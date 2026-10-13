<?php

namespace App\Services\WhatsApp;

use App\Models\Master\School;
use App\Models\Master\WhatsappAccount;
use App\Services\Tenancy\TenantManager;

/**
 * Multi-school glue for WhatsApp onboarding: which school a request belongs to, booting that
 * school's database for the central Embedded Signup callback, and the master phone-number →
 * school routing row the webhook uses.
 */
class WhatsAppTenantBridge
{
    public function __construct(private TenantManager $tenants) {}

    public function currentSchoolId(): ?int
    {
        $school = app()->bound('tenant') ? app('tenant') : null;

        return $school?->id;
    }

    public function bootSchool(?int $schoolId): void
    {
        $school = $schoolId ? School::query()->find($schoolId) : null;
        if (! $school || ! in_array($school->status, ['active', 'provisioning'], true)) {
            throw new WhatsAppGraphException('This school is not available.');
        }
        $this->tenants->initialize($school);
    }

    public function assertPhoneAvailable(string $phoneNumberId, ?int $schoolId): void
    {
        $taken = WhatsappAccount::query()
            ->where('phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->where('school_id', '!=', $schoolId)
            ->exists();
        if ($taken) {
            throw new WhatsAppGraphException('This WhatsApp number is already connected to another school.');
        }
    }

    public function link(?int $schoolId, string $phoneNumberId, string $wabaId, ?string $displayPhone): void
    {
        // One active number per school: retire this school's older routes first.
        WhatsappAccount::query()->where('school_id', $schoolId)->where('phone_number_id', '!=', $phoneNumberId)->update(['is_active' => false]);
        WhatsappAccount::query()->updateOrCreate(
            ['phone_number_id' => $phoneNumberId],
            ['school_id' => $schoolId, 'waba_id' => $wabaId, 'display_phone_number' => $displayPhone, 'is_active' => true]
        );
    }

    public function unlink(?int $schoolId, string $phoneNumberId): void
    {
        WhatsappAccount::query()->where('school_id', $schoolId)->where('phone_number_id', $phoneNumberId)->update(['is_active' => false]);
    }
}
