<?php

namespace App\Http\Controllers\Erp\Meetings;

use App\Http\Controllers\Controller;
use App\Services\MeetingReportCalculator;

class MeetingReportController extends Controller
{
    public function index()
    {
        return response()->json(MeetingReportCalculator::summary());
    }
}
