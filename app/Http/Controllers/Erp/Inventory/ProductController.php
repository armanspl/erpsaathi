<?php

namespace App\Http\Controllers\Erp\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'opening_stock' => 'nullable|integer|min:0',
        ]);

        $product = Product::create([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'category' => $data['category'] ?? null,
            'unit' => $data['unit'],
            'cost_price' => $data['cost_price'],
            'reorder_level' => $data['reorder_level'],
            'current_stock' => $data['opening_stock'] ?? 0,
        ]);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['success' => true]);
    }
}
