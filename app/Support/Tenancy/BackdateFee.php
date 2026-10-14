<?php

namespace App\Support\Tenancy;

/**
 * Whether the current school may enter back-dated fee receipts (Fee Receipt → Back-date entry:
 * past payment date, own receipt no., chosen session). Switched per school in Super Admin.
 */
class BackdateFee
{
    public static function allowed(): bool
    {
        $school = app(TenantContext::class)->get();

        // No tenant resolved (single-school / local setup without the master registry):
        // there is no Super Admin to ask, so behave as before — allowed.
        return $school === null || $school->allowsBackdateFee();
    }
}
