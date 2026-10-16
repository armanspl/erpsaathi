<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Master\School;
use App\Models\Master\SuperAdminDbAudit;
use App\Services\Tenancy\DatabaseManagerException;
use App\Services\Tenancy\SchoolDatabaseManager;
use App\Services\Tenancy\SchoolProvisioner;
use App\Services\Tenancy\SchoolSqlImporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super Admin Database Manager API. Every endpoint is scoped to ONE school (route model binding on the
 * master `schools` table) and opens only that school's registered database via SchoolDatabaseManager.
 */
class SchoolDatabaseController extends Controller
{
    public function __construct(private SchoolDatabaseManager $manager) {}

    /** Every school with its database status (exists, tables, size, whether it can be managed). */
    public function index(): JsonResponse
    {
        $schools = School::query()->with('domains')->orderBy('name')->get();
        $status = $this->manager->statusForSchools($schools);

        return response()->json([
            'schools' => $schools->map(fn (School $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'status' => $s->status,
                'db_name' => $s->db_name,
                'domain' => ($s->domains->firstWhere('is_primary', true) ?? $s->domains->first())?->domain,
                'database' => $status[$s->id] ?? null,
                'blocked_reason' => $this->manager->blockedReason($s),
            ])->values(),
        ]);
    }

    public function overview(School $school): JsonResponse
    {
        return $this->run($school, fn () => [
            'school' => $this->schoolInfo($school),
            ...$this->manager->overview(),
            'upload_limit' => $this->uploadLimit(),
        ]);
    }

    public function structure(School $school, string $table): JsonResponse
    {
        return $this->run($school, fn () => $this->manager->structure($table));
    }

    public function rows(Request $request, School $school, string $table): JsonResponse
    {
        $filters = $request->input('filters');
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        return $this->run($school, fn () => $this->manager->rows($table, [
            'page' => $request->query('page'),
            'per_page' => $request->query('per_page'),
            'search' => $request->query('search'),
            'sort' => $request->query('sort'),
            'dir' => $request->query('dir'),
            'filters' => is_array($filters) ? $filters : [],
        ]));
    }

    public function show(Request $request, School $school, string $table): JsonResponse
    {
        $key = $request->input('key');

        return $this->run($school, fn () => $this->manager->find($table, is_array($key) ? $key : []));
    }

    public function store(Request $request, School $school, string $table): JsonResponse
    {
        $data = $request->validate(['values' => ['required', 'array']]);

        return $this->run($school, function () use ($request, $school, $table, $data) {
            $result = $this->manager->insert($table, $data['values']);
            $this->audit($request, $school, 'insert_record', $table, $result['key'], ['values' => $this->mask($result['values'])], 1);

            return ['message' => 'Record inserted.', 'key' => $result['key']];
        }, 201);
    }

    public function update(Request $request, School $school, string $table): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'array'],
            'values' => ['required', 'array'],
        ]);

        return $this->run($school, function () use ($request, $school, $table, $data) {
            $result = $this->manager->update($table, $data['key'], $data['values']);
            if (! $result['after']) {
                return ['message' => 'No changes to save.', 'key' => $result['key']];
            }
            $this->audit($request, $school, 'update_record', $table, $data['key'], [
                'before' => $this->mask($result['before']),
                'after' => $this->mask($result['after']),
            ], 1);

            return ['message' => 'Record updated.', 'key' => $result['key']];
        });
    }

    public function destroyRows(Request $request, School $school, string $table): JsonResponse
    {
        $data = $request->validate(['keys' => ['required', 'array', 'min:1', 'max:500']]);

        return $this->run($school, function () use ($request, $school, $table, $data) {
            $deleted = $this->manager->deleteRecords($table, $data['keys']);
            $this->audit($request, $school, 'delete_records', $table, $data['keys'], null, $deleted);

            return ['message' => $deleted === 1 ? '1 record deleted.' : "{$deleted} records deleted.", 'deleted' => $deleted];
        });
    }

    public function emptyTable(Request $request, School $school, string $table): JsonResponse
    {
        return $this->destructive($request, $school, $table, 'empty_table', function () use ($table) {
            $n = $this->manager->emptyTable($table);

            return [$n, "Table `{$table}` emptied: {$n} row(s) deleted. Structure and AUTO_INCREMENT kept."];
        });
    }

    public function truncateTable(Request $request, School $school, string $table): JsonResponse
    {
        return $this->destructive($request, $school, $table, 'truncate_table', function () use ($table) {
            $n = $this->manager->truncateTable($table);

            return [$n, "Table `{$table}` truncated: {$n} row(s) removed and AUTO_INCREMENT reset."];
        });
    }

    public function dropTable(Request $request, School $school, string $table): JsonResponse
    {
        return $this->destructive($request, $school, $table, 'drop_table', function () use ($table) {
            $n = $this->manager->dropTable($table);

            return [$n, "Table `{$table}` dropped ({$n} row(s) and its structure removed)."];
        });
    }

    /** Streams a .sql backup of the whole school database, or of one table when $table is given. */
    public function backup(Request $request, School $school, ?string $table = null): StreamedResponse|JsonResponse
    {
        try {
            $this->manager->open($school);
            if ($table !== null) {
                $this->manager->assertTable($table);
            }
        } catch (DatabaseManagerException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $this->audit($request, $school, $table === null ? 'backup_database' : 'backup_table', $table, null, null, null);
        $filename = $school->db_name.($table !== null ? '.'.$table : '').'_'.now()->format('Y-m-d_His').'.sql';

        return response()->streamDownload(function () use ($table) {
            @set_time_limit(0);
            $this->manager->dump($table, function (string $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            });
        }, $filename, ['Content-Type' => 'application/sql; charset=utf-8']);
    }

    /**
     * Import SQL, step 1: upload a .sql / .sql.gz file (or re-check one already uploaded, e.g. the
     * pre-import backup to undo an import) and report what it would change. Nothing is changed.
     */
    public function importPreview(Request $request, School $school): JsonResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:2097152'],
            'token' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/'],
        ]);

        return $this->run($school, function () use ($request, $school) {
            $importer = $this->importer();
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $name = strtolower($file->getClientOriginalName());
                if (! str_ends_with($name, '.sql') && ! str_ends_with($name, '.sql.gz') && ! str_ends_with($name, '.gz')) {
                    throw new DatabaseManagerException('Choose a .sql or .sql.gz file.', 422);
                }
                $token = $importer->stash($file, $school);
            } elseif ($request->filled('token')) {
                $token = (string) $request->input('token');
            } else {
                throw new DatabaseManagerException('Choose a .sql file to import.', 422);
            }

            return $importer->analyse($token, $school);
        });
    }

    /** Import SQL, step 2: backup, then run the checked file. The caller types the database name. */
    public function importSql(Request $request, School $school): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'confirmation' => ['required', 'string', 'max:200'],
            'run_migrations' => ['boolean'],
        ]);
        if (trim($data['confirmation']) !== $school->db_name) {
            return response()->json(['message' => "Confirmation must be exactly: {$school->db_name}"], 422);
        }

        return $this->run($school, function () use ($request, $school, $data) {
            $importer = $this->importer();
            $meta = $importer->meta($data['token'], $school);
            $result = $importer->import($data['token'], $school, (bool) ($data['run_migrations'] ?? false));

            $this->audit($request, $school, $meta['kind'] === 'backup' ? 'restore_sql' : 'import_sql', null, null, [
                'file' => $meta['name'],
                'size' => $meta['size'],
                'ok' => $result['ok'],
                'executed' => $result['executed'],
                'skipped' => $result['skipped'],
                'tables' => array_slice($result['tables'], 0, 200),
                'failed' => $result['failed'],
                'backup_file' => $result['backup']['name'],
                'migrations' => ['ran' => $result['migrations']['ran'], 'error' => $result['migrations']['error']],
            ], $result['executed']);

            return $result;
        });
    }

    /** Downloads an uploaded import file or a pre-import backup of this school. */
    public function importDownload(School $school, string $token): StreamedResponse|JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $importer = $this->importer();
            $meta = $importer->meta($token, $school);
        } catch (DatabaseManagerException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->download($importer->path($token), $meta['name'], ['Content-Type' => 'application/sql; charset=utf-8']);
    }

    /** Removes an uploaded file that was checked but not imported (backups are kept). */
    public function importDiscard(School $school, string $token): JsonResponse
    {
        try {
            $this->importer()->discard($token, $school);
        } catch (DatabaseManagerException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['message' => 'Upload removed.']);
    }

    /** Shares this controller's manager, so it works on the school database run() opened. */
    private function importer(): SchoolSqlImporter
    {
        return new SchoolSqlImporter($this->manager, app(SchoolProvisioner::class));
    }

    /** The largest file PHP accepts in one upload on this server. */
    private function uploadLimit(): string
    {
        $bytes = function (string $v): int {
            $v = trim($v);
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824,
                'm' => $n * 1048576,
                'k' => $n * 1024,
                default => $n,
            };
        };
        $limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);
        $min = $limits ? min($limits) : 0;

        return $min > 0 ? round($min / 1048576).' MB' : 'unlimited';
    }

    public function audits(School $school): JsonResponse
    {
        return response()->json([
            'audits' => SuperAdminDbAudit::query()
                ->where('school_id', $school->id)
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * Empty / truncate / drop: the caller must type "<db_name>.<table>" so the wrong school or table
     * cannot be hit by accident.
     */
    private function destructive(Request $request, School $school, string $table, string $action, callable $op): JsonResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:200']]);
        $expected = $school->db_name.'.'.$table;
        if (trim($data['confirmation']) !== $expected) {
            return response()->json(['message' => "Confirmation must be exactly: {$expected}"], 422);
        }

        return $this->run($school, function () use ($request, $school, $table, $action, $op) {
            $this->manager->assertTable($table, true);
            [$rows, $message] = $op();
            $this->audit($request, $school, $action, $table, null, null, $rows);

            return ['message' => $message, 'affected' => $rows];
        });
    }

    /** Opens the school's database, runs $fn and maps refusals / MySQL errors to clear JSON errors. */
    private function run(School $school, callable $fn, int $status = 200): JsonResponse
    {
        try {
            $this->manager->open($school);

            return response()->json($fn(), $status);
        } catch (DatabaseManagerException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (QueryException $e) {
            return response()->json(['message' => $this->friendlyError($e)], 422);
        }
    }

    private function friendlyError(QueryException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $driver = $this->manager->driverMessage($e);

        $hint = match ($code) {
            1451, 1217 => 'Blocked by a foreign key: rows in another table still reference these records. Remove or re-point those rows first.',
            1452, 1216 => 'A value does not exist in the table it references (foreign key).',
            1701 => 'MySQL cannot TRUNCATE a table that other tables reference with foreign keys. Use "Empty table" instead, which deletes row by row and follows the ON DELETE rules.',
            3730 => 'MySQL cannot DROP this table while foreign keys in other tables reference it. Drop or change those foreign keys first.',
            1062 => 'Duplicate value for a unique key.',
            1048 => 'A required column was left empty.',
            1264, 1265, 1292, 1366, 1406 => 'A value does not fit its column type or length.',
            1142, 1044, 1045 => 'The MySQL user is not allowed to do this on the school database.',
            default => 'MySQL refused the operation.',
        };

        return "{$hint} ({$driver})";
    }

    private function audit(Request $request, School $school, string $action, ?string $table, $key, ?array $details, ?int $affected): void
    {
        $admin = Auth::guard('super_admin')->user();

        SuperAdminDbAudit::query()->create([
            'super_admin_id' => $admin?->id,
            'super_admin_email' => $admin?->email,
            'school_id' => $school->id,
            'db_name' => $school->db_name,
            'action' => $action,
            'table_name' => $table,
            'record_key' => $key,
            'details' => $details,
            'affected_rows' => $affected,
            'ip' => $request->ip(),
        ]);
    }

    /** Hide secrets (password hashes, tokens) from the audit trail. */
    private function mask(array $values): array
    {
        foreach ($values as $col => $v) {
            if (preg_match('/pass(word)?|token|secret|otp|api_key/i', (string) $col)) {
                $values[$col] = $v === null ? null : '***';
            }
        }

        return $values;
    }

    private function schoolInfo(School $school): array
    {
        $school->loadMissing('domains');

        return [
            'id' => $school->id,
            'name' => $school->name,
            'slug' => $school->slug,
            'status' => $school->status,
            'db_name' => $school->db_name,
            'domain' => ($school->domains->firstWhere('is_primary', true) ?? $school->domains->first())?->domain,
        ];
    }
}
