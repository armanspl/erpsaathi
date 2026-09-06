<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class FeeCache
{
    public const LOOKUPS = 'erp.fee.lookups.v1';

    public const HEADS = 'erp.fee.heads.v1';

    public const FINE_RULES = 'erp.fee.fine_rules.v1';

    public const TTL = 60;

    public static function forget(): void
    {
        Cache::forget(self::LOOKUPS);
        Cache::forget(self::HEADS);
        Cache::forget(self::FINE_RULES);
    }
}
