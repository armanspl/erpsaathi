<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Services\StudentPortalCatalog;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Server-side enforcement of the admin "Student Portal" visibility toggles (Settings →
 * Student Portal) — every Student\* controller action must call this before doing any
 * work, so disabling a module in admin settings actually blocks the API, not just hides
 * the nav item client-side.
 */
trait EnforcesPortalVisibility
{
    protected function abortIfModuleDisabled(string $key): void
    {
        if (! StudentPortalCatalog::isEnabled($key)) {
            throw new HttpException(403, 'This section has been disabled by the school.');
        }
    }
}
