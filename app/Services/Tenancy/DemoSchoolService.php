<?php

namespace App\Services\Tenancy;

use App\Models\ErpUser;
use App\Models\Master\School;
use App\Models\SchoolSetting;
use App\Models\Student;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Ensures the public Try Demo school exists, is active, and has sample data.
 */
class DemoSchoolService
{
    public function __construct(
        protected SchoolProvisioner $provisioner,
        protected TenantManager $tenants,
    ) {}

    public function slug(): string
    {
        return (string) config('tenancy.demo.slug', 'demo');
    }

    public function isDemo(?School $school = null): bool
    {
        $school ??= app(\App\Support\Tenancy\TenantContext::class)->get();

        return $school && $school->slug === $this->slug();
    }

    public function isDemoRequest(): bool
    {
        return $this->isDemo();
    }

    /** Menu/API keys Super Admin can hide for the demo school. */
    public static function importExportCatalog(): array
    {
        return [
            ['key' => 'global-workbook-import', 'label' => 'Global Workbook Import'],
            ['key' => 'global-workbook-export', 'label' => 'Global Workbook Export'],
            ['key' => 'student-pen-import', 'label' => 'Student PEN Import'],
            ['key' => 'student-export', 'label' => 'Student Export'],
            ['key' => 'student-udise-export', 'label' => 'Student UDISE Export'],
            ['key' => 'attendance-import', 'label' => 'Attendance Import'],
            ['key' => 'attendance-export', 'label' => 'Attendance Export'],
            ['key' => 'exam-marks-import', 'label' => 'Exam Marks Import'],
        ];
    }

    /**
     * @return list<string>
     */
    public function hiddenImportExportKeys(?School $school = null): array
    {
        $school ??= app(\App\Support\Tenancy\TenantContext::class)->get();
        if (! $school || $school->slug !== $this->slug()) {
            return [];
        }

        $hidden = $school->demo_settings['hidden_import_export'] ?? [];

        return array_values(array_unique(array_filter(array_map('strval', is_array($hidden) ? $hidden : []))));
    }

    public function isImportExportKeyHidden(string $key, ?School $school = null): bool
    {
        return in_array($key, $this->hiddenImportExportKeys($school), true);
    }

    /**
     * Create (if needed), activate, seed sample data, and boot the demo tenant connection.
     */
    public function ensureReady(): School
    {
        $cfg = config('tenancy.demo', []);
        $slug = $this->slug();
        $email = strtolower(trim((string) ($cfg['admin_email'] ?? 'demo@erpsaathi.com')));
        $password = (string) ($cfg['admin_password'] ?? 'Demo@12345');
        $name = (string) ($cfg['name'] ?? 'Demo School');
        $adminName = (string) ($cfg['admin_name'] ?? 'Demo Admin');

        $school = School::query()->where('slug', $slug)->first();

        if (! $school || ! in_array($school->status, ['active', 'inactive'], true)) {
            $school = $this->provisioner->provision([
                'name' => $name,
                'slug' => $slug,
                'admin_email' => $email,
                'admin_name' => $adminName,
                'admin_password' => $password,
                'notes' => 'Public Try Demo school — sample data, import disabled.',
            ]);
        } elseif ($school->status === 'inactive') {
            $school->update(['status' => 'active', 'last_error' => null]);
        }

        $this->tenants->initialize($school);
        $this->ensureAdminCredentials($email, $adminName, $password);
        $this->seedSampleDataIfEmpty();

        return $school->fresh(['domains']);
    }

    protected function ensureAdminCredentials(string $email, string $name, string $password): void
    {
        $user = ErpUser::query()->where('email', $email)->first();
        if (! $user) {
            $user = new ErpUser;
            $user->email = $email;
        }
        $user->name = $name;
        $user->password = $password;
        $user->role = 'admin';
        $user->is_active = true;
        $user->save();
    }

    protected function seedSampleDataIfEmpty(): void
    {
        if (Student::query()->exists()) {
            return;
        }

        // Seed against the current (tenant) default connection.
        Artisan::call('db:seed', [
            '--class' => DemoSchoolSeeder::class,
            '--force' => true,
        ]);

        SchoolSetting::query()->update([
            'school_name' => config('tenancy.demo.name', 'Demo School'),
        ]);
    }

    public function adminUser(): ?ErpUser
    {
        $email = strtolower(trim((string) config('tenancy.demo.admin_email', 'demo@erpsaathi.com')));

        return ErpUser::query()->where('email', $email)->first();
    }

    public function publicEnterUrl(): string
    {
        $request = request();
        $host = strtolower($request->getHost());
        $slug = $this->slug();
        $base = strtolower((string) config('tenancy.base_domain'));

        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return url('/erp/demo');
        }

        // Always open on the demo school host so the ERP session cookie sticks.
        if ($base !== '') {
            return $request->getScheme().'://'.$slug.'.'.$base.'/erp/demo';
        }

        return url('/erp/demo');
    }
}
