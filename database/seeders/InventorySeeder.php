<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Om Stationery Mart'],
            ['contact' => '9866600001', 'email' => 'sales@omstationery.test', 'address' => 'MI Road, Jaipur']
        );

        $paper = Product::firstOrCreate(
            ['sku' => 'PROD-001'],
            ['name' => 'A4 Paper Ream', 'category' => 'Stationery', 'unit' => 'ream', 'cost_price' => 250, 'reorder_level' => 20, 'current_stock' => 0]
        );
        $marker = Product::firstOrCreate(
            ['sku' => 'PROD-002'],
            ['name' => 'Whiteboard Marker', 'category' => 'Stationery', 'unit' => 'pcs', 'cost_price' => 20, 'reorder_level' => 10, 'current_stock' => 0]
        );

        $purchase = Purchase::firstOrCreate(
            ['voucher_no' => 'PO-2026-0001'],
            [
                'supplier_id' => $supplier->id,
                'purchase_date' => '2026-07-20',
                'items' => [
                    ['product_id' => $paper->id, 'product_name' => $paper->name, 'quantity' => 15, 'unit_cost' => 250, 'total' => 3750],
                    ['product_id' => $marker->id, 'product_name' => $marker->name, 'quantity' => 50, 'unit_cost' => 20, 'total' => 1000],
                ],
                'total_amount' => 4750,
            ]
        );

        if ($purchase->wasRecentlyCreated) {
            $paper->increment('current_stock', 15);
            $marker->increment('current_stock', 50);
        }
    }
}
