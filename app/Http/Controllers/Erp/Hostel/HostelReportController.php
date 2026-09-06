<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Services\HostelReportCalculator;

class HostelReportController extends Controller
{
    public function index()
    {
        return response()->json(HostelReportCalculator::summary());
    }
}
