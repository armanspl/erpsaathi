<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AttendanceCache
{
    public const LOOKUPS = 'erp.attendance.lookups.v1';

    public const HOLIDAYS = 'erp.attendance.holidays.v1';

    public const WORKING_DAYS = 'erp.attendance.working_days.v1';

    public const TTL = 60;

    public static function forget(): void
    {
        Cache::forget(self::LOOKUPS);
        Cache::forget(self::HOLIDAYS);
        Cache::forget(self::WORKING_DAYS);
    }
}
