<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for Academics taxonomy reads (branches / classes / sections / subjects).
 * Cleared on any write so dropdowns stay correct without waiting for TTL.
 */
class AcademicsCache
{
    public const LOOKUPS = 'erp.academics.lookups.v1';

    public const CLASSES_COMPACT = 'erp.academics.classes.compact.v1';

    public const CLASSES_FULL = 'erp.academics.classes.full.v1';

    public const BRANCHES = 'erp.academics.branches.v1';

    public const SECTIONS = 'erp.academics.sections.v1';

    public const SUBJECTS = 'erp.academics.subjects.v1';

    public const TTL = 60;

    public static function forget(): void
    {
        Cache::forget(self::LOOKUPS);
        Cache::forget(self::CLASSES_COMPACT);
        Cache::forget(self::CLASSES_FULL);
        Cache::forget(self::BRANCHES);
        Cache::forget(self::SECTIONS);
        Cache::forget(self::SUBJECTS);
    }
}
