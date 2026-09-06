<?php

namespace App\Console\Commands;

use App\Models\Master\School;
use App\Models\Master\SchoolDomain;
use App\Models\Master\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapFirstSchoolCommand extends Command
{
    protected $signature = 'tenancy:bootstrap-first-school
        {--super-email=superadmin@erpsaathi.com : Super Admin email}
        {--super-password=password : Super Admin password}
        {--super-name=Super Admin : Super Admin name}
        {--force : Re-run even if a first school already exists}';

    protected $description = 'Create erpsaathi_master schema, Super Admin, and register the current DB as the first school tenant (no tenant data migration).';

    public function handle(): int
    {
        $masterDb = config('database.connections.master.database');
        $tenantDb = config('database.connections.mysql.database') ?: env('DB_DATABASE');
        $slug = config('tenancy.first_school_slug', 'demo');
        $name = config('tenancy.first_school_name', 'Demo School');

        $this->info("Master DB: {$masterDb}");
        $this->info("First school tenant DB (unchanged): {$tenantDb}");
        $this->info("First school slug: {$slug}");

        // Create master database if missing (connect without database name).
        $this->ensureMasterDatabaseExists($masterDb);

        $this->info('Running master migrations...');
        Artisan::call('migrate', [
            '--database' => 'master',
            '--path' => 'database/migrations/master',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        if (School::query()->where('is_first_school', true)->exists() && ! $this->option('force')) {
            $this->warn('First school already registered. Use --force to update Super Admin only / skip school insert.');
        } else {
            $school = School::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'db_name' => $tenantDb,
                    'status' => 'active',
                    'admin_email' => 'admin@globalschool.edu',
                    'storage_path' => storage_path('app/private'),
                    'is_first_school' => true,
                    'last_error' => null,
                ]
            );

            $base = config('tenancy.base_domain');
            SchoolDomain::query()->updateOrCreate(
                ['domain' => $slug.'.'.$base],
                [
                    'school_id' => $school->id,
                    'type' => 'subdomain',
                    'is_primary' => true,
                ]
            );

            // Local convenience domain entry (optional lookup).
            SchoolDomain::query()->updateOrCreate(
                ['domain' => $slug.'.localhost'],
                [
                    'school_id' => $school->id,
                    'type' => 'subdomain',
                    'is_primary' => false,
                ]
            );

            $this->info("Registered first school #{$school->id} → DB {$tenantDb}");
        }

        $email = $this->option('super-email');
        $password = $this->option('super-password');
        SuperAdmin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('super-name'),
                'password' => $password,
                'is_active' => true,
            ]
        );

        $this->info("Super Admin ready: {$email}");
        $this->newLine();
        $this->table(
            ['Item', 'Value'],
            [
                ['Super Admin login', url('/super-admin/login')],
                ['Local ERP (default first school)', url('/erp/login')],
                ['Local ERP explicit tenant', url('/erp/login?school='.$slug)],
                ['Production school host', "https://{$slug}.".config('tenancy.base_domain')],
                ['Production admin host', 'https://admin.'.config('tenancy.base_domain')],
            ]
        );

        return self::SUCCESS;
    }

    protected function ensureMasterDatabaseExists(string $masterDb): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $masterDb);
        $config = config('database.connections.master');

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']),
            $config['username'],
            $config['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->info("Ensured database `{$safe}` exists.");
    }
}
