<?php

namespace App\Http\Controllers\Erp\System;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function index()
    {
        $dbStatus = 'Down';
        try {
            DB::connection()->getPdo();
            $dbStatus = 'Connected';
        } catch (\Throwable) {
            $dbStatus = 'Down';
        }

        $storagePath = storage_path();
        $freeBytes = @disk_free_space($storagePath);

        return response()->json([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => (bool) config('app.debug'),
            'database' => [
                'connection' => config('database.default'),
                'status' => $dbStatus,
            ],
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'storage_writable' => is_writable($storagePath),
            'disk_free_space_gb' => $freeBytes !== false ? round($freeBytes / 1024 / 1024 / 1024, 2) : null,
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
