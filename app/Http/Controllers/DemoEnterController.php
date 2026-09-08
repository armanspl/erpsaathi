<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\Master\School;
use App\Services\Tenancy\DemoSchoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DemoEnterController extends Controller
{
    public function __invoke(Request $request, DemoSchoolService $demo)
    {
        $slug = $demo->slug();
        $alreadyActive = School::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->exists();

        try {
            $school = $demo->ensureReady();
        } catch (Throwable $e) {
            report($e);

            return response()->view('errors.demo-unavailable', [
                'message' => 'Demo is temporarily unavailable. Please try again in a moment.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        // First provision ran under a public session. Bounce once so IdentifyTenant
        // boots the demo tenant (correct session cookie) before we log the user in.
        if (! $alreadyActive) {
            return redirect()->route('erp.demo');
        }

        $user = $demo->adminUser();
        if (! $user || ! $user->is_active) {
            return response()->view('errors.demo-unavailable', [
                'message' => 'Demo admin account is missing. Contact support.',
            ], 503);
        }

        Auth::guard('erp')->login($user, false);
        $request->session()->regenerate();

        LoginHistory::create([
            'erp_user_id' => $user->id,
            'email' => $user->email,
            'status' => 'Success',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent().' [try-demo]',
        ]);

        // Local IP hosts resolve tenant via ?school= / cookie — pin both so dashboard
        // does not pick a stale cookie and 404 "School not found".
        $target = route('erp.dashboard', absolute: false);
        $host = strtolower($request->getHost());
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $target .= (str_contains($target, '?') ? '&' : '?').'school='.urlencode($school->slug);
        }

        $response = redirect()->to($target);

        $cookieName = config('tenancy.cookie_name', 'tenant');
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

        return $response;
    }
}
