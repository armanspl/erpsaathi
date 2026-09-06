<?php

namespace App\Http\Controllers\Erp\System;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class CacheManagerController extends Controller
{
    public function index()
    {
        return response()->json([
            'driver' => config('cache.default'),
            'store_config' => config('cache.stores.' . config('cache.default')),
        ]);
    }

    public function clear()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        return response()->json(['success' => true, 'message' => 'Application, config, route and view caches cleared.']);
    }
}
