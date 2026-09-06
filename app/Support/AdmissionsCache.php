<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AdmissionsCache
{
    public const CUSTOM_FIELDS = 'erp.admissions.custom_fields.v1';

    public const CUSTOM_FIELDS_ACTIVE = 'erp.admissions.custom_fields.active.v1';

    public const PIPELINE_COUNTS = 'erp.admissions.pipeline_counts.v1';

    public const TTL = 45;

    public static function forget(): void
    {
        Cache::forget(self::CUSTOM_FIELDS);
        Cache::forget(self::CUSTOM_FIELDS_ACTIVE);
        Cache::forget(self::PIPELINE_COUNTS);
    }
}
