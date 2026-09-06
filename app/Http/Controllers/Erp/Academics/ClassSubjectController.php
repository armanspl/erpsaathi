<?php

namespace App\Http\Controllers\Erp\Academics;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Support\AcademicsCache;
use Illuminate\Http\Request;

class ClassSubjectController extends Controller
{
    /**
     * Replace the full set of subjects assigned to a class.
     */
    public function sync(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'subject_ids' => 'array',
            'subject_ids.*' => 'integer|exists:subjects,id',
        ]);

        $schoolClass->subjects()->sync($data['subject_ids'] ?? []);
        AcademicsCache::forget();

        return response()->json($schoolClass->load('subjects:id,name,code'));
    }
}
