<?php

namespace App\Services\WhatsApp;

use App\Models\Master\WhatsappAccount;
use App\Services\Tenancy\TenantManager;

/**
 * Multi-school: maps a webhook's phone_number_id to the owning school's database via the
 * master `whatsapp_accounts` table, on a dedicated per-school connection (never the request's
 * default connection, so one payload carrying several numbers can't cross schools).
 */
class WhatsAppAccountResolver
{
    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(private TenantManager $tenants) {}

    public function connectionFor(string $phoneNumberId, ?string $wabaId): ?string
    {
        if (array_key_exists($phoneNumberId, $this->cache)) {
            return $this->cache[$phoneNumberId];
        }

        $account = WhatsappAccount::query()
            ->with('school')
            ->where('phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->first();

        $school = $account?->school;
        $wabaMismatch = $account && $account->waba_id && $wabaId && $account->waba_id !== $wabaId;
        if (! $school || $wabaMismatch || ! in_array($school->status, ['active', 'provisioning'], true)) {
            return $this->cache[$phoneNumberId] = null;
        }

        $name = 'whatsapp_school_'.$school->id;
        $this->tenants->configureTemporaryConnection($name, $school->db_name, $school->db_host);

        return $this->cache[$phoneNumberId] = $name;
    }
}
