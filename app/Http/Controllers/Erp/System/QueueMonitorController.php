<?php

namespace App\Http\Controllers\Erp\System;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class QueueMonitorController extends Controller
{
    public function index()
    {
        return response()->json([
            'connection' => config('queue.default'),
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'recent_failed' => DB::table('failed_jobs')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'queue', 'exception', 'failed_at'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'queue' => $row->queue,
                    'exception' => str($row->exception)->limit(300)->toString(),
                    'failed_at' => $row->failed_at,
                ]),
        ]);
    }
}
