<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict Super Admin routes to configured central hosts.
 */
class EnsureCentralDomain
{
    public function __construct(
        protected TenantManager $tenants
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenants->isCentralHost($request)) {
            abort(404);
        }

        $this->tenants->useCentral();

        return $next($request);
    }
}
