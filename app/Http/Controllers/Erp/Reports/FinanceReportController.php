<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\FinanceReportCalculator;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $all = AcademicSession::requestWantsAll($request);
        $session = $all ? null : AcademicSession::fromRequest($request, true);

        return response()->json(FinanceReportCalculator::summary($session, $all));
    }
}
