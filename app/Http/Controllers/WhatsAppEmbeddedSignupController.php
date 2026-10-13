<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppGraphException;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use App\Services\WhatsApp\WhatsAppSignupTicket;
use App\Services\WhatsApp\WhatsAppTenantBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The single central page where Meta's JavaScript SDK runs Embedded Signup for every school
 * (WHATSAPP_SIGNUP_URL/whatsapp/embedded-signup). A school's ERP opens it in a popup with a
 * signed one-time ticket; the ticket — not a session or the host — says which school the
 * WhatsApp account is being connected for.
 */
class WhatsAppEmbeddedSignupController extends Controller
{
    public function __construct(private WhatsAppPlatformConfig $config) {}

    public function page(Request $request)
    {
        $ticket = (string) $request->query('ticket', '');
        $payload = WhatsAppSignupTicket::peek($ticket);
        $ready = $this->config->onboardingReady();

        // Only public identifiers reach the page (App ID, Configuration ID, Graph version) —
        // the App Secret stays server-side.
        return response()
            ->view('whatsapp.embedded-signup', [
                'valid' => (bool) $payload,
                'ticket' => $payload ? $ticket : '',
                'openerOrigin' => $payload['o'] ?? '',
                'appId' => $ready ? $this->config->appId() : '',
                'configId' => $ready ? $this->config->configId() : '',
                'graphVersion' => $this->config->graphVersion(),
                'completeUrl' => $this->config->signupUrl().'/complete',
            ])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY');
    }

    public function complete(Request $request, WhatsAppOnboardingService $onboarding, WhatsAppTenantBridge $bridge)
    {
        $data = $request->validate([
            'ticket' => 'required|string|max:2048',
            'code' => 'required|string|max:2048',
            'waba_id' => ['nullable', 'regex:/^\d{5,64}$/'],
            'phone_number_id' => ['nullable', 'regex:/^\d{5,64}$/'],
        ]);

        if (! $this->config->onboardingReady()) {
            return response()->json(['message' => 'WhatsApp onboarding is not available right now: '.implode(' ', $this->config->onboardingProblems())], 503);
        }

        $ticket = WhatsAppSignupTicket::consume($data['ticket']);
        if (! $ticket) {
            return response()->json(['message' => 'This connect link has expired or was already used. Close this window and click Connect again.'], 403);
        }

        try {
            $bridge->bootSchool($ticket['s'] ?? null);
            $connection = $onboarding->complete($ticket['s'] ?? null, (int) $ticket['u'], $data['code'], $data['waba_id'] ?? null, $data['phone_number_id'] ?? null);
        } catch (WhatsAppGraphException $e) {
            Log::warning('WhatsApp Embedded Signup failed', ['school_id' => $ticket['s'] ?? null, 'code' => $e->getCode()]);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Embedded Signup error', ['school_id' => $ticket['s'] ?? null, 'exception' => get_class($e)]);

            return response()->json(['message' => 'Something went wrong while connecting. Please try again.'], 500);
        }

        Log::info('WhatsApp Embedded Signup completed', ['school_id' => $ticket['s'] ?? null]);

        return response()->json([
            'success' => true,
            'display_phone_number' => $connection->display_phone_number,
            'verified_name' => $connection->verified_name,
        ]);
    }
}
