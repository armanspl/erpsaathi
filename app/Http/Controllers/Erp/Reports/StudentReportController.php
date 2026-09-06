<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Services\StudentReportCalculator;

class StudentReportController extends Controller
{
    public function index()
    {
        return response()->json(StudentReportCalculator::summary());
    }
}
