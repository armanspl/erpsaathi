<?php

namespace App\Services\Tenancy;

use App\Models\ErpUser;
use App\Models\Master\School;
use App\Models\Master\SchoolDomain;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SchoolProvisioner
{
    public function __construct(
        protected TenantManager $tenants
    ) {}

    /**
     * @param  array{name:string,slug?:string,admin_email:string,admin_name?:string,admin_password?:string,custom_domain?:string|null}  $data
     */
    public function provision(array $data): School
    {
        $name = trim($data['name']);
        $slug = Str::slug($data['slug'] ?? $name);
        $dbName = $this->makeDatabaseName($slug);
        $adminEmail = strtolower(trim($data['admin_email']));
        $adminName = $data['admin_name'] ?? 'School Admin';
        $adminPassword = $data['admin_password'] ?? Str::password(12);
        $customDomain = ! empty($data['custom_domain']) ? strtolower(trim($data['custom_domain'])) : null;
        $storagePath = rtrim(config('tenancy.storage_root'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$slug;

        $existing = School::query()->where('slug', $slug)->first();

        if ($existing && in_array($existing->status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException("Slug \"{$slug}\" is already taken.");
        }

        if (! $existing) {
            $takenDb = School::query()->where('db_name', $dbName)->first();
            if ($takenDb) {
                throw new \InvalidArgumentException("Database \"{$dbName}\" is already registered.");
            }
        }

        $base = config('tenancy.base_domain');
        $domains = array_values(array_filter([
            $slug.'.'.$base,
            $slug.'.localhost',
            $customDomain,
        ]));

        $this->assertDomainsAvailable($domains, $existing?->id);

        $school = DB::connection('master')->transaction(function () use (
            $existing, $name, $slug, $dbName, $adminEmail, $storagePath, $domains, $customDomain, $base
        ) {
            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'db_name' => $dbName,
                    'status' => 'provisioning',
                    'admin_email' => $adminEmail,
                    'storage_path' => $storagePath,
                    'last_error' => null,
                ]);
                $school = $existing->fresh();
            } else {
                $school = School::query()->create([
                    'name' => $name,
                    'slug' => $slug,
                    'db_name' => $dbName,
                    'status' => 'provisioning',
                    'admin_email' => $adminEmail,
                    'storage_path' => $storagePath,
                    'is_first_school' => false,
                ]);
            }

            $this->syncDomains($school, $slug.'.'.$base, $slug.'.localhost', $customDomain);

            return $school->fresh(['domains']);
        });

        try {
            $this->createDatabase($dbName);
            $this->runTenantMigrations($dbName);
            $this->seedSchoolAdmin($dbName, $adminName, $adminEmail, $adminPassword);
            $this->ensureStorage($storagePath);

            $school->update([
                'status' => 'active',
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $school->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $school->setAttribute('generated_admin_password', $adminPassword);

        return $school->fresh(['domains']);
    }

    protected function assertDomainsAvailable(array $domains, ?int $ignoreSchoolId = null): void
    {
        $query = SchoolDomain::query()->whereIn('domain', $domains);
        if ($ignoreSchoolId) {
            $query->where('school_id', '!=', $ignoreSchoolId);
        }

        $conflict = $query->first();
        if ($conflict) {
            throw new \InvalidArgumentException("Domain \"{$conflict->domain}\" is already in use.");
        }
    }

    protected function syncDomains(School $school, string $primary, string $localhost, ?string $custom): void
    {
        SchoolDomain::query()->updateOrCreate(
            ['domain' => $primary],
            [
                'school_id' => $school->id,
                'type' => 'subdomain',
                'is_primary' => true,
            ]
        );

        SchoolDomain::query()->updateOrCreate(
            ['domain' => $localhost],
            [
                'school_id' => $school->id,
                'type' => 'subdomain',
                'is_primary' => false,
            ]
        );

        SchoolDomain::query()
            ->where('school_id', $school->id)
            ->where('type', 'custom')
            ->when($custom, fn ($q) => $q->where('domain', '!=', $custom))
            ->delete();

        if ($custom) {
            SchoolDomain::query()->updateOrCreate(
                ['domain' => $custom],
                [
                    'school_id' => $school->id,
                    'type' => 'custom',
                    'is_primary' => false,
                ]
            );
        }
    }

    public function makeDatabaseName(string $slug): string
    {
        $safe = preg_replace('/[^a-z0-9_]/', '_', str_replace('-', '_', strtolower($slug)));

        return $safe.'_db';
    }

    protected function createDatabase(string $dbName): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        DB::connection('master')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    protected function runTenantMigrations(string $dbName): void
    {
        $this->tenants->configureTemporaryConnection('tenant_provision', $dbName);

        Artisan::call('migrate', [
            '--database' => 'tenant_provision',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
    }

    protected function seedSchoolAdmin(string $dbName, string $name, string $email, string $password): void
    {
        $this->tenants->configureTemporaryConnection('tenant_provision', $dbName);

        $user = ErpUser::on('tenant_provision')->where('email', $email)->first();
        if (! $user) {
            $user = new ErpUser;
            $user->setConnection('tenant_provision');
            $user->email = $email;
        }

        $user->name = $name;
        $user->password = $password;
        $user->role = 'admin';
        $user->is_active = true;
        $user->save();
    }

    protected function ensureStorage(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
        foreach (['documents', 'uploads', 'exports'] as $sub) {
            $dir = $path.DIRECTORY_SEPARATOR.$sub;
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function setStatus(School $school, string $status): School
    {
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }
        $school->update(['status' => $status]);

        return $school->fresh();
    }

    public function resetSchoolAdmin(School $school, ?string $password = null): string
    {
        $password = $password ?: Str::password(12);
        $this->tenants->configureTemporaryConnection('tenant_provision', $school->db_name, $school->db_host);

        $user = ErpUser::on('tenant_provision')
            ->where('email', $school->admin_email)
            ->first();

        if (! $user) {
            $user = new ErpUser([
                'name' => 'School Admin',
                'email' => $school->admin_email,
                'role' => 'admin',
                'is_active' => true,
            ]);
            $user->setConnection('tenant_provision');
        }

        $user->password = $password;
        $user->role = 'admin';
        $user->is_active = true;
        $user->save();

        return $password;
    }
}
