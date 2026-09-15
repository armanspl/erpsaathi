<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache keys for the ERP dashboard / fee-report snapshots.
 * Bumping the version invalidates every session-scoped key without enumerating them.
 */
class DashboardCache
{
    public const VERSION_KEY = 'erp.dashboard.cache_ver';

    public const TTL = 90;

    public static function version(): int
    {
        return max(1, (int) Cache::get(self::VERSION_KEY, 1));
    }

    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function summaryKey(?int $sessionId, bool $allSessions): string
    {
        $scope = $allSessions ? 'all' : ($sessionId ?? 'none');

        return 'erp.dashboard.summary.v'.self::version().'.'.$scope;
    }

    public static function feeReportKey(?int $sessionId, bool $allSessions): string
    {
        $scope = $allSessions ? 'all' : ($sessionId ?? 'none');

        return 'erp.fee.report.v'.self::version().'.'.$scope;
    }

    public static function notificationsKey(?int $sessionId, bool $allSessions): string
    {
        $scope = $allSessions ? 'all' : ($sessionId ?? 'none');

        return 'erp.header.notifications.v'.self::version().'.'.$scope;
    }
}
