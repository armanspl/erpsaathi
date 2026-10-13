<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Master\WhatsappAccount;
use App\Models\Master\WhatsappPlatformSetting;
use App\Services\WhatsApp\WhatsAppGraphClient;
use App\Services\WhatsApp\WhatsAppGraphException;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin → WhatsApp Configuration: the platform-wide Meta app settings every school's
 * Embedded Signup, Graph calls and the webhook read (master DB, secrets encrypted).
 * Secrets are write-only: responses only say whether they are set and when they changed.
 */
class WhatsAppConfigController extends Controller
{
    public function __construct(private WhatsAppPlatformConfig $config) {}

    public function show()
    {
        return response()->json($this->present());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'embedded_signup_enabled' => 'required|boolean',
            'app_id' => ['nullable', 'regex:/^\d{10,20}$/'],
            'embedded_signup_config_id' => ['nullable', 'regex:/^\d{10,20}$/'],
            'graph_version' => ['required', 'regex:/^v\d{1,2}\.\d{1,2}$/'],
            'signup_ticket_ttl' => 'required|integer|min:5|max:60',
            'app_secret' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{16,128}$/'],
            'clear_app_secret' => 'sometimes|boolean',
            'webhook_verify_token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._~-]{16,255}$/'],
            'clear_webhook_verify_token' => 'sometimes|boolean',
        ], [
            'app_id.regex' => 'Meta App ID must be the numeric ID from App settings → Basic (10–20 digits).',
            'embedded_signup_config_id.regex' => 'Configuration ID must be the numeric ID from Facebook Login for Business → Configurations (10–20 digits).',
            'graph_version.regex' => 'Graph API version must look like v23.0.',
            'app_secret.regex' => 'Meta App Secret must be letters and digits only (Meta shows 32 characters).',
            'webhook_verify_token.regex' => 'Verify token must be 16–255 characters: letters, digits, dot, dash, underscore or tilde.',
        ]);

        $settings = WhatsappPlatformSetting::current();
        $admin = Auth::guard('super_admin')->user();
        $changes = [
            'embedded_signup_enabled' => (bool) $data['embedded_signup_enabled'],
            'app_id' => $data['app_id'] ?? null,
            'embedded_signup_config_id' => $data['embedded_signup_config_id'] ?? null,
            'graph_version' => $data['graph_version'],
            'signup_ticket_ttl' => (int) $data['signup_ticket_ttl'],
            'updated_by_id' => $admin?->id,
            'updated_by_email' => $admin?->email,
        ];
        // Secrets are write-only: blank = keep the current value; "clear" removes it.
        if (! empty($data['app_secret'])) {
            $changes += ['app_secret' => $data['app_secret'], 'app_secret_updated_at' => now()];
        } elseif (! empty($data['clear_app_secret'])) {
            $changes += ['app_secret' => null, 'app_secret_updated_at' => now()];
        }
        if (! empty($data['webhook_verify_token'])) {
            $changes += ['webhook_verify_token' => $data['webhook_verify_token'], 'webhook_verify_token_updated_at' => now()];
        } elseif (! empty($data['clear_webhook_verify_token'])) {
            $changes += ['webhook_verify_token' => null, 'webhook_verify_token_updated_at' => now()];
        }

        $before = collect(['embedded_signup_enabled', 'app_id', 'embedded_signup_config_id', 'graph_version', 'signup_ticket_ttl'])
            ->mapWithKeys(fn ($k) => [$k => $settings->{$k}]);
        $settings->update($changes);
        WhatsAppPlatformConfig::flush();

        $changed = $before->filter(fn ($v, $k) => $v != $settings->{$k})->keys()
            ->merge(isset($changes['app_secret_updated_at']) ? ['app_secret'] : [])
            ->merge(isset($changes['webhook_verify_token_updated_at']) ? ['webhook_verify_token'] : []);
        // Field names only — never values.
        Log::info('WhatsApp platform configuration updated', ['by' => $admin?->email, 'fields' => $changed->values()->all()]);

        return response()->json(['message' => 'WhatsApp configuration saved.'] + $this->present());
    }

    /** Live checks: required values, App ID + Secret + version against Meta, the webhook endpoint. */
    public function check(WhatsAppGraphClient $graph)
    {
        $results = [];
        $add = function (string $label, string $status, string $message) use (&$results) {
            $results[] = compact('label', 'status', 'message');
        };

        $appId = $this->config->appId();
        $secret = $this->config->appSecret();
        $version = $this->config->graphVersion();

        $add('Embedded Signup', $this->config->signupEnabled() ? 'ok' : 'warn', $this->config->signupEnabled() ? 'Switched on.' : 'Switched off — schools cannot connect.');
        $add('Meta App ID', $appId !== '' ? 'ok' : 'fail', $appId !== '' ? 'Set ('.$this->config->source('app_id', 'whatsapp.app_id').').' : 'Not set.');
        $add('Configuration ID', $this->config->configId() !== '' ? 'ok' : 'fail', $this->config->configId() !== '' ? 'Set ('.$this->config->source('embedded_signup_config_id', 'whatsapp.embedded_signup.config_id').').' : 'Not set — create it under Facebook Login for Business → Configurations.');
        $add('App Secret', $secret !== '' ? 'ok' : 'fail', $secret !== '' ? 'Set ('.$this->config->source('app_secret', 'whatsapp.webhook.app_secret').').' : 'Not set — webhooks are refused and schools cannot connect.');
        $add('Webhook verify token', $this->config->verifyToken() !== '' ? 'ok' : 'fail', $this->config->verifyToken() !== '' ? 'Set ('.$this->config->source('webhook_verify_token', 'whatsapp.webhook.verify_token').').' : 'Not set — Meta cannot verify the Callback URL.');

        if ($appId !== '' && $secret !== '') {
            try {
                $app = $graph->appInfo($appId, $secret, $version);
                $add('Meta app + secret + '.$version, 'ok', 'Meta accepted them: app "'.($app['name'] ?? '?').'" ('.($app['id'] ?? $appId).').');
            } catch (WhatsAppGraphException $e) {
                $add('Meta app + secret + '.$version, 'fail', $e->getMessage());
            }
        } else {
            $add('Meta app + secret + '.$version, 'fail', 'Skipped — App ID and App Secret are both needed.');
        }

        $callback = $this->config->callbackUrl();
        if ($this->config->verifyToken() !== '') {
            try {
                $challenge = (string) random_int(100000000, 999999999);
                $res = Http::timeout(10)->get($callback, ['hub.mode' => 'subscribe', 'hub.verify_token' => $this->config->verifyToken(), 'hub.challenge' => $challenge]);
                $pass = $res->status() === 200 && trim($res->body()) === $challenge;
                $add('Webhook endpoint', $pass ? 'ok' : 'fail', $pass ? 'Answers Meta\'s verification at '.$callback.'.' : 'Did not answer the verification correctly (HTTP '.$res->status().') at '.$callback.'.');
            } catch (\Throwable) {
                $add('Webhook endpoint', 'warn', 'Could not reach '.$callback.' from this server.');
            }
        }
        $add('HTTPS', str_starts_with($callback, 'https://') ? 'ok' : 'warn', str_starts_with($callback, 'https://') ? 'Callback and signup URLs use HTTPS.' : 'Meta requires HTTPS for the Callback URL and the signup page.');

        $ok = collect($results)->doesntContain('status', 'fail');
        WhatsappPlatformSetting::current()->update(['last_check' => ['at' => now()->toIso8601String(), 'ok' => $ok, 'results' => $results]]);

        return response()->json(['ok' => $ok, 'results' => $results]);
    }

    private function present(): array
    {
        $s = WhatsappPlatformSetting::current();
        $signup = $this->config->signupUrl();
        $p = parse_url($signup);
        $origin = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');

        return [
            'settings' => [
                'embedded_signup_enabled' => (bool) $s->embedded_signup_enabled,
                'app_id' => $s->app_id,
                'embedded_signup_config_id' => $s->embedded_signup_config_id,
                'graph_version' => $s->graph_version ?: 'v23.0',
                'signup_ticket_ttl' => (int) ($s->signup_ticket_ttl ?: 15),
                'app_secret_set' => $this->config->appSecret() !== '',
                'app_secret_updated_at' => $s->app_secret_updated_at?->toIso8601String(),
                'webhook_verify_token_set' => $this->config->verifyToken() !== '',
                'webhook_verify_token_updated_at' => $s->webhook_verify_token_updated_at?->toIso8601String(),
                'updated_by_email' => $s->updated_by_email,
                'updated_at' => $s->updated_at?->toIso8601String(),
                'last_check' => $s->last_check,
            ],
            'sources' => [
                'app_id' => $this->config->source('app_id', 'whatsapp.app_id'),
                'embedded_signup_config_id' => $this->config->source('embedded_signup_config_id', 'whatsapp.embedded_signup.config_id'),
                'graph_version' => $this->config->source('graph_version', 'whatsapp.graph_version'),
                'app_secret' => $this->config->source('app_secret', 'whatsapp.webhook.app_secret'),
                'webhook_verify_token' => $this->config->source('webhook_verify_token', 'whatsapp.webhook.verify_token'),
            ],
            'meta_values' => [
                'callback_url' => $this->config->callbackUrl(),
                'signup_page_url' => $signup,
                'oauth_redirect_uri' => $signup,
                'allowed_domain' => $origin,
            ],
            'problems' => $this->config->onboardingProblems(),
            'connected_schools' => WhatsappAccount::query()->where('is_active', true)->count(),
        ];
    }
}
