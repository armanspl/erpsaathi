<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PeopleCache
{
    public const LOOKUPS = 'erp.people.lookups.v1';

    public const PARENTS_LITE = 'erp.people.parents.lite.v1';

    public const TEACHERS = 'erp.people.teachers.v1';

    public const STAFF = 'erp.people.staff.v1';

    public const DRIVERS = 'erp.people.drivers.v1';

    public const TTL = 45;

    public static function forget(): void
    {
        Cache::forget(self::LOOKUPS);
        Cache::forget(self::PARENTS_LITE);
        Cache::forget(self::TEACHERS);
        Cache::forget(self::STAFF);
        Cache::forget(self::DRIVERS);
    }
}
