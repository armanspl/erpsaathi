<?php

namespace App\Console\Commands;

use App\Models\Master\School;
use App\Models\Master\WhatsappAccount;
use Illuminate\Console\Command;

/**
 * Link a school to its WhatsApp Business phone number so webhook events for that number are
 * stored in that school's database. Both IDs are on Meta App Dashboard → WhatsApp → API Setup.
 */
class WhatsAppLinkAccountCommand extends Command
{
    protected $signature = 'whatsapp:link-account
        {school : School slug (e.g. globalaccessschool)}
        {phone_number_id : "Phone number ID" from WhatsApp → API Setup}
        {--waba= : "WhatsApp Business Account ID" (recommended — events from another account are ignored)}
        {--display= : Display phone number, for reference only}
        {--disable : Stop accepting events for this number}
        {--list : List linked numbers}';

    protected $description = 'Link a school to its WhatsApp Business phone number for webhook routing';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->table(['School', 'Phone number ID', 'WABA ID', 'Display', 'Active'], WhatsappAccount::with('school')->get()->map(fn ($a) => [
                $a->school?->slug, $a->phone_number_id, $a->waba_id ?: '—', $a->display_phone_number ?: '—', $a->is_active ? 'yes' : 'no',
            ]));

            return self::SUCCESS;
        }

        $school = School::query()->where('slug', $this->argument('school'))->first();
        if (! $school) {
            $this->error('No school with that slug.');

            return self::FAILURE;
        }

        $phoneNumberId = trim((string) $this->argument('phone_number_id'));
        if (! preg_match('/^\d{5,64}$/', $phoneNumberId)) {
            $this->error('Phone number ID must be the numeric ID from API Setup (not the phone number itself).');

            return self::FAILURE;
        }

        $existing = WhatsappAccount::query()->where('phone_number_id', $phoneNumberId)->first();
        if ($existing && $existing->school_id !== $school->id) {
            $this->error('This phone number ID is already linked to another school ('.$existing->school?->slug.').');

            return self::FAILURE;
        }

        $account = WhatsappAccount::query()->updateOrCreate(
            ['phone_number_id' => $phoneNumberId],
            array_filter([
                'school_id' => $school->id,
                'waba_id' => $this->option('waba') ?: null,
                'display_phone_number' => $this->option('display') ?: null,
                'is_active' => ! $this->option('disable'),
            ], fn ($v) => $v !== null)
        );

        $this->info(sprintf(
            '%s: phone number ID %s %s for school "%s".',
            $account->wasRecentlyCreated ? 'Linked' : 'Updated',
            $phoneNumberId,
            $account->is_active ? 'is active' : 'is disabled',
            $school->slug
        ));

        return self::SUCCESS;
    }
}
