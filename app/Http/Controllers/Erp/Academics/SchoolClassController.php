<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    public function index(Request $request)
    {
        // full=1 — management pages (Classes & Sections, Homework subject picker).
        // Default — compact list used by filters/dropdowns across the ERP.
        $full = $request->boolean('full');

        $key = $full ? AcademicsCache::CLASSES_FULL : AcademicsCache::CLASSES_COMPACT;

        $payload = Cache::remember($key, AcademicsCache::TTL, function () use ($full) {
            $query = SchoolClass::query()
                ->orderBy('sort_order')
                ->orderBy('name');

            if ($full) {
                return $query
                    ->with([
                        'sections:id,school_class_id,name,capacity,class_teacher',
                        'subjects:id,name,code',
                    ])
                    ->get(['id', 'name', 'capacity', 'sort_order', 'created_at', 'updated_at']);
            }

            return $query
                ->with(['sections:id,school_class_id,name,capacity'])
                ->get(['id', 'name', 'capacity', 'sort_order']);
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:school_classes,name',
            'capacity' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $schoolClass = SchoolClass::create($data);
        AcademicsCache::forget();

        return response()->json(
            $schoolClass->load(['sections:id,school_class_id,name,capacity,class_teacher', 'subjects:id,name,code']),
            201
        );
    }

    public function update(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($schoolClass->id)],
            'capacity' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $schoolClass->update($data);
        AcademicsCache::forget();

        return response()->json(
            $schoolClass->load(['sections:id,school_class_id,name,capacity,class_teacher', 'subjects:id,name,code'])
        );
    }

    public function destroy(SchoolClass $schoolClass)
    {
        $schoolClass->delete();
        AcademicsCache::forget();

        return response()->json(['success' => true]);
    }
}
