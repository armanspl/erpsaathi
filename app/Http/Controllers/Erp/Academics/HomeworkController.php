<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Homework;
use App\Models\HomeworkItem;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max($request->integer('limit', 150), 1), 500);

        return response()->json(
            Homework::query()
                ->with([
                    'branch:id,name',
                    'schoolClass:id,name',
                    'section:id,name',
                    'teacher:id,name',
                    'academicSession:id,name',
                    'items.subject:id,name,code',
                ])
                ->tap(fn ($q) => AcademicSession::applySessionIdFilter($q, $request))
                ->orderByDesc('assigned_date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validatedHeader($request);
        $items = $this->validatedItems($request, $data['school_class_id']);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add homework text or a file for at least one subject.',
            ]);
        }

        $homework = DB::transaction(function () use ($request, $data, $items) {
            $homework = Homework::create($data);

            foreach ($items as $item) {
                $this->storeItem($homework, $item, $request);
            }

            return $homework;
        });

        return response()->json($this->loadRelations($homework), 201);
    }

    public function update(Request $request, Homework $homework)
    {
        $data = $this->validatedHeader($request);
        $items = $this->validatedItems($request, $data['school_class_id']);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add homework text or a file for at least one subject.',
            ]);
        }

        DB::transaction(function () use ($request, $homework, $data, $items) {
            $homework->update($data);

            // Replace items — remove old attachments via model deleting hook.
            $homework->items()->each(fn (HomeworkItem $item) => $item->delete());

            foreach ($items as $item) {
                $this->storeItem($homework, $item, $request);
            }
        });

        return response()->json($this->loadRelations($homework->fresh()));
    }

    public function destroy(Homework $homework)
    {
        $homework->items()->each(fn (HomeworkItem $item) => $item->delete());
        $homework->delete();

        return response()->json(['success' => true]);
    }

    public function downloadAttachment(HomeworkItem $homeworkItem)
    {
        if (! $homeworkItem->attachment_path || ! Storage::disk('local')->exists($homeworkItem->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $homeworkItem->attachment_path,
            $homeworkItem->attachment_name ?: basename($homeworkItem->attachment_path)
        );
    }

    private function loadRelations(Homework $homework): Homework
    {
        return $homework->load([
            'branch:id,name',
            'schoolClass:id,name',
            'section:id,name',
            'teacher:id,name',
            'academicSession:id,name',
            'items.subject:id,name,code',
        ]);
    }

    private function validatedHeader(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'assigned_date' => 'required|date',
            'description' => 'nullable|string|max:2000',
        ]);

        if (! empty($data['section_id'])) {
            $belongs = \App\Models\Section::where('id', $data['section_id'])
                ->where('school_class_id', $data['school_class_id'])
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'section_id' => 'The selected section does not belong to the selected class.',
                ]);
            }
        }

        $session = AcademicSession::fromRequest($request, true);
        $data['academic_session_id'] = $session?->id;

        return $data;
    }

    /**
     * Items arrive as JSON string when using multipart FormData, or as array for JSON posts.
     *
     * @return list<array{subject_id: int, content: string}>
     */
    private function validatedItems(Request $request, int $schoolClassId): array
    {
        $raw = $request->input('items', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?? [];
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages(['items' => 'Invalid subjects payload.']);
        }

        $allowedSubjectIds = SchoolClass::findOrFail($schoolClassId)
            ->subjects()
            ->pluck('subjects.id')
            ->all();

        $items = [];
        foreach ($raw as $index => $row) {
            $subjectId = (int) ($row['subject_id'] ?? 0);
            $content = trim((string) ($row['content'] ?? ''));
            $fileKey = "attachments.{$subjectId}";
            $hasFile = $request->hasFile($fileKey);

            if ($subjectId === 0) {
                continue;
            }
            if (! in_array($subjectId, $allowedSubjectIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.subject_id" => 'Subject is not assigned to the selected class.',
                ]);
            }
            if ($content === '' && ! $hasFile) {
                continue;
            }

            $items[] = [
                'subject_id' => $subjectId,
                'content' => $content,
            ];
        }

        return $items;
    }

    /** @param  array{subject_id: int, content: string}  $item */
    private function storeItem(Homework $homework, array $item, Request $request): void
    {
        $path = null;
        $name = null;
        $fileKey = "attachments.{$item['subject_id']}";

        if ($request->hasFile($fileKey)) {
            $request->validate([
                $fileKey => 'file|max:10240',
            ]);
            $file = $request->file($fileKey);
            $path = $file->store("homework-attachments/{$homework->id}", 'local');
            $name = $file->getClientOriginalName();
        }

        HomeworkItem::create([
            'homework_id' => $homework->id,
            'subject_id' => $item['subject_id'],
            'content' => $item['content'] !== '' ? $item['content'] : null,
            'attachment_path' => $path,
            'attachment_name' => $name,
        ]);
    }
}
