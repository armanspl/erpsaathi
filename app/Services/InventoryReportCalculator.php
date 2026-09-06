<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;

class InventoryReportCalculator
{
    /** Inventory operations snapshot, computed live from products/purchases. */
    public static function summary(): array
    {
        $products = Product::all();
        $lowStock = $products->filter(fn (Product $p) => $p->current_stock <= $p->reorder_level);

        $thisMonthPurchases = Purchase::whereBetween('purchase_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->get();

        $topPurchased = Purchase::get()
            ->flatMap(fn (Purchase $p) => $p->items)
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => [
                'product_id' => (int) $productId,
                'product_name' => $rows->first()['product_name'],
                'total_quantity' => (int) $rows->sum('quantity'),
            ])
            ->sortByDesc('total_quantity')
            ->take(5)
            ->values();

        return [
            'total_products' => $products->count(),
            'total_stock_units' => (int) $products->sum('current_stock'),
            'total_stock_value' => round((float) $products->sum(fn (Product $p) => $p->current_stock * (float) $p->cost_price), 2),
            'low_stock_count' => $lowStock->count(),
            'total_suppliers' => Supplier::count(),
            'purchases_this_month' => $thisMonthPurchases->count(),
            'purchase_value_this_month' => round((float) $thisMonthPurchases->sum('total_amount'), 2),
            'low_stock_list' => $lowStock->map(fn (Product $p) => [
                'product_id' => $p->id,
                'name' => $p->name,
                'current_stock' => $p->current_stock,
                'reorder_level' => $p->reorder_level,
            ])->values(),
            'top_purchased' => $topPurchased,
        ];
    }
}
