<?php

namespace App\Http\Controllers\Erp\System;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Schedule;

class ScheduledJobController extends Controller
{
    public function index(Schedule $schedule)
    {
        $events = collect($schedule->events())->map(fn ($event) => [
            'command' => trim(str_replace(['artisan', "'" . PHP_BINARY . "'"], '', $event->command ?? $event->description ?? 'Closure task')),
            'expression' => $event->expression,
            'next_due' => $event->nextRunDate()?->toDateTimeString(),
            'description' => $event->description,
        ]);

        return response()->json([
            'count' => $events->count(),
            'events' => $events->values(),
        ]);
    }
}
