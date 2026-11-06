<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\DashboardCalculator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $allSessions = AcademicSession::requestWantsAll($request);
        $session = $allSessions ? null : AcademicSession::fromRequest($request, true);

        return response()->json(DashboardCalculator::summary($session, $allSessions));
    }

    /** The Today card for a picked date (?date=Y-m-d): attendance and fee collected that day. */
    public function day(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);

        return response()->json(DashboardCalculator::daySummary($data['date']));
    }

    /** Header bell — live operational alert counts (cached ~60s). */
    public function notifications(Request $request)
    {
        $allSessions = AcademicSession::requestWantsAll($request);
        $session = $allSessions ? null : AcademicSession::fromRequest($request, true);

        return response()->json(DashboardCalculator::notifications($session, $allSessions));
    }
}
