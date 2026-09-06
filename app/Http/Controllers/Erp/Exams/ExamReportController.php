<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\ExamResultCalculator;
use Illuminate\Http\Request;

class ExamReportController extends Controller
{
    public function index(Request $request, Exam $exam)
    {
        $schoolClassId = $request->filled('school_class_id') ? $request->integer('school_class_id') : null;
        $rows = collect(ExamResultCalculator::forExam($exam, $schoolClassId));

        $total = $rows->count();
        $passed = $rows->where('result', 'Pass')->count();
        $failed = $total - $passed;
        $topper = $rows->sortByDesc('percentage')->first();
        $average = $total > 0 ? round($rows->avg('percentage'), 2) : 0;

        return response()->json([
            'total_students' => $total,
            'pass_percentage' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'fail_percentage' => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
            'average_percentage' => $average,
            'topper' => $topper ? ['name' => $topper['name'], 'admission_no' => $topper['admission_no'], 'percentage' => $topper['percentage']] : null,
            'rows' => $rows->values(),
        ]);
    }
}
