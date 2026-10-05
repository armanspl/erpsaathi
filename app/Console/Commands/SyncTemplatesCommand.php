<?php

namespace App\Console\Commands;

use App\Models\Master\School;
use App\Services\Tenancy\SchoolProvisioner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manual/scriptable equivalent of the template-sync step `erp:deploy` already runs
 * automatically after each tenant's migrations (see SchoolProvisioner::syncTenantTemplates()
 * and TemplateSyncService) — lets you resync system-managed document templates (Report Card,
 * etc.) across every school without running a full deploy. Safe to re-run: a template already
 * on the current Blade source is left untouched, and an admin-customized one is always skipped.
 */
class SyncTemplatesCommand extends Command
{
    protected $signature = 'templates:sync {--include-failed : Also sync schools with status=failed}';

    protected $description = 'Sync system-managed document templates (Report Card, etc.) with the current Blade source, across every tenant';

    public function handle(SchoolProvisioner $provisioner): int
    {
        $query = School::query()
            ->whereNotNull('db_name')
            ->where('db_name', '!=', '')
            ->orderBy('id');

        if (! $this->option('include-failed')) {
            $query->where('status', '!=', 'failed');
        }

        $schools = $query->get(['id', 'name', 'slug', 'db_name', 'db_host', 'status']);

        if ($schools->isEmpty()) {
            $this->warn('No school tenants found.');

            return self::SUCCESS;
        }

        $report = [];
        $failures = 0;

        foreach ($schools as $school) {
            try {
                $result = $provisioner->syncTenantTemplates($school->db_name, $school->db_host);
                $report[] = [
                    $school->slug,
                    $result['synced'],
                    $result['already_synced'],
                    $result['skipped_customized'],
                    $result['synced'] > 0 ? implode(', ', $result['synced_names']) : '',
                ];
            } catch (Throwable $e) {
                $failures++;
                $report[] = [$school->slug, '-', '-', '-', 'FAILED: '.mb_substr($e->getMessage(), 0, 100)];
            }
        }

        $this->table(['Slug', 'Synced', 'Already current', 'Customized (skipped)', 'Detail'], $report);

        if ($failures > 0) {
            $this->error("{$failures} school(s) failed to sync — see table above.");

            return self::FAILURE;
        }

        $this->info('Template sync complete.');

        return self::SUCCESS;
    }
}
