<?php

namespace App\Jobs\Concerns;

use App\Support\Tenancy\TenantContext;

/**
 * Attach the current school slug to a queued job so InitializeTenantForJob
 * can re-bind the tenant DB connection before handle().
 */
trait BelongsToTenant
{
    public ?string $school_slug = null;

    public function initializeTenantPayload(): void
    {
        if ($this->school_slug) {
            return;
        }

        $tenant = app(TenantContext::class)->get();
        if ($tenant) {
            $this->school_slug = $tenant->slug;
        }
    }

    public function tenantSlug(): ?string
    {
        return $this->school_slug;
    }
}
