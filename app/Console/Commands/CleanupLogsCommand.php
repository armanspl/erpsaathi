<?php

namespace App\Console\Commands;

use App\Models\Master\School;
use App\Services\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Empties the import/audit log tables in every school database.
 *
 * Tenants are read from the master `schools` registry on every run, so a newly provisioned
 * school is picked up automatically — nothing to register by hand. Only the tables listed in
 * TABLES are touched, and only when they exist in that school's DB. None of them is referenced
 * by a foreign key from another table, so TRUNCATE can't cascade into other data.
 *
 * Scheduled every 4 hours in routes/console.php; run manually with:
 *   php artisan erp:cleanup-logs [--dry-run]
 */
class CleanupLogsCommand extends Command
{
    /** @var list<string> */
    public const TABLES = [
        'import_failed_rows',
        'audit_logs',
        'import_row_logs',
    ];

    private const CONNECTION = 'tenant_cleanup';

    protected $signature = 'erp:cleanup-logs {--dry-run : Show row counts without deleting anything}';

    protected $description = 'Empty import_failed_rows, audit_logs and import_row_logs in every tenant database';

    public function handle(TenantManager $tenants): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $schools = School::query()
            ->whereNotNull('db_name')
            ->where('db_name', '!=', '')
            ->orderBy('id')
            ->get(['id', 'slug', 'db_name', 'db_host', 'status']);

        // Printed up front so a production dry run shows exactly which registry was read and
        // whether the app's default DB_DATABASE is one of the tenants (it's only cleaned if so).
        $master = config('database.connections.master');
        $defaultDb = (string) config('database.connections.mysql.database');
        $this->line("Master registry: {$master['database']} @ {$master['host']}");
        $this->line("DB_DATABASE ({$defaultDb}): "
            .($schools->contains('db_name', $defaultDb) ? 'registered tenant — included' : 'not a registered tenant — left untouched'));
        $this->newLine();

        if ($schools->isEmpty()) {
            $this->warn('No school tenants found in master.');

            return self::SUCCESS;
        }

        $report = [];
        $failures = 0;
        $totalRows = 0;

        foreach ($schools as $school) {
            try {
                $tenants->configureTemporaryConnection(self::CONNECTION, $school->db_name, $school->db_host);
                $conn = DB::connection(self::CONNECTION);
                $schema = Schema::connection(self::CONNECTION);

                foreach (self::TABLES as $table) {
                    if (! $schema->hasTable($table)) {
                        $report[] = [$school->slug, $school->status, $school->db_name, $table, '—', 'not present'];

                        continue;
                    }

                    $rows = (int) $conn->table($table)->count();
                    if (! $dryRun && $rows > 0) {
                        $conn->table($table)->truncate();
                    }
                    $totalRows += $rows;
                    $report[] = [$school->slug, $school->status, $school->db_name, $table, $rows, $dryRun ? 'dry run' : 'emptied'];
                }
            } catch (Throwable $e) {
                $failures++;
                $msg = mb_substr($e->getMessage(), 0, 120);
                $report[] = [$school->slug, $school->status, $school->db_name, '*', '—', 'FAILED: '.$msg];
                Log::warning('erp:cleanup-logs failed for tenant', [
                    'school' => $school->slug,
                    'db' => $school->db_name,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                DB::purge(self::CONNECTION);
            }
        }

        $this->table(['Slug', 'Status', 'Database', 'Table', 'Rows', 'Result'], $report);

        $summary = ($dryRun ? 'Dry run: ' : '')
            .$schools->count().' school DB(s), '.$totalRows.' row(s) '.($dryRun ? 'would be removed' : 'removed')
            .($failures > 0 ? ", {$failures} failed" : '').'.';
        $failures > 0 ? $this->error($summary) : $this->info($summary);

        if (! $dryRun) {
            Log::info('erp:cleanup-logs finished', [
                'schools' => $schools->count(),
                'rows_removed' => $totalRows,
                'failures' => $failures,
            ]);
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
