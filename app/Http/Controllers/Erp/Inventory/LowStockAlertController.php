<?php

namespace App\Http\Controllers\Erp\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;

class LowStockAlertController extends Controller
{
    public function index()
    {
        return response()->json(
            Product::whereColumn('current_stock', '<=', 'reorder_level')
                ->orderBy('current_stock')
                ->get()
        );
    }
}
