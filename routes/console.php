<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Empties import_failed_rows / audit_logs / import_row_logs in every tenant DB.
// Runs via the server cron entry for `php artisan schedule:run` (every minute).
// Only fires once ERP_CLEANUP_LOGS_ENABLED=true (config/tenancy.php) — off by default.
Schedule::command('erp:cleanup-logs')
    ->everyFourHours()
    ->when(fn () => (bool) config('tenancy.cleanup_logs_enabled'));
