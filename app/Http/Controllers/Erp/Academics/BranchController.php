<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BranchController extends Controller
{
    public function index()
    {
        $payload = Cache::remember(AcademicsCache::BRANCHES, AcademicsCache::TTL, function () {
            return Branch::query()->orderBy('name')->get();
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'principal' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $branch = Branch::create($data);
        AcademicsCache::forget();

        return response()->json($branch, 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'principal' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $branch->update($data);
        AcademicsCache::forget();

        return response()->json($branch);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        AcademicsCache::forget();

        return response()->json(['success' => true]);
    }
}
