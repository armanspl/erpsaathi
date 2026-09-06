<?php

namespace App\Http\Controllers\Erp\Inventory;

use App\Http\Controllers\Controller;
use App\Services\InventoryReportCalculator;

class InventoryReportController extends Controller
{
    public function index()
    {
        return response()->json(InventoryReportCalculator::summary());
    }
}
