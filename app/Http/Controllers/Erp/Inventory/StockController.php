<?php

namespace App\Http\Controllers\Erp\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index()
    {
        return response()->json(Product::orderBy('name')->get());
    }

    public function adjustments(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        return response()->json(
            StockAdjustment::where('product_id', $request->integer('product_id'))->orderByDesc('date')->get()
        );
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => ['required', Rule::in(['Addition', 'Reduction'])],
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        $product = Product::findOrFail($data['product_id']);
        if ($data['type'] === 'Reduction' && $data['quantity'] > $product->current_stock) {
            throw ValidationException::withMessages(['quantity' => "Cannot reduce stock by more than the current stock ({$product->current_stock})."]);
        }

        $adjustment = DB::transaction(function () use ($data, $product) {
            $adjustment = StockAdjustment::create([...$data, 'adjusted_by_id' => Auth::guard('erp')->id()]);

            $product->increment('current_stock', $data['type'] === 'Addition' ? $data['quantity'] : -$data['quantity']);

            return $adjustment;
        });

        return response()->json($adjustment->load('product:id,name,current_stock'), 201);
    }
}
