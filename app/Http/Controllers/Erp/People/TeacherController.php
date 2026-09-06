<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\ErpUser;
use App\Models\Teacher;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    private const RELATIONS = ['schoolClass:id,name', 'subjects:id,name,code', 'classAssignments.schoolClass:id,name', 'classAssignments.section:id,name'];

    public function index()
    {
        $payload = Cache::remember(PeopleCache::TEACHERS, PeopleCache::TTL, function () {
            return Teacher::with(self::RELATIONS)->orderBy('name')->get();
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        [$teacher, $accountStatus] = DB::transaction(function () use ($data) {
            $data['employee_id'] = $this->nextEmployeeId();
            $teacher = Teacher::create($data);

            return [$teacher, $this->provisionLinkedAccount($teacher)];
        });

        PeopleCache::forget();

        return response()->json(array_merge(
            $teacher->load(self::RELATIONS)->toArray(),
            ['account_status' => $accountStatus]
        ), 201);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $data = $this->validated($request);

        $accountStatus = DB::transaction(function () use ($data, $teacher) {
            $teacher->update($data);

            return $this->provisionLinkedAccount($teacher);
        });

        PeopleCache::forget();

        return response()->json(array_merge(
            $teacher->load(self::RELATIONS)->toArray(),
            ['account_status' => $accountStatus]
        ));
    }

    public function destroy(Teacher $teacher)
    {
        if ($teacher->signature_path) {
            Storage::disk('local')->delete($teacher->signature_path);
        }

        $teacher->delete();
        PeopleCache::forget();

        return response()->json(['success' => true]);
    }

    /** Serve the teacher's signature image (auth-gated private disk). */
    public function signature(Teacher $teacher)
    {
        abort_unless($teacher->signature_path && Storage::disk('local')->exists($teacher->signature_path), 404);

        return Storage::disk('local')->response($teacher->signature_path);
    }

    /** Upload / replace signature image for create+edit flows. */
    public function uploadSignature(Request $request, Teacher $teacher)
    {
        $request->validate([
            'signature' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
        ]);

        if ($teacher->signature_path) {
            Storage::disk('local')->delete($teacher->signature_path);
        }

        $path = $request->file('signature')->store('teacher-signatures/'.$teacher->id, 'local');
        $teacher->update(['signature_path' => $path]);
        PeopleCache::forget();

        return response()->json($teacher->fresh()->load(self::RELATIONS));
    }

    public function deleteSignature(Teacher $teacher)
    {
        if ($teacher->signature_path) {
            Storage::disk('local')->delete($teacher->signature_path);
            $teacher->update(['signature_path' => null]);
            PeopleCache::forget();
        }

        return response()->json($teacher->fresh()->load(self::RELATIONS));
    }

    /** Replace the full set of class/section assignments for this teacher (Assign action). */
    public function syncAssignments(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'assignments' => 'array',
            'assignments.*.school_class_id' => 'required|integer|exists:school_classes,id',
            'assignments.*.section_id' => 'nullable|integer|exists:sections,id',
            'assignments.*.role' => ['required', Rule::in(['head', 'assistant'])],
        ]);

        DB::transaction(function () use ($data, $teacher) {
            $teacher->classAssignments()->delete();
            foreach ($data['assignments'] ?? [] as $row) {
                $teacher->classAssignments()->create([
                    'school_class_id' => $row['school_class_id'],
                    'section_id' => $row['section_id'] ?? null,
                    'role' => $row['role'],
                ]);
            }
        });

        PeopleCache::forget();

        return response()->json($teacher->load(self::RELATIONS));
    }

    /** Replace the full set of subjects this teacher teaches (Subjects action). */
    public function syncSubjects(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'subject_ids' => 'array',
            'subject_ids.*' => 'integer|exists:subjects,id',
        ]);

        $teacher->subjects()->sync($data['subject_ids'] ?? []);
        PeopleCache::forget();

        return response()->json($teacher->load(self::RELATIONS));
    }

    /** Replace the full set of ad-hoc custom field values for this teacher (Fields action). */
    public function updateCustomFields(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'fields' => 'array',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.value' => 'nullable|string|max:1000',
        ]);

        $fields = collect($data['fields'] ?? [])
            ->filter(fn ($f) => trim($f['label'] ?? '') !== '')
            ->values()
            ->all();

        $teacher->update(['custom_field_values' => $fields]);
        PeopleCache::forget();

        return response()->json($teacher->load(self::RELATIONS));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'status' => 'required|in:active,inactive',
            'salary' => 'nullable|numeric|min:0|max:99999999.99',
        ]);
    }

    private function nextEmployeeId(): string
    {
        $last = Teacher::query()
            ->where('employee_id', 'like', 'TCH-%')
            ->orderByDesc('id')
            ->value('employee_id');

        $next = $last ? ((int) str_replace('TCH-', '', $last) + 1) : 1;

        return 'TCH-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create or link the ErpUser account matched by email, so the teacher can log in
     * and use the self-service attendance check-in. Never overwrites an existing
     * account's password/role — a pre-existing account with that email is only linked,
     * not modified, since it may belong to a different intentionally-configured login.
     */
    private function provisionLinkedAccount(Teacher $teacher): string
    {
        if (! $teacher->email) {
            return 'no_email';
        }

        $existing = ErpUser::where('email', $teacher->email)->first();
        if ($existing) {
            return 'linked_existing';
        }

        ErpUser::create([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'password' => Str::random(32),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        return 'created';
    }
}
