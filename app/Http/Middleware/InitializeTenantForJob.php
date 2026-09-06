<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantManager;
use Closure;

/**
 * Re-bind tenant DB for queued jobs that carry a school_slug / school_id.
 */
class InitializeTenantForJob
{
    public function __construct(
        protected TenantManager $tenants
    ) {}

    public function handle($job, Closure $next)
    {
        $slug = null;
        if (isset($job->school_slug) && is_string($job->school_slug)) {
            $slug = $job->school_slug;
        } elseif (method_exists($job, 'tenantSlug')) {
            $slug = $job->tenantSlug();
        }

        if ($slug) {
            $school = $this->tenants->findBySlug($slug);
            if ($school && $school->isActive()) {
                $this->tenants->initialize($school);
            }
        } elseif (isset($job->school_id) && is_numeric($job->school_id)) {
            $school = \App\Models\Master\School::query()->find($job->school_id);
            if ($school && $school->isActive()) {
                $this->tenants->initialize($school);
            }
        }

        return $next($job);
    }
}
