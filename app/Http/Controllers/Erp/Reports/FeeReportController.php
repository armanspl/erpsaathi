<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\FeeReportCalculator;
use Illuminate\Http\Request;

class FeeReportController extends Controller
{
    public function index(Request $request)
    {
        if (AcademicSession::requestWantsAll($request)) {
            return response()->json(FeeReportCalculator::summary(null, true));
        }

        return response()->json(FeeReportCalculator::summary(AcademicSession::fromRequest($request, true)));
    }
}
