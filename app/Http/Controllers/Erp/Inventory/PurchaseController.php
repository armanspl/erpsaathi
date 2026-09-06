<?php

namespace App\Http\Controllers\Erp\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        return response()->json(
            Purchase::with(['supplier:id,name', 'purchasedBy:id,name'])
                ->orderByDesc('purchase_date')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $productNames = Product::whereIn('id', collect($data['items'])->pluck('product_id'))->pluck('name', 'id');
        $items = collect($data['items'])->map(fn ($item) => [
            'product_id' => $item['product_id'],
            'product_name' => $productNames[$item['product_id']] ?? 'Product',
            'quantity' => (int) $item['quantity'],
            'unit_cost' => (float) $item['unit_cost'],
            'total' => round($item['quantity'] * $item['unit_cost'], 2),
        ])->values();

        $purchase = DB::transaction(function () use ($data, $items) {
            $purchase = Purchase::create([
                'voucher_no' => $this->nextVoucherNo(),
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'items' => $items,
                'total_amount' => $items->sum('total'),
                'remarks' => $data['remarks'] ?? null,
                'purchased_by_id' => Auth::guard('erp')->id(),
            ]);

            foreach ($items as $item) {
                Product::where('id', $item['product_id'])->increment('current_stock', $item['quantity']);
            }

            return $purchase;
        });

        return response()->json($purchase->load(['supplier:id,name', 'purchasedBy:id,name']), 201);
    }

    private function nextVoucherNo(): string
    {
        $year = now()->format('Y');
        $count = Purchase::where('voucher_no', 'like', "PO-{$year}-%")->count() + 1;

        return sprintf('PO-%s-%04d', $year, $count);
    }
}
