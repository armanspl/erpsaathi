<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;

class ClassSubjectController extends Controller
{
    /**
     * Replace the full set of subjects assigned to a class. `subjects` (preferred) carries each
     * subject's optional/elective flag; `subject_ids` (legacy shape) is still accepted and treats
     * every subject as compulsory, so existing callers keep working unchanged.
     */
    public function sync(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'subject_ids' => 'array',
            'subject_ids.*' => 'integer|exists:subjects,id',
            'subjects' => 'array',
            'subjects.*.subject_id' => 'required_with:subjects|integer|exists:subjects,id',
            'subjects.*.is_optional' => 'boolean',
            'subjects.*.elective_group' => 'nullable|string|max:60',
        ]);

        if (! empty($data['subjects'])) {
            $sync = collect($data['subjects'])->mapWithKeys(fn ($s) => [
                (int) $s['subject_id'] => [
                    'is_optional' => (bool) ($s['is_optional'] ?? false),
                    'elective_group' => $s['elective_group'] ?? null,
                ],
            ])->all();
        } else {
            $sync = collect($data['subject_ids'] ?? [])->mapWithKeys(fn ($id) => [(int) $id => ['is_optional' => false, 'elective_group' => null]])->all();
        }

        $schoolClass->subjects()->sync($sync);
        AcademicsCache::forget();

        return response()->json($schoolClass->load('subjects:id,name,code'));
    }
}
