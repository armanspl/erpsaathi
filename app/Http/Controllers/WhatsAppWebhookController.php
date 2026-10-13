<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppPlatformConfig;
use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meta WhatsApp Cloud API webhook.
 *
 * GET  — subscription handshake: echoes hub.challenge only when hub.verify_token matches
 *        WHATSAPP_WEBHOOK_VERIFY_TOKEN.
 * POST — event notifications: rejected unless X-Hub-Signature-256 is a valid HMAC-SHA256 of
 *        the raw body with WHATSAPP_APP_SECRET; then routed per phone number to the owning
 *        school and stored idempotently (see WhatsAppWebhookProcessor).
 *
 * Nothing secret or personal is ever written to the log — no tokens, signatures, payloads,
 * phone numbers or message text.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppPlatformConfig $config) {}

    public function verify(Request $request): Response
    {
        $expected = $this->config->verifyToken();
        // PHP turns "hub.mode" into "hub_mode" when parsing the query string.
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($expected === '' || $mode !== 'subscribe' || ! hash_equals($expected, $token) || $challenge === '' || strlen($challenge) > 512) {
            Log::warning('WhatsApp webhook verification rejected', ['configured' => $expected !== '']);

            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppWebhookProcessor $processor): Response
    {
        $secret = $this->config->appSecret();
        if ($secret === '') {
            Log::error('WhatsApp webhook: Meta App Secret is not configured; event refused');

            return response('Webhook not configured', 503);
        }

        $raw = $request->getContent();
        if (strlen($raw) > $this->config->maxPayloadBytes()) {
            return response('Payload too large', 413);
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);
        if ($header === '' || ! hash_equals($expected, $header)) {
            Log::warning('WhatsApp webhook: invalid or missing signature', ['present' => $header !== '']);

            return response('Invalid signature', 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('Invalid JSON', 400);
        }

        try {
            $summary = $processor->process($payload);
        } catch (\Throwable $e) {
            // 5xx makes Meta retry; processing is idempotent, so a retry is safe.
            Log::error('WhatsApp webhook processing failed', ['exception' => get_class($e)]);

            return response('Processing error', 500);
        }

        if ($summary['events'] > 0 || $summary['unrouted'] > 0) {
            Log::info('WhatsApp webhook processed', $summary);
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }
}
