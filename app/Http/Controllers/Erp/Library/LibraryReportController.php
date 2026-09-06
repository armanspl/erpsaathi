<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Services\LibraryReportCalculator;

class LibraryReportController extends Controller
{
    public function index()
    {
        return response()->json(LibraryReportCalculator::summary());
    }
}
