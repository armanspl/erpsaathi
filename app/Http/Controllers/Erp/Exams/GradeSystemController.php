<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\GradeSystem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GradeSystemController extends Controller
{
    public function index()
    {
        return response()->json(GradeSystem::orderByDesc('min_percentage')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertNoOverlap($data);

        $grade = GradeSystem::create($data);

        return response()->json($grade, 201);
    }

    public function update(Request $request, GradeSystem $gradeSystem)
    {
        $data = $this->validated($request);
        $this->assertNoOverlap($data, $gradeSystem->id);

        $gradeSystem->update($data);

        return response()->json($gradeSystem);
    }

    public function destroy(GradeSystem $gradeSystem)
    {
        $gradeSystem->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'grade' => 'required|string|max:10',
            'min_percentage' => 'required|numeric|min:0|max:100',
            'max_percentage' => 'required|numeric|min:0|max:100|gte:min_percentage',
            'remarks' => 'nullable|string|max:255',
        ]);
    }

    /** A new/edited range may not overlap any other grade's [min, max]. */
    private function assertNoOverlap(array $data, ?int $ignoreId = null): void
    {
        $overlap = GradeSystem::query()
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('min_percentage', '<=', $data['max_percentage'])
            ->where('max_percentage', '>=', $data['min_percentage'])
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'min_percentage' => "This range overlaps with grade \"{$overlap->grade}\" ({$overlap->min_percentage}%–{$overlap->max_percentage}%).",
            ]);
        }
    }
}
