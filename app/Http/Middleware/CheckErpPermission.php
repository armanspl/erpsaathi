<?php

namespace App\Http\Middleware;

use App\Services\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckErpPermission
{
    public function __construct(private PermissionResolver $resolver) {}

    /**
     * Accept one or more permission keys (OR). Pipe-separated values also work:
     * erp.permission:people.staff.create
     * erp.permission:fee.manage|fee-management.pay-fee.create
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::guard('erp')->user();
        $candidates = [];
        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        }

        foreach ($candidates as $permission) {
            if ($this->resolver->allows($user, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
        ], 403);
    }
}
