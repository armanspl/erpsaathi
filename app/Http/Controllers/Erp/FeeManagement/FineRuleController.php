<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\FineRule;
use App\Support\FeeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class FineRuleController extends Controller
{
    public function index()
    {
        $rules = Cache::remember(FeeCache::FINE_RULES, FeeCache::TTL, fn () => FineRule::query()->orderBy('name')->get());

        return response()->json($rules);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $rule = FineRule::create($data);
        FeeCache::forget();

        return response()->json($rule, 201);
    }

    public function update(Request $request, FineRule $fineRule)
    {
        $data = $this->validated($request);

        $fineRule->update($data);
        FeeCache::forget();

        return response()->json($fineRule);
    }

    public function destroy(FineRule $fineRule)
    {
        $fineRule->delete();
        FeeCache::forget();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['per_day', 'fixed'])],
            'amount' => 'required|numeric|min:0',
            'grace_days' => 'nullable|integer|min:0',
        ]);
    }
}
