<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Run ON THE SERVER to configure the Meta WhatsApp webhook secrets in .env:
 * - WHATSAPP_WEBHOOK_VERIFY_TOKEN: generated here (64 hex chars, CSPRNG); kept if already set
 *   unless --rotate. Shown once in this terminal so it can be pasted into Meta.
 * - WHATSAPP_APP_SECRET: typed in with hidden input (never echoed, never logged).
 * Then refreshes the config cache and calls the public Callback URL the same way Meta's
 * verification does, to prove the endpoint is reachable over HTTPS and answers correctly.
 */
class WhatsAppConfigureWebhookCommand extends Command
{
    protected $signature = 'whatsapp:configure-webhook
        {--url= : Public Callback URL to self-test (default: APP_URL + /webhooks/whatsapp)}
        {--rotate : Generate a new verify token even if one is already set (then update it in Meta too)}
        {--check : Only run the readiness check, change nothing}';

    protected $description = 'Set the WhatsApp webhook verify token + app secret in .env and check the endpoint';

    public function handle(): int
    {
        $envPath = app()->environmentFilePath();
        if (! is_file($envPath) || ! is_writable($envPath)) {
            $this->error('.env not found or not writable at '.$envPath);

            return self::FAILURE;
        }

        $env = (string) file_get_contents($envPath);
        $token = $this->envValue($env, 'WHATSAPP_WEBHOOK_VERIFY_TOKEN');
        $secret = $this->envValue($env, 'WHATSAPP_APP_SECRET');
        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/webhooks/whatsapp';

        if (! $this->option('check')) {
            $newToken = ($token === '' || $this->option('rotate')) ? bin2hex(random_bytes(32)) : null;

            $typed = (string) $this->secret($secret === ''
                ? 'Paste the Meta App Secret (App settings → Basic → App secret; input is hidden)'
                : 'Paste a new Meta App Secret, or press Enter to keep the current one (input is hidden)');
            $typed = trim($typed);
            if ($typed === '' && $secret === '') {
                $this->error('An App Secret is required. Nothing was changed.');

                return self::FAILURE;
            }
            if ($typed !== '' && ! preg_match('/^[A-Za-z0-9]{16,128}$/', $typed)) {
                $this->error('That does not look like a Meta App Secret (letters/digits only). Nothing was changed.');

                return self::FAILURE;
            }

            $updates = [];
            if ($newToken !== null) {
                $updates['WHATSAPP_WEBHOOK_VERIFY_TOKEN'] = $newToken;
                $token = $newToken;
            }
            if ($typed !== '') {
                $updates['WHATSAPP_APP_SECRET'] = $typed;
                $secret = $typed;
            }
            if ($updates !== []) {
                $this->writeEnv($envPath, $env, $updates);
                $this->info('.env updated: '.implode(', ', array_keys($updates)));
            }

            $cached = app()->configurationIsCached();
            Artisan::call('config:clear');
            if ($cached) {
                Artisan::call('config:cache');
            }
            $this->info($cached ? 'Configuration cache rebuilt.' : 'Configuration cache cleared.');

            if ($newToken !== null) {
                $this->newLine();
                $this->warn('Verify token (paste into Meta → WhatsApp → Configuration → Verify token).');
                $this->warn('It is shown only now; it is also stored in .env on this server:');
                $this->line('  '.$newToken);
                $this->newLine();
            } else {
                $this->line('Verify token unchanged (already set). Use --rotate to replace it.');
            }
        }

        return $this->check($url, $token, $secret) ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $url, string $token, string $secret): bool
    {
        $this->line('Readiness check for '.$url);
        $ok = true;

        $ok = $this->report($token !== '', 'Verify token is set in .env') && $ok;
        $ok = $this->report($secret !== '', 'App secret is set in .env') && $ok;
        $ok = $this->report(str_starts_with($url, 'https://'), 'Callback URL uses HTTPS') && $ok;
        if ($token === '') {
            return false;
        }

        $challenge = (string) random_int(100000000, 999999999);
        try {
            $res = Http::timeout(15)->get($url, [
                'hub.mode' => 'subscribe',
                'hub.verify_token' => $token,
                'hub.challenge' => $challenge,
            ]);
            $ok = $this->report($res->status() === 200 && trim($res->body()) === $challenge, 'GET verification answers with the challenge (HTTP '.$res->status().')') && $ok;

            $bad = Http::timeout(15)->get($url, ['hub.mode' => 'subscribe', 'hub.verify_token' => 'wrong', 'hub.challenge' => '1']);
            $ok = $this->report($bad->status() === 403, 'Wrong verify token is refused (HTTP '.$bad->status().')') && $ok;

            $unsigned = Http::timeout(15)->withBody('{"object":"whatsapp_business_account","entry":[]}', 'application/json')->post($url);
            $ok = $this->report($unsigned->status() === 401, 'Unsigned POST is refused (HTTP '.$unsigned->status().')') && $ok;

            $body = '{"object":"whatsapp_business_account","entry":[]}';
            $signed = Http::timeout(15)
                ->withHeaders(['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)])
                ->withBody($body, 'application/json')->post($url);
            $ok = $this->report($signed->status() === 200, 'Correctly signed POST is accepted (HTTP '.$signed->status().')') && $ok;
        } catch (\Throwable $e) {
            $ok = $this->report(false, 'Callback URL reachable ('.class_basename($e).': '.mb_substr($e->getMessage(), 0, 120).')');
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

    private function envValue(string $env, string $key): string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $env, $m)) {
            return '';
        }

        return trim(trim($m[1]), "\"'");
    }

    /** Replace or append keys, writing atomically and keeping the file's permissions. */
    private function writeEnv(string $path, string $env, array $updates): void
    {
        $eol = str_contains($env, "\r\n") ? "\r\n" : "\n";
        foreach ($updates as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $env = preg_match($pattern, $env)
                ? preg_replace($pattern, $line, $env)
                : rtrim($env, "\r\n").$eol.$line.$eol;
        }

        $tmp = $path.'.tmp-'.bin2hex(random_bytes(4));
        file_put_contents($tmp, $env, LOCK_EX);
        @chmod($tmp, fileperms($path) & 0777);
        rename($tmp, $path);
    }
}
