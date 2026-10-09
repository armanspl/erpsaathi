<?php

namespace App\Services\Tenancy;

use App\Models\ErpUser;
use App\Models\Master\School;
use App\Models\Master\SchoolDomain;
use App\Services\DefaultSchoolBranchService;
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
     * @param  array{name:string,slug?:string,admin_email:string,admin_name?:string,admin_password?:string,custom_domain?:string|null,price?:float|string|null,renewal_charge?:float|string|null,billing_currency?:string|null}  $data
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
        $price = array_key_exists('price', $data) && $data['price'] !== '' && $data['price'] !== null
            ? (float) $data['price']
            : null;
        $renewal = array_key_exists('renewal_charge', $data) && $data['renewal_charge'] !== '' && $data['renewal_charge'] !== null
            ? (float) $data['renewal_charge']
            : null;
        $currency = strtoupper(trim((string) ($data['billing_currency'] ?? 'INR'))) ?: 'INR';
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

        $billing = compact('price', 'renewal', 'currency');

        $school = DB::connection('master')->transaction(function () use (
            $existing, $name, $slug, $dbName, $adminEmail, $storagePath, $customDomain, $base, $billing
        ) {
            $attrs = [
                'name' => $name,
                'db_name' => $dbName,
                'status' => 'provisioning',
                'admin_email' => $adminEmail,
                'storage_path' => $storagePath,
                'price' => $billing['price'],
                'renewal_charge' => $billing['renewal'],
                'billing_currency' => $billing['currency'],
                'last_error' => null,
            ];

            if ($existing) {
                $existing->update($attrs);
                $school = $existing->fresh();
            } else {
                $school = School::query()->create(array_merge($attrs, [
                    'slug' => $slug,
                    'is_first_school' => false,
                ]));
            }

            $this->syncDomains($school, $slug.'.'.$base, $slug.'.localhost', $customDomain);

            return $school->fresh(['domains']);
        });

        try {
            $this->createDatabase($dbName);
            $this->runTenantMigrations($dbName);
            $this->seedSchoolAdmin($dbName, $adminName, $adminEmail, $adminPassword);
            $this->seedTenantDefaults($dbName, $name);
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

        // CREATE DATABASE alone does not grant SELECT/INSERT on the new schema. On servers
        // where the app user is not a full root (e.g. 'erpsaathi'@'localhost'), migrate then
        // fails with "SELECT command denied … for table 'migrations'".
        $this->grantTenantDatabaseAccess($safe);
    }

    /**
     * Ensure the app MySQL account(s) can read/write the newly created school database.
     */
    protected function grantTenantDatabaseAccess(string $dbName): void
    {
        $accounts = [];

        try {
            $current = (string) (DB::connection('master')->selectOne('select current_user() as u')?->u ?? '');
            if ($current !== '' && str_contains($current, '@')) {
                $accounts[] = $current;
            }
        } catch (Throwable) {
            //
        }

        foreach (['mysql', 'master'] as $connection) {
            $user = (string) config("database.connections.{$connection}.username", '');
            if ($user === '') {
                continue;
            }
            // Cover the common MySQL host variants — localhost (socket) vs 127.0.0.1 (TCP)
            // are different accounts. '%' covers remote app servers.
            foreach (['localhost', '127.0.0.1', '%'] as $host) {
                $accounts[] = $user.'@'.$host;
            }
        }

        $accounts = array_values(array_unique($accounts));
        $granted = false;
        $lastError = null;

        foreach ($accounts as $account) {
            [$user, $host] = array_pad(explode('@', $account, 2), 2, 'localhost');
            $user = str_replace('`', '``', $user);
            $host = str_replace('`', '``', $host);
            try {
                DB::connection('master')->statement(
                    "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO `{$user}`@`{$host}`"
                );
                $granted = true;
            } catch (Throwable $e) {
                // Account may not exist for that host — try the next one.
                $lastError = $e;
            }
        }

        if ($granted) {
            try {
                DB::connection('master')->statement('FLUSH PRIVILEGES');
            } catch (Throwable) {
                // Some hosted MySQL setups disallow FLUSH; GRANT still applies.
            }

            return;
        }

        $hintUser = (string) (config('database.connections.mysql.username')
            ?: config('database.connections.master.username')
            ?: 'erpsaathi');

        throw new \RuntimeException(
            "MySQL user cannot access new school database `{$dbName}`. "
            .'As a MySQL admin (root), run: '
            ."GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$hintUser}'@'localhost'; "
            ."GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$hintUser}'@'127.0.0.1'; "
            .'FLUSH PRIVILEGES;'
            .($lastError ? ' ('.$lastError->getMessage().')' : ''),
            0,
            $lastError
        );
    }

    /**
     * Run pending tenant schema migrations on a school database.
     * Used by provisioning and by `php artisan erp:deploy`.
     */
    public function runTenantMigrations(string $dbName, ?string $host = null): void
    {
        $this->tenants->configureTemporaryConnection('tenant_provision', $dbName, $host);

        Artisan::call('migrate', [
            '--database' => 'tenant_provision',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
    }

    /**
     * Brings this school's system-managed Report Card template (and any other category listed
     * in TemplateSyncService::AUTO_SYNC_CATEGORIES) up to date with the current Blade source —
     * called right after runTenantMigrations() in erp:deploy's per-school loop. Never touches a
     * template an admin has hand-customized (source='custom'); see TemplateSyncService.
     *
     * @return array{synced: int, skipped_customized: int, already_synced: int, synced_names: list<string>}
     */
    public function syncTenantTemplates(string $dbName, ?string $host = null): array
    {
        $this->tenants->configureTemporaryConnection('tenant_provision', $dbName, $host);

        return \App\Services\TemplateSyncService::syncAll('tenant_provision');
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

    /**
     * Ensure school settings + a default branch exist so student branch filters work
     * immediately after Super Admin creates a school (even before imports).
     */
    protected function seedTenantDefaults(string $dbName, string $schoolName): void
    {
        $this->tenants->configureTemporaryConnection('tenant_provision', $dbName);

        $previous = config('database.default');
        config(['database.default' => 'tenant_provision']);

        try {
            $branch = DefaultSchoolBranchService::ensureNamed($schoolName);
            DefaultSchoolBranchService::assignUnassignedStudents($branch);
        } finally {
            config(['database.default' => $previous]);
        }
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

    /**
     * Permanently remove a school: drop tenant DB, delete master rows (domains cascade),
     * and remove tenant storage under storage/app/schools/{slug}.
     *
     * If the MySQL user lacks DROP privilege (common for limited accounts like erpsaathi),
     * master registry + storage are still removed so failed schools can be cleared from
     * Super Admin. The orphan DB name is returned for a root-level cleanup.
     *
     * @return array{db_dropped: bool, db_name: string}
     */
    public function destroyCompletely(School $school): array
    {
        $dbName = (string) $school->db_name;
        $storagePath = $school->storage_path
            ?: (rtrim(config('tenancy.storage_root'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$school->slug);

        $dbDropped = $this->dropDatabase($dbName);

        DB::connection('master')->transaction(function () use ($school) {
            SchoolDomain::query()->where('school_id', $school->id)->delete();
            $school->delete();
        });

        $this->removeTenantStorage($storagePath);

        return [
            'db_dropped' => $dbDropped,
            'db_name' => $dbName,
        ];
    }

    /**
     * @return bool true when the database was dropped (or never existed / already gone)
     */
    protected function dropDatabase(string $dbName): bool
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        $masterDb = preg_replace('/[^a-zA-Z0-9_]/', '', (string) config('database.connections.master.database'));

        if ($safe === '' || strcasecmp($safe, $masterDb) === 0) {
            throw new \InvalidArgumentException('Refusing to drop an invalid or master database.');
        }

        try {
            DB::connection('master')->statement("DROP DATABASE IF EXISTS `{$safe}`");

            return true;
        } catch (Throwable $e) {
            // 1044 Access denied / 1227 privilege — limited app users often can CREATE but not DROP.
            // Do not block Super Admin from removing a failed school from the registry.
            if ($this->isMysqlPrivilegeError($e)) {
                return false;
            }

            throw $e;
        }
    }

    protected function isMysqlPrivilegeError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '1044')
            || str_contains($message, '1142')
            || str_contains($message, '1227')
            || str_contains($message, 'Access denied')
            || str_contains($message, 'command denied');
    }

    protected function removeTenantStorage(string $path): void
    {
        $path = realpath($path) ?: $path;
        $root = realpath(config('tenancy.storage_root')) ?: config('tenancy.storage_root');

        // Only delete paths under storage/app/schools
        if (! is_string($path) || $path === '' || ! is_dir($path)) {
            return;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', (string) $root), '/');
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');

        if ($normalizedRoot === '' || ! str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            return;
        }

        $this->deleteDirectory($path);
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($dir);
    }
}
