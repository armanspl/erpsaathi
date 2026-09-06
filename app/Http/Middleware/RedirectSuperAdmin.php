<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('super_admin')->check()) {
            return redirect()->route('super-admin.login');
        }

        $user = Auth::guard('super_admin')->user();
        if ($user && isset($user->is_active) && ! $user->is_active) {
            Auth::guard('super_admin')->logout();

            return redirect()->route('super-admin.login')
                ->withErrors(['email' => 'This Super Admin account is inactive.']);
        }

        return $next($request);
    }
}
