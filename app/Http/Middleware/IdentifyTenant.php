<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected TenantManager $tenants
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenants->isCentralHost($request) && $this->isSuperAdminPath($request)) {
            $this->tenants->useCentral();

            return $next($request);
        }

        // Public Try Demo entry.
        // If Demo school already exists, boot it here so the ERP session cookie matches login.
        // First-time provision still uses public mode; the controller then re-enters this route.
        if ($request->is('erp/demo')) {
            try {
                $demoSlug = (string) config('tenancy.demo.slug', 'demo');
                $demoSchool = $this->tenants->findBySlug($demoSlug);
                if ($demoSchool && $demoSchool->status === 'active') {
                    return $this->bootSchool($demoSchool, $next, $request);
                }
            } catch (\Throwable $e) {
                // Master DB not ready — fall through to public provision attempt.
            }

            $this->tenants->usePublic();

            return $next($request);
        }

        // Apex marketing site (erpsaathi.com / www) — no school tenant.
        if ($this->isPublicApexHost($request)) {
            // ERP lives only on school subdomains (e.g. bright.erpsaathi.com).
            if ($request->is('erp') || $request->is('erp/*')) {
                abort(404);
            }

            $this->tenants->usePublic();

            return $next($request);
        }

        // Local only (127.0.0.1 / localhost): serve the same marketing homepage as
        // production apex. Does not change erpsaathi.com or school subdomains.
        // ERP continues below via ?school= / cookie / first-school fallback.
        if ($this->isLocalHost($request) && $this->isLocalPublicPath($request)) {
            $this->tenants->usePublic();

            return $next($request);
        }

        $slug = $this->tenants->resolveSlugFromRequest($request);

        // Local / IP hosts without subdomain: fall back to first-school slug so
        // existing 127.0.0.1:8000 URLs keep working after bootstrap.
        if ($slug === null && $this->isLocalHost($request)) {
            $slug = config('tenancy.first_school_slug');
        }

        if ($slug === null) {
            try {
                $school = $this->tenants->findByDomain(strtolower($request->getHost()));
            } catch (\Throwable $e) {
                return $next($request);
            }
            if ($school) {
                return $this->bootSchool($school, $next, $request);
            }

            abort(404, 'School not found for this host.');
        }

        try {
            $school = $this->tenants->findBySlug($slug);
        } catch (\Throwable $e) {
            // Master DB not bootstrapped yet — allow request through (local first-run).
            return $next($request);
        }

        // Stale ?school= / tenant cookie on local → try demo / first-school before 404.
        if (! $school && $this->isLocalHost($request)) {
            foreach (array_unique(array_filter([
                config('tenancy.demo.slug'),
                config('tenancy.first_school_slug'),
            ])) as $fallback) {
                if ($fallback === $slug) {
                    continue;
                }
                try {
                    $school = $this->tenants->findBySlug($fallback);
                } catch (\Throwable $e) {
                    $school = null;
                }
                if ($school) {
                    break;
                }
            }
        }

        if (! $school) {
            abort(404, 'School not found.');
        }

        if ($school->status === 'inactive') {
            abort(503, 'This school is currently inactive.');
        }

        if ($school->status === 'failed') {
            abort(503, 'This school failed provisioning. Contact support.');
        }

        return $this->bootSchool($school, $next, $request);
    }

    /**
     * Public marketing hosts that must not resolve a school tenant.
     */
    protected function isPublicApexHost(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('tenancy.base_domain'));

        if ($base === '') {
            return false;
        }

        return $host === $base || $host === 'www.'.$base;
    }

    protected function bootSchool($school, Closure $next, Request $request): Response
    {
        try {
            $this->tenants->initialize($school);
        } catch (\Throwable $e) {
            abort(503, 'Unable to connect to school database.');
        }

        $response = $next($request);

        // Keep tenant sticky on local IP hosts when later requests omit ?school=
        $cookieName = config('tenancy.cookie_name', 'tenant');
        if ($request->cookie($cookieName) !== $school->slug) {
            $response->headers->setCookie(cookie(
                $cookieName,
                $school->slug,
                60 * 24 * 30,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        }

        return $response;
    }

    protected function isSuperAdminPath(Request $request): bool
    {
        return $request->is('super-admin') || $request->is('super-admin/*');
    }

    /**
     * Local paths that should behave like the production marketing apex.
     * ERP + Super Admin keep normal tenant/central resolution.
     */
    protected function isLocalPublicPath(Request $request): bool
    {
        if ($this->isSuperAdminPath($request)) {
            return false;
        }

        if ($request->is('erp') || $request->is('erp/*')) {
            return false;
        }

        return true;
    }

    protected function isLocalHost(Request $request): bool
    {
        $host = strtolower($request->getHost());

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
