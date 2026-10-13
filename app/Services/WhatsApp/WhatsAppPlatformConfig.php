<?php

namespace App\Services\WhatsApp;

use App\Models\Master\WhatsappPlatformSetting;
use Illuminate\Support\Facades\Schema;

/**
 * The single place WhatsApp/Meta platform settings are read from.
 *
 * Source order per setting: Super Admin → WhatsApp Configuration (master DB; secrets encrypted
 * with APP_KEY) first, then the legacy WHATSAPP_* .env values as a fallback so an existing
 * install keeps working until Super Admin saves its own values. Secrets are only ever returned
 * to server code — never to a browser (see SuperAdmin\WhatsAppConfigController::present()).
 */
class WhatsAppPlatformConfig
{
    /** Re-read the row at most this often in long-running workers. */
    private const MEMO_SECONDS = 30;

    private static ?WhatsappPlatformSetting $row = null;

    private static int $loadedAt = 0;

    public static function flush(): void
    {
        self::$row = null;
        self::$loadedAt = 0;
    }

    public function row(): ?WhatsappPlatformSetting
    {
        if (self::$loadedAt > 0 && time() - self::$loadedAt < self::MEMO_SECONDS) {
            return self::$row;
        }
        try {
            self::$row = Schema::connection('master')->hasTable('whatsapp_platform_settings')
                ? WhatsappPlatformSetting::query()->orderBy('id')->first()
                : null;
        } catch (\Throwable) {
            self::$row = null; // master DB unreachable → .env fallback only
        }
        self::$loadedAt = time();

        return self::$row;
    }

    public function appId(): string
    {
        return $this->pick('app_id', 'whatsapp.app_id');
    }

    public function appSecret(): string
    {
        return $this->pick('app_secret', 'whatsapp.webhook.app_secret');
    }

    public function verifyToken(): string
    {
        return $this->pick('webhook_verify_token', 'whatsapp.webhook.verify_token');
    }

    public function configId(): string
    {
        return $this->pick('embedded_signup_config_id', 'whatsapp.embedded_signup.config_id');
    }

    public function graphVersion(): string
    {
        return $this->pick('graph_version', 'whatsapp.graph_version') ?: 'v23.0';
    }

    public function signupEnabled(): bool
    {
        $row = $this->row();

        return $row ? (bool) $row->embedded_signup_enabled : true;
    }

    public function ticketTtlMinutes(): int
    {
        $row = $this->row();
        $ttl = $row?->signup_ticket_ttl ?: (int) config('whatsapp.embedded_signup.ticket_ttl', 15);

        return max(1, min(60, (int) $ttl));
    }

    /** Fixed by TENANCY_BASE_DOMAIN / WHATSAPP_SIGNUP_URL (deployment), not editable at runtime. */
    public function signupUrl(): string
    {
        return (string) config('whatsapp.embedded_signup.url');
    }

    public function callbackUrl(): string
    {
        $p = parse_url($this->signupUrl());

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '').'/webhooks/whatsapp';
    }

    public function maxPayloadBytes(): int
    {
        return (int) config('whatsapp.webhook.max_payload_bytes', 1048576);
    }

    /** 'database' | 'env' | 'missing' — where a setting's current value comes from. */
    public function source(string $column, string $configKey): string
    {
        $row = $this->row();
        if ($row && filled($this->rowValue($row, $column))) {
            return 'database';
        }

        return filled(config($configKey)) ? 'env' : 'missing';
    }

    /** @return list<string> human-readable reasons Embedded Signup can't run right now */
    public function onboardingProblems(): array
    {
        $problems = [];
        if (! $this->signupEnabled()) {
            $problems[] = 'WhatsApp Embedded Signup is switched off in Super Admin.';
        }
        if ($this->appId() === '') {
            $problems[] = 'Meta App ID is not set.';
        }
        if ($this->configId() === '') {
            $problems[] = 'Facebook Login for Business Configuration ID is not set.';
        }
        if ($this->appSecret() === '') {
            $problems[] = 'Meta App Secret is not set.';
        }

        return $problems;
    }

    public function onboardingReady(): bool
    {
        return $this->onboardingProblems() === [];
    }

    private function pick(string $column, string $configKey): string
    {
        $row = $this->row();
        $value = $row ? $this->rowValue($row, $column) : null;

        return filled($value) ? trim((string) $value) : trim((string) config($configKey, ''));
    }

    private function rowValue(WhatsappPlatformSetting $row, string $column): mixed
    {
        try {
            return $row->{$column};
        } catch (\Throwable) {
            return null; // undecryptable (APP_KEY changed) → treat as unset
        }
    }
}
