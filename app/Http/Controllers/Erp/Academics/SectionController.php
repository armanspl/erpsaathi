<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('school_class_id')) {
            return response()->json(
                Section::query()
                    ->where('school_class_id', $request->integer('school_class_id'))
                    ->orderBy('name')
                    ->get(['id', 'school_class_id', 'name', 'capacity', 'class_teacher'])
            );
        }

        $payload = Cache::remember(AcademicsCache::SECTIONS, AcademicsCache::TTL, function () {
            return Section::query()
                ->orderBy('name')
                ->get(['id', 'school_class_id', 'name', 'capacity', 'class_teacher']);
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')->where(fn ($q) => $q->where('school_class_id', $request->school_class_id)),
            ],
            'capacity' => 'nullable|integer|min:1',
            'class_teacher' => 'nullable|string|max:255',
        ]);

        $section = Section::create($data);
        AcademicsCache::forget();

        return response()->json($section, 201);
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where(fn ($q) => $q->where('school_class_id', $request->school_class_id))
                    ->ignore($section->id),
            ],
            'capacity' => 'nullable|integer|min:1',
            'class_teacher' => 'nullable|string|max:255',
        ]);

        $section->update($data);
        AcademicsCache::forget();

        return response()->json($section);
    }

    public function destroy(Section $section)
    {
        $section->delete();
        AcademicsCache::forget();

        return response()->json(['success' => true]);
    }
}
