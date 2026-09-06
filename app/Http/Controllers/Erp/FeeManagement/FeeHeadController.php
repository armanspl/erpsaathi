<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\FeeHead;
use App\Support\FeeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FeeHeadController extends Controller
{
    public function index()
    {
        $heads = Cache::remember(FeeCache::HEADS, FeeCache::TTL, fn () => FeeHead::query()->orderBy('name')->get());

        return response()->json($heads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $head = FeeHead::create($data);
        FeeCache::forget();

        return response()->json($head, 201);
    }

    public function update(Request $request, FeeHead $feeHead)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $feeHead->update($data);
        FeeCache::forget();

        return response()->json($feeHead);
    }

    public function destroy(FeeHead $feeHead)
    {
        $feeHead->delete();
        FeeCache::forget();

        return response()->json(['success' => true]);
    }
}
