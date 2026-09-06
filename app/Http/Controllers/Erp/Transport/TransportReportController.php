<?php

namespace App\Http\Controllers\Erp\Transport;

use App\Http\Controllers\Controller;
use App\Services\TransportReportCalculator;

class TransportReportController extends Controller
{
    public function index()
    {
        return response()->json(TransportReportCalculator::summary());
    }
}
