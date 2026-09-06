<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FineRule;
use App\Support\FeeCache;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight Fee Management boot payload for sidebar prefetch.
 */
class FeeLookupController extends Controller
{
    public function __invoke()
    {
        $payload = Cache::remember(FeeCache::LOOKUPS, FeeCache::TTL, function () {
            return [
                'heads' => FeeHead::query()->orderBy('name')->get(['id', 'name', 'description']),
                'fine_rules' => FineRule::query()->orderBy('name')->get(),
                'counts' => [
                    'heads' => FeeHead::query()->count(),
                    'discounts' => FeeDiscount::query()->count(),
                    'fine_rules' => FineRule::query()->count(),
                    'payments_today' => FeePayment::query()->whereDate('payment_date', now()->toDateString())->count(),
                ],
            ];
        });

        return response()->json($payload);
    }
}
