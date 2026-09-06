<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Services\AdmissionReportCalculator;

class AdmissionReportController extends Controller
{
    public function index()
    {
        return response()->json(AdmissionReportCalculator::summary());
    }
}
