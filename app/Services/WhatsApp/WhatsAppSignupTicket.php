<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived, signed, single-use ticket that carries "which school + which ERP user + which
 * ERP origin" from a school's ERP to the central Embedded Signup page and back to the server.
 * HMAC-SHA256 with APP_KEY; the nonce is burned on first successful use.
 */
class WhatsAppSignupTicket
{
    public static function issue(?int $schoolId, int $userId, string $origin): string
    {
        $payload = [
            's' => $schoolId,
            'u' => $userId,
            'o' => $origin,
            'n' => Str::random(32),
            'e' => now()->addMinutes(app(WhatsAppPlatformConfig::class)->ticketTtlMinutes())->timestamp,
        ];
        $body = self::b64(json_encode($payload));

        return $body.'.'.self::sign($body);
    }

    /** Valid, unexpired and unused — or null. Does not burn it. */
    public static function peek(?string $ticket): ?array
    {
        if (! is_string($ticket) || substr_count($ticket, '.') !== 1 || strlen($ticket) > 2048) {
            return null;
        }
        [$body, $sig] = explode('.', $ticket);
        if (! hash_equals(self::sign($body), $sig)) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);
        if (! is_array($payload) || ($payload['e'] ?? 0) < time() || empty($payload['n'])) {
            return null;
        }
        if (Cache::store('file')->has(self::nonceKey($payload['n']))) {
            return null;
        }

        return $payload;
    }

    /** Validate and burn. Second use of the same ticket returns null. */
    public static function consume(?string $ticket): ?array
    {
        $payload = self::peek($ticket);
        if (! $payload) {
            return null;
        }
        $ttl = max(60, $payload['e'] - time() + 60);

        return Cache::store('file')->add(self::nonceKey($payload['n']), 1, $ttl) ? $payload : null;
    }

    private static function nonceKey(string $nonce): string
    {
        return 'wa-signup-ticket:'.hash('sha256', $nonce);
    }

    private static function sign(string $body): string
    {
        return self::b64(hash_hmac('sha256', 'whatsapp-signup|'.$body, (string) config('app.key'), true));
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
