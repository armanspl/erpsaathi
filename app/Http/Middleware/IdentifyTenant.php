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

    protected function isLocalHost(Request $request): bool
    {
        $host = strtolower($request->getHost());

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
