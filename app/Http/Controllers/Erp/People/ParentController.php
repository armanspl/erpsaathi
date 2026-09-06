<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ParentController extends Controller
{
    public function index(Request $request)
    {
        // lite=1 — dropdowns (Students form); skip children_count.
        if ($request->boolean('lite')) {
            $payload = Cache::remember(PeopleCache::PARENTS_LITE, PeopleCache::TTL, function () {
                return ParentGuardian::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'phone', 'email']);
            });

            return response()->json($payload);
        }

        $parents = ParentGuardian::query()
            ->orderBy('name')
            ->get();

        $childCounts = $this->childCountsByParentId();

        $parents->each(function (ParentGuardian $parent) use ($childCounts) {
            $parent->children_count = $childCounts[$parent->id] ?? 0;
            $parent->students_as_father_count = 0;
            $parent->students_as_mother_count = 0;
            $parent->students_as_guardian_count = 0;
        });

        return response()->json($parents);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
            'address' => 'nullable|string|max:255',
        ]);

        $parent = ParentGuardian::create($data);
        PeopleCache::forget();

        return response()->json($parent, 201);
    }

    public function update(Request $request, ParentGuardian $parent)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
            'address' => 'nullable|string|max:255',
        ]);

        $parent->update($data);
        PeopleCache::forget();

        return response()->json($parent);
    }

    public function destroy(ParentGuardian $parent)
    {
        $parent->delete();
        PeopleCache::forget();

        return response()->json(['success' => true]);
    }

    /**
     * Three grouped aggregates instead of withCount() subqueries on every parent row.
     *
     * @return array<int, int>
     */
    private function childCountsByParentId(): array
    {
        $counts = [];
        foreach (['father_id', 'mother_id', 'guardian_id'] as $column) {
            foreach (
                Student::query()
                    ->whereNotNull($column)
                    ->selectRaw("{$column} as parent_id, COUNT(*) as aggregate")
                    ->groupBy($column)
                    ->pluck('aggregate', 'parent_id') as $parentId => $aggregate
            ) {
                $counts[(int) $parentId] = ($counts[(int) $parentId] ?? 0) + (int) $aggregate;
            }
        }

        return $counts;
    }
}
