<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        // with_counts=1 — Subjects management page stats.
        if ($request->boolean('with_counts')) {
            return response()->json(
                Subject::withCount('classes')->orderBy('name')->get()
            );
        }

        $payload = Cache::remember(AcademicsCache::SUBJECTS, AcademicsCache::TTL, function () {
            return Subject::query()->orderBy('name')->get(['id', 'name', 'code']);
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:subjects,code',
        ]);

        $subject = Subject::create($data);
        AcademicsCache::forget();

        return response()->json($subject, 201);
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($subject->id)],
        ]);

        $subject->update($data);
        AcademicsCache::forget();

        return response()->json($subject);
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();
        AcademicsCache::forget();

        return response()->json(['success' => true]);
    }
}
