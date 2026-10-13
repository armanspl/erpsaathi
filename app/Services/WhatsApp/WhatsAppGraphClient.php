<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Meta Graph API client for WhatsApp Cloud API onboarding.
 *
 * The app secret and access tokens travel in POST bodies or Authorization headers. The one
 * exception is debug_token's input_token, which Meta only accepts as a query parameter —
 * so transport errors (whose text quotes the request URL) are swallowed here and never
 * rethrown or logged. Failures surface as WhatsAppGraphException carrying only Meta's own
 * error code/message, never the request.
 */
class WhatsAppGraphClient
{
    public function __construct(private WhatsAppPlatformConfig $config) {}

    private function base(?string $version = null): string
    {
        return 'https://graph.facebook.com/'.($version ?: $this->config->graphVersion());
    }

    /**
     * Proves the App ID + App Secret pair (and Graph version) are valid: reads the app itself
     * with an app access token. Returns ['id' => …, 'name' => …].
     */
    public function appInfo(string $appId, string $appSecret, string $version): array
    {
        return $this->send(fn () => Http::withToken($appId.'|'.$appSecret)->timeout(20)
            ->get($this->base($version).'/'.rawurlencode($appId), ['fields' => 'id,name']), 'app check');
    }

    /** Exchange the Embedded Signup authorization code for a business access token. */
    public function exchangeCode(string $code): array
    {
        $data = $this->send(fn () => Http::asForm()->timeout(20)->post($this->base().'/oauth/access_token', [
            'client_id' => $this->config->appId(),
            'client_secret' => $this->config->appSecret(),
            'code' => $code,
        ]), 'code exchange');

        if (empty($data['access_token'])) {
            throw new WhatsAppGraphException('Meta did not return an access token.');
        }

        return $data;
    }

    /** What the token can access: is_valid, app_id, expires_at, granular_scopes[{scope, target_ids}]. */
    public function debugToken(string $token): array
    {
        $appToken = $this->config->appId().'|'.$this->config->appSecret();

        return (array) ($this->send(fn () => Http::withToken($appToken)->timeout(20)
            ->get($this->base().'/debug_token', ['input_token' => $token]), 'token check')['data'] ?? []);
    }

    public function phoneNumber(string $token, string $phoneNumberId): array
    {
        return $this->send(fn () => Http::withToken($token)->timeout(20)->get($this->base().'/'.rawurlencode($phoneNumberId), [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,platform_type,code_verification_status,name_status',
        ]), 'phone number lookup');
    }

    /** @return list<string> phone number IDs under the WABA */
    public function wabaPhoneNumberIds(string $token, string $wabaId): array
    {
        $data = $this->send(fn () => Http::withToken($token)->timeout(20)
            ->get($this->base().'/'.rawurlencode($wabaId).'/phone_numbers', ['fields' => 'id', 'limit' => 100]), 'phone number list');

        return array_values(array_map(fn ($p) => (string) ($p['id'] ?? ''), (array) ($data['data'] ?? [])));
    }

    public function waba(string $token, string $wabaId): array
    {
        return $this->send(fn () => Http::withToken($token)->timeout(20)
            ->get($this->base().'/'.rawurlencode($wabaId), ['fields' => 'id,name,owner_business_info']), 'account lookup');
    }

    /** Subscribe our app to the school's WABA so its webhooks reach /webhooks/whatsapp. */
    public function subscribeApp(string $token, string $wabaId): void
    {
        $this->send(fn () => Http::withToken($token)->timeout(20)->post($this->base().'/'.rawurlencode($wabaId).'/subscribed_apps'), 'webhook subscription');
    }

    public function unsubscribeApp(string $token, string $wabaId): void
    {
        $this->send(fn () => Http::withToken($token)->timeout(20)->delete($this->base().'/'.rawurlencode($wabaId).'/subscribed_apps'), 'webhook unsubscription');
    }

    /** Register the number on Cloud API (needed once when platform_type is not CLOUD_API). */
    public function registerPhone(string $token, string $phoneNumberId, string $pin): void
    {
        $this->send(fn () => Http::withToken($token)->timeout(20)->post($this->base().'/'.rawurlencode($phoneNumberId).'/register', [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]), 'number registration');
    }

    /** @param  callable(): Response  $request */
    private function send(callable $request, string $what): array
    {
        try {
            $response = $request();
        } catch (\Throwable) {
            // Transport errors can quote the URL — never rethrow or log the original.
            throw new WhatsAppGraphException("Could not reach Meta ({$what}). Please try again.");
        }

        $json = (array) $response->json();
        if (! $response->successful() || isset($json['error'])) {
            $err = (array) ($json['error'] ?? []);
            $msg = (string) ($err['error_user_msg'] ?? $err['message'] ?? 'Unknown error');
            throw new WhatsAppGraphException(
                "Meta rejected the {$what}: ".mb_substr($msg, 0, 300),
                isset($err['code']) ? (int) $err['code'] : $response->status()
            );
        }

        return $json;
    }
}
