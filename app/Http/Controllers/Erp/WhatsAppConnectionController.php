<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Services\WhatsApp\WhatsAppGraphException;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use App\Services\WhatsApp\WhatsAppSignupTicket;
use App\Services\WhatsApp\WhatsAppTenantBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Settings → WhatsApp: this school's own WhatsApp Business connection (no tokens ever returned). */
class WhatsAppConnectionController extends Controller
{
    public function __construct(
        private WhatsAppOnboardingService $onboarding,
        private WhatsAppTenantBridge $bridge,
        private WhatsAppPlatformConfig $config,
    ) {}

    public function show()
    {
        $connection = WhatsappConnection::active();

        return response()->json([
            // Schools only learn whether onboarding is available — the platform settings
            // themselves are Super Admin only.
            'configured' => $this->config->onboardingReady(),
            'connection' => $connection?->toPublicArray(),
        ]);
    }

    /** URL of the central Embedded Signup page for a popup, with a signed one-time ticket. */
    public function start(Request $request)
    {
        abort_unless($this->config->onboardingReady(), 422, 'WhatsApp connection is not available yet. Please contact ERPSaathi support.');

        $ticket = WhatsAppSignupTicket::issue(
            $this->bridge->currentSchoolId(),
            (int) Auth::guard('erp')->id(),
            $request->getSchemeAndHttpHost()
        );

        return response()->json([
            'url' => $this->config->signupUrl().'?ticket='.urlencode($ticket),
            'origin' => $this->origin($this->config->signupUrl()),
        ]);
    }

    public function refresh()
    {
        $connection = WhatsappConnection::active();
        abort_unless($connection, 404, 'No WhatsApp number is connected.');

        return $this->guard(fn () => $this->onboarding->refresh($connection)->toPublicArray());
    }

    public function register(Request $request)
    {
        $data = $request->validate(['pin' => ['required', 'regex:/^\d{6}$/']]);
        $connection = WhatsappConnection::active();
        abort_unless($connection, 404, 'No WhatsApp number is connected.');

        return $this->guard(fn () => $this->onboarding->register($connection, $data['pin'])->toPublicArray());
    }

    public function disconnect()
    {
        $connection = WhatsappConnection::active();
        abort_unless($connection, 404, 'No WhatsApp number is connected.');
        $this->onboarding->disconnect($this->bridge->currentSchoolId(), $connection);

        return response()->json(['success' => true]);
    }

    private function guard(callable $fn)
    {
        try {
            return response()->json(['connection' => $fn()]);
        } catch (WhatsAppGraphException $e) {
            WhatsappConnection::active()?->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
    }
}
