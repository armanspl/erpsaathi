<?php

namespace App\Console\Commands;

use App\Models\Master\WhatsappPlatformSetting;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Server-side alternative to Super Admin → WhatsApp Configuration for the two webhook secrets.
 * Stores them encrypted in the master database (whatsapp_platform_settings), then calls the
 * public Callback URL the same way Meta's verification does.
 * - Verify token: generated here (64 hex chars, CSPRNG) unless one is set; shown once.
 * - App Secret: typed with hidden input; never echoed or logged.
 */
class WhatsAppConfigureWebhookCommand extends Command
{
    protected $signature = 'whatsapp:configure-webhook
        {--url= : Callback URL to self-test (default: the platform callback URL)}
        {--rotate : Generate a new verify token even if one is set (then update it in Meta too)}
        {--check : Only run the readiness check, change nothing}';

    protected $description = 'Store the WhatsApp webhook verify token + app secret (encrypted, master DB) and check the endpoint';

    public function handle(WhatsAppPlatformConfig $config): int
    {
        $url = $this->option('url') ?: $config->callbackUrl();

        if (! $this->option('check')) {
            $settings = WhatsappPlatformSetting::current();
            $token = $config->verifyToken();
            $newToken = ($token === '' || $this->option('rotate')) ? bin2hex(random_bytes(32)) : null;

            $typed = trim((string) $this->secret($config->appSecret() === ''
                ? 'Paste the Meta App Secret (App settings → Basic → App secret; input is hidden)'
                : 'Paste a new Meta App Secret, or press Enter to keep the current one (input is hidden)'));
            if ($typed === '' && $config->appSecret() === '') {
                $this->error('An App Secret is required. Nothing was changed.');

                return self::FAILURE;
            }
            if ($typed !== '' && ! preg_match('/^[A-Za-z0-9]{16,128}$/', $typed)) {
                $this->error('That does not look like a Meta App Secret (letters/digits only). Nothing was changed.');

                return self::FAILURE;
            }

            $changes = ['updated_by_email' => 'console: whatsapp:configure-webhook'];
            if ($newToken !== null) {
                $changes += ['webhook_verify_token' => $newToken, 'webhook_verify_token_updated_at' => now()];
            }
            if ($typed !== '') {
                $changes += ['app_secret' => $typed, 'app_secret_updated_at' => now()];
            }
            $settings->update($changes);
            WhatsAppPlatformConfig::flush();
            $this->info('Saved (encrypted) in the master database.');

            if ($newToken !== null) {
                $this->newLine();
                $this->warn('Verify token (paste into Meta → WhatsApp → Configuration → Verify token). Shown only now:');
                $this->line('  '.$newToken);
                $this->newLine();
            } else {
                $this->line('Verify token unchanged (already set). Use --rotate to replace it.');
            }
        }

        return $this->check($url, $config->verifyToken(), $config->appSecret()) ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $url, string $token, string $secret): bool
    {
        $this->line('Readiness check for '.$url);
        $ok = $this->report($token !== '', 'Verify token is set');
        $ok = $this->report($secret !== '', 'App secret is set') && $ok;
        $ok = $this->report(str_starts_with($url, 'https://'), 'Callback URL uses HTTPS') && $ok;
        if ($token === '') {
            return false;
        }

        $challenge = (string) random_int(100000000, 999999999);
        $body = '{"object":"whatsapp_business_account","entry":[]}';
        try {
            $res = Http::timeout(15)->get($url, ['hub.mode' => 'subscribe', 'hub.verify_token' => $token, 'hub.challenge' => $challenge]);
            $ok = $this->report($res->status() === 200 && trim($res->body()) === $challenge, 'GET verification answers with the challenge (HTTP '.$res->status().')') && $ok;
            $bad = Http::timeout(15)->get($url, ['hub.mode' => 'subscribe', 'hub.verify_token' => 'wrong', 'hub.challenge' => '1']);
            $ok = $this->report($bad->status() === 403, 'Wrong verify token is refused (HTTP '.$bad->status().')') && $ok;
            $unsigned = Http::timeout(15)->withBody($body, 'application/json')->post($url);
            $ok = $this->report($unsigned->status() === 401, 'Unsigned POST is refused (HTTP '.$unsigned->status().')') && $ok;
            $signed = Http::timeout(15)->withHeaders(['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)])
                ->withBody($body, 'application/json')->post($url);
            $ok = $this->report($signed->status() === 200, 'Correctly signed POST is accepted (HTTP '.$signed->status().')') && $ok;
        } catch (\Throwable $e) {
            $ok = $this->report(false, 'Callback URL reachable ('.class_basename($e).')');
        }

        $this->newLine();
        $ok ? $this->info('READY for Meta verification.') : $this->error('NOT ready — fix the items marked FAIL above.');

        return $ok;
    }

    private function report(bool $pass, string $label): bool
    {
        $this->line(($pass ? '  <info>PASS</info> ' : '  <error>FAIL</error> ').$label);

        return $pass;
    }
}
