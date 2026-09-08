<?php

namespace App\Console\Commands;

use App\Models\Master\School;
use App\Services\Tenancy\SchoolProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Production deploy helper for the multi-tenant ERP.
 *
 * After git pull: php artisan erp:deploy
 * — master migrations, every school DB, frontend build, cache clear.
 * Safe to re-run: migrate only applies pending migrations.
 */
class ErpDeployCommand extends Command
{
    protected $signature = 'erp:deploy
        {--skip-build : Skip npm run build}
        {--skip-cache : Skip clearing Laravel caches/views}
        {--include-failed : Also migrate schools with status=failed}';

    protected $description = 'Deploy: migrate master + all tenant DBs, build frontend, clear caches';

    public function handle(SchoolProvisioner $provisioner): int
    {
        $started = microtime(true);
        $this->info('ERP deploy started.');
        $this->newLine();

        if (! $this->migrateMaster()) {
            $this->error('Master migrations failed — aborting before tenant updates.');

            return self::FAILURE;
        }

        $tenantOk = $this->migrateTenants($provisioner);

        if (! $this->option('skip-build')) {
            if (! $this->buildFrontend()) {
                $this->error('Frontend build failed.');

                return self::FAILURE;
            }
        } else {
            $this->warn('Skipped frontend build (--skip-build).');
        }

        if (! $this->option('skip-cache')) {
            $this->clearCaches();
        } else {
            $this->warn('Skipped cache clear (--skip-cache).');
        }

        $elapsed = round(microtime(true) - $started, 1);
        $this->newLine();
        if ($tenantOk) {
            $this->info("ERP deploy finished successfully in {$elapsed}s.");

            return self::SUCCESS;
        }

        $this->error("ERP deploy finished with tenant migration failures in {$elapsed}s. See report above.");

        return self::FAILURE;
    }

    private function migrateMaster(): bool
    {
        $masterDb = config('database.connections.master.database');
        $this->info("1) Master migrations → {$masterDb}");

        try {
            $exit = Artisan::call('migrate', [
                '--database' => 'master',
                '--path' => 'database/migrations/master',
                '--force' => true,
            ]);
            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->line($output);
            }
            if ($exit !== 0) {
                $this->error("Master migrate exited with code {$exit}.");

                return false;
            }
            $this->info('Master migrations OK.');
            $this->newLine();

            return true;
        } catch (Throwable $e) {
            $this->error('Master migrate exception: '.$e->getMessage());

            return false;
        }
    }

    private function migrateTenants(SchoolProvisioner $provisioner): bool
    {
        $this->info('2) Tenant migrations (all school databases)');

        $query = School::query()
            ->whereNotNull('db_name')
            ->where('db_name', '!=', '')
            ->orderBy('id');

        if (! $this->option('include-failed')) {
            $query->where('status', '!=', 'failed');
        }

        $schools = $query->get(['id', 'name', 'slug', 'db_name', 'db_host', 'status']);

        if ($schools->isEmpty()) {
            $this->warn('No school tenants found in master. Skipping tenant migrations.');
            $this->newLine();

            return true;
        }

        $this->line('Found '.$schools->count().' school DB(s).');
        $this->newLine();

        $report = [];
        $failures = 0;

        foreach ($schools as $school) {
            $label = "{$school->slug} ({$school->db_name})";
            $this->line("→ Migrating {$label} [{$school->status}]...");

            try {
                $provisioner->runTenantMigrations($school->db_name, $school->db_host);
                $output = trim(Artisan::output());
                if ($output !== '') {
                    foreach (preg_split("/\r\n|\n|\r/", $output) as $line) {
                        if ($line !== '') {
                            $this->line('  '.$line);
                        }
                    }
                }
                $this->info("  OK — {$label}");
                $report[] = [$school->slug, $school->db_name, $school->status, 'OK', ''];
            } catch (Throwable $e) {
                $failures++;
                $msg = $e->getMessage();
                $this->error("  FAILED — {$label}: {$msg}");
                $report[] = [$school->slug, $school->db_name, $school->status, 'FAILED', mb_substr($msg, 0, 120)];
            }

            $this->newLine();
        }

        $this->table(['Slug', 'Database', 'Status', 'Result', 'Error'], $report);

        if ($failures > 0) {
            $this->error("Tenant migrations: {$failures} failed, ".($schools->count() - $failures).' succeeded.');
        } else {
            $this->info('Tenant migrations: all '.$schools->count().' succeeded.');
        }
        $this->newLine();

        return $failures === 0;
    }

    private function buildFrontend(): bool
    {
        $this->info('3) Frontend build (npm run build)');

        if (! is_file(base_path('package.json'))) {
            $this->warn('No package.json — skipping build.');
            $this->newLine();

            return true;
        }

        $result = Process::path(base_path())
            ->timeout(900)
            ->run('npm run build');

        $stdout = trim($result->output());
        $stderr = trim($result->errorOutput());
        if ($stdout !== '') {
            $this->line($stdout);
        }
        if ($stderr !== '' && ! $result->successful()) {
            $this->line($stderr);
        }

        if (! $result->successful()) {
            $this->error('npm run build failed (exit '.$result->exitCode().').');

            return false;
        }

        $this->info('Frontend build OK.');
        $this->newLine();

        return true;
    }

    private function clearCaches(): void
    {
        $this->info('4) Clearing Laravel caches / compiled views');

        // optimize:clear is idempotent and covers config, route, view, event, cache.
        Artisan::call('optimize:clear');
        $output = trim(Artisan::output());
        if ($output !== '') {
            $this->line($output);
        }

        $this->info('Caches cleared.');
        $this->newLine();
    }
}
