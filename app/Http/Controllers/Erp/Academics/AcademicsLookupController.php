<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Support\AcademicsCache;
use Illuminate\Support\Facades\Cache;

/**
 * One lightweight payload for dropdowns / filters across the ERP.
 * Prefer this over three separate /branches + /classes + /sections calls.
 */
class AcademicsLookupController extends Controller
{
    public function __invoke()
    {
        $payload = Cache::remember(AcademicsCache::LOOKUPS, AcademicsCache::TTL, function () {
            $classes = SchoolClass::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']);

            $sections = Section::query()
                ->orderBy('name')
                ->get(['id', 'name', 'school_class_id', 'capacity']);

            $byClass = [];
            foreach ($sections as $section) {
                $byClass[$section->school_class_id][] = [
                    'id' => $section->id,
                    'name' => $section->name,
                    'school_class_id' => $section->school_class_id,
                    'capacity' => $section->capacity,
                ];
            }

            return [
                'branches' => Branch::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'status']),
                'classes' => $classes->map(fn (SchoolClass $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sort_order' => $c->sort_order,
                    'sections' => $byClass[$c->id] ?? [],
                ])->values(),
                'sections' => $sections,
                'subjects' => Subject::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'code']),
            ];
        });

        return response()->json($payload);
    }
}
