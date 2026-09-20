<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\Homework;
use App\Models\HomeworkItem;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HomeworkController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('homework');

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $rows = Homework::query()
            ->where('school_class_id', $student->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $student->section_id))
            ->with(['teacher:id,name', 'items.subject:id,name'])
            ->orderByDesc('assigned_date')
            ->get();

        return response()->json($rows->map(fn (Homework $h) => [
            'id' => $h->id,
            'assigned_date' => $h->assigned_date?->format('Y-m-d'),
            'description' => $h->description,
            'teacher' => $h->teacher?->name,
            'items' => $h->items->map(fn (HomeworkItem $item) => [
                'id' => $item->id,
                'subject' => $item->subject?->name,
                'content' => $item->content,
                'has_attachment' => (bool) $item->attachment_path,
                'attachment_name' => $item->attachment_name,
            ])->values(),
        ])->values());
    }

    public function download(HomeworkItem $homeworkItem)
    {
        $this->abortIfModuleDisabled('homework');

        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $homeworkItem->loadMissing('homework');
        $homework = $homeworkItem->homework;

        $belongsToClass = $homework
            && $homework->school_class_id === $student->school_class_id
            && ($homework->section_id === null || $homework->section_id === $student->section_id);
        abort_unless($belongsToClass, 404);
        abort_unless($homeworkItem->attachment_path && Storage::disk('local')->exists($homeworkItem->attachment_path), 404);

        return Storage::disk('local')->download(
            $homeworkItem->attachment_path,
            $homeworkItem->attachment_name ?: basename($homeworkItem->attachment_path)
        );
    }
}
