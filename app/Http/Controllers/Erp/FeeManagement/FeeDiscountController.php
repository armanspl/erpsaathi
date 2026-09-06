<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\FeeDiscount;
use App\Support\FeeCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeDiscountController extends Controller
{
    public function index()
    {
        return response()->json(
            FeeDiscount::with(['student:id,name,admission_no', 'feeHead:id,name'])->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $discount = FeeDiscount::create($data);
        FeeCache::forget();

        return response()->json($discount->load(['student:id,name,admission_no', 'feeHead:id,name']), 201);
    }

    public function update(Request $request, FeeDiscount $feeDiscount)
    {
        $data = $this->validated($request);

        $feeDiscount->update($data);
        FeeCache::forget();

        return response()->json($feeDiscount->load(['student:id,name,admission_no', 'feeHead:id,name']));
    }

    public function destroy(FeeDiscount $feeDiscount)
    {
        $feeDiscount->delete();
        FeeCache::forget();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_head_id' => 'nullable|exists:fee_heads,id',
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);
    }
}
