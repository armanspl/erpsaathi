<?php

namespace App\Services\Tenancy;

use App\Models\Master\School;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Super Admin "Import SQL" into ONE registered school's database (Databases → Manage database).
 *
 * Safety model (on top of SchoolDatabaseManager's):
 *  - The target database always comes from the school's master record; the file's own USE /
 *    CREATE DATABASE statements are skipped, so every statement runs in that database only.
 *  - The file is checked statement by statement BEFORE anything runs (preview). Only table-level
 *    statements are allowed: CREATE/DROP/ALTER/RENAME/TRUNCATE TABLE, INSERT/REPLACE/UPDATE/DELETE,
 *    views (DEFINER removed), session SET, LOCK/UNLOCK TABLES and transactions. Anything else
 *    (GRANT, users, routines/triggers/DELIMITER, LOAD DATA, INTO OUTFILE, SET GLOBAL…) and any
 *    reference to another database on the server refuses the whole file.
 *  - Before the first statement runs, a full .sql backup of the current database is written to
 *    private storage; it can be downloaded or re-imported to undo the import.
 */
class SchoolSqlImporter
{
    /** Uploads and pre-import backups older than this are removed on the next upload. */
    private const KEEP_DAYS = 7;

    private const MAX_ERRORS = 25;

    public function __construct(
        private SchoolDatabaseManager $manager,
        private SchoolProvisioner $provisioner,
    ) {}

    // ── Stored files (upload / backup), addressed by an unguessable token ───────────────────

    public function dir(): string
    {
        return storage_path('app/private/sql-imports');
    }

    /** Saves an uploaded .sql / .sql.gz file for $school and returns its token. */
    public function stash(UploadedFile $file, School $school): string
    {
        $this->cleanup();
        $token = bin2hex(random_bytes(16));
        File::ensureDirectoryExists($this->dir());
        $file->move($this->dir(), $token.'.sql');
        $this->writeMeta($token, [
            'school_id' => $school->id,
            'db_name' => $school->db_name,
            'kind' => 'upload',
            'name' => $file->getClientOriginalName(),
            'size' => filesize($this->dir().'/'.$token.'.sql'),
            'created_at' => now()->toDateTimeString(),
        ]);

        return $token;
    }

    /** @return array{school_id: int, db_name: string, kind: string, name: string, size: int, created_at: string} */
    public function meta(string $token, School $school): array
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new DatabaseManagerException('Invalid import file reference.', 422);
        }
        $metaPath = $this->dir().'/'.$token.'.json';
        $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        if (! is_array($meta) || ! is_file($this->path($token))) {
            throw new DatabaseManagerException('The uploaded file is no longer available. Upload it again.', 404);
        }
        if ((int) $meta['school_id'] !== (int) $school->id) {
            throw new DatabaseManagerException('This file was uploaded for a different school.', 403);
        }

        return $meta;
    }

    public function path(string $token): string
    {
        return $this->dir().'/'.$token.'.sql';
    }

    public function discard(string $token, School $school): void
    {
        $meta = $this->meta($token, $school);
        // A pre-import backup is the way back from an import — never delete it from the UI.
        if ($meta['kind'] === 'upload') {
            File::delete([$this->path($token), $this->dir().'/'.$token.'.json']);
        }
    }

    private function writeMeta(string $token, array $meta): void
    {
        file_put_contents($this->dir().'/'.$token.'.json', json_encode($meta, JSON_PRETTY_PRINT));
    }

    private function cleanup(): void
    {
        if (! is_dir($this->dir())) {
            return;
        }
        $cutoff = time() - self::KEEP_DAYS * 86400;
        foreach (File::files($this->dir()) as $f) {
            if ($f->getMTime() < $cutoff) {
                File::delete($f->getPathname());
            }
        }
    }

    // ── Preview ─────────────────────────────────────────────────────────────────────────────

    /**
     * Reads the whole file without changing anything and reports what it would do.
     * The school's database must already be open on $this->manager.
     */
    public function analyse(string $token, School $school): array
    {
        $meta = $this->meta($token, $school);
        $path = $this->path($token);
        $target = strtolower((string) $school->db_name);
        $others = $this->otherSchemas($target);

        $existing = [];
        foreach ($this->manager->overview()['tables'] as $t) {
            $existing[strtolower($t['name'])] = $t;
        }

        $tables = [];
        $errors = [];
        $skipped = [];
        $sourceDbs = [];
        $appendsToExisting = [];
        $count = 0;
        $executable = 0;

        $touch = function (string $name) use (&$tables, $existing): string {
            $key = strtolower($name);
            $tables[$key] ??= [
                'name' => $name,
                'exists' => isset($existing[$key]),
                'is_view' => (bool) ($existing[$key]['is_view'] ?? false),
                'rows_now' => $existing[$key]['rows'] ?? null,
                'drop' => false, 'create' => false, 'view' => false, 'insert_rows' => 0,
                'truncate' => false, 'delete' => false, 'update' => false, 'alter' => false, 'rename' => false,
                'cleared' => false,
            ];

            return $key;
        };

        try {
            foreach ($this->statements($path) as $stmt) {
                $count++;
                $c = $this->classify($stmt['skeleton'], $others);
                if ($c['kind'] === 'error') {
                    if (count($errors) < self::MAX_ERRORS) {
                        $errors[] = ['line' => $stmt['line'], 'statement' => $this->snippet($stmt['sql']), 'reason' => $c['reason']];
                    }

                    continue;
                }
                if ($c['kind'] === 'skip') {
                    $skipped[] = ['line' => $stmt['line'], 'statement' => $this->snippet($stmt['sql']), 'reason' => $c['reason']];
                    if (! empty($c['database'])) {
                        $sourceDbs[strtolower($c['database'])] = $c['database'];
                    }

                    continue;
                }
                $executable++;
                foreach ($c['tables'] as $name) {
                    $key = $touch($name);
                    $t = &$tables[$key];
                    switch ($c['kind']) {
                        case 'drop':
                            $t['drop'] = true;
                            $t['cleared'] = true;
                            break;
                        case 'create':
                            $t['create'] = true;
                            $t['cleared'] = true;
                            break;
                        case 'view':
                            $t['view'] = true;
                            break;
                        case 'insert':
                            $t['insert_rows'] += $c['rows'];
                            if ($t['exists'] && ! $t['cleared'] && (int) $t['rows_now'] > 0) {
                                $appendsToExisting[$key] = $t['name'];
                            }
                            break;
                        case 'truncate':
                            $t['truncate'] = true;
                            $t['cleared'] = true;
                            break;
                        case 'delete':
                            $t['delete'] = true;
                            $t['cleared'] = true;
                            break;
                        case 'update':
                            $t['update'] = true;
                            break;
                        case 'alter':
                            $t['alter'] = true;
                            break;
                        case 'rename':
                            $t['rename'] = true;
                            break;
                    }
                    unset($t);
                }
            }
        } catch (DatabaseManagerException $e) {
            $errors[] = ['line' => null, 'statement' => '', 'reason' => $e->getMessage()];
        }

        $headerDb = $this->headerDatabase($path);
        if ($headerDb !== null) {
            $sourceDbs[strtolower($headerDb)] ??= $headerDb;
        }

        if ($count === 0 && ! $errors) {
            $errors[] = ['line' => null, 'statement' => '', 'reason' => 'The file contains no SQL statements.'];
        }

        $warnings = [];
        $foreign = array_values(array_filter($sourceDbs, fn ($db) => strtolower($db) !== $target));
        if ($foreign) {
            $warnings[] = 'This file was exported from database `'.implode('`, `', $foreign).'`. It will be imported into `'
                .$school->db_name.'` ('.$school->name.'). Make sure this is the right school.';
        }
        $replaced = array_filter($tables, fn ($t) => $t['exists'] && ($t['drop'] || $t['truncate'] || $t['delete']));
        if ($replaced) {
            $rows = array_sum(array_map(fn ($t) => (int) $t['rows_now'], $replaced));
            $warnings[] = count($replaced).' existing table(s) holding '.number_format($rows).' row(s) will be emptied or replaced by the data in this file.';
        }
        if ($appendsToExisting) {
            $warnings[] = 'Rows are ADDED to existing data (not replaced) in: '.implode(', ', array_slice($appendsToExisting, 0, 15))
                .(count($appendsToExisting) > 15 ? ' …' : '').'. A row whose id already exists will stop the import.';
        }
        $untouched = array_diff_key($existing, $tables);
        if ($untouched && $tables) {
            $warnings[] = count($untouched).' existing table(s) are not in this file and stay unchanged.';
        }

        ksort($tables);

        return [
            'token' => $token,
            'file' => ['name' => $meta['name'], 'size' => (int) $meta['size'], 'kind' => $meta['kind'], 'created_at' => $meta['created_at']],
            'statements' => $count,
            'executable' => $executable,
            'skipped' => array_slice($skipped, 0, 50),
            'skipped_count' => count($skipped),
            'source_databases' => array_values($sourceDbs),
            'errors' => $errors,
            'warnings' => $warnings,
            'tables' => array_values(array_map(fn ($t) => [
                'name' => $t['name'],
                'exists' => $t['exists'],
                'rows_now' => $t['rows_now'],
                'effects' => $this->effects($t),
                'replaces' => $t['exists'] && ($t['drop'] || $t['truncate'] || $t['delete']),
            ], $tables)),
            'can_import' => ! $errors && $executable > 0,
        ];
    }

    /** @return list<string> */
    private function effects(array $t): array
    {
        $out = [];
        if ($t['drop'] && $t['create']) {
            $out[] = $t['exists'] ? 'Drop & recreate' : 'Create';
        } elseif ($t['drop']) {
            $out[] = $t['exists'] ? 'Drop' : 'Drop (not present)';
        } elseif ($t['create']) {
            $out[] = $t['exists'] ? 'Create (already exists)' : 'Create';
        }
        if ($t['view']) {
            $out[] = 'Create view';
        }
        if ($t['truncate']) {
            $out[] = 'Empty (truncate)';
        }
        if ($t['delete']) {
            $out[] = 'Delete rows';
        }
        if ($t['insert_rows'] > 0) {
            $out[] = 'Insert ≈ '.number_format($t['insert_rows']).' row(s)';
        }
        if ($t['update']) {
            $out[] = 'Update rows';
        }
        if ($t['alter']) {
            $out[] = 'Change structure';
        }
        if ($t['rename']) {
            $out[] = 'Rename';
        }

        return $out;
    }

    // ── Import ──────────────────────────────────────────────────────────────────────────────

    /**
     * Backs the database up, then runs every allowed statement in file order, stopping at the
     * first MySQL error. The school's database must already be open on $this->manager.
     */
    public function import(string $token, School $school, bool $runMigrations): array
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $plan = $this->analyse($token, $school);
        if (! $plan['can_import']) {
            throw new DatabaseManagerException('This file cannot be imported: '.($plan['errors'][0]['reason'] ?? 'nothing to import.'), 422);
        }

        $backup = $this->backup($school);
        $others = $this->otherSchemas(strtolower((string) $school->db_name));
        $db = $this->manager->db();
        $started = microtime(true);
        $executed = 0;
        $skipped = 0;
        $failed = null;
        $number = 0;

        try {
            foreach ($this->statements($this->path($token)) as $stmt) {
                $number++;
                $c = $this->classify($stmt['skeleton'], $others);
                if ($c['kind'] === 'skip') {
                    $skipped++;

                    continue;
                }
                if ($c['kind'] === 'error') {
                    // The preview already refused such files; this only guards a changed file.
                    $failed = ['number' => $number, 'line' => $stmt['line'], 'statement' => $this->snippet($stmt['sql']), 'message' => $c['reason']];
                    break;
                }
                $sql = $c['kind'] === 'view' ? $this->stripDefiner($stmt['sql']) : $stmt['sql'];
                try {
                    $db->unprepared($sql);
                    $executed++;
                } catch (QueryException $e) {
                    $failed = ['number' => $number, 'line' => $stmt['line'], 'statement' => $this->snippet($stmt['sql']), 'message' => $this->errorText($e)];
                    break;
                }
            }
        } catch (DatabaseManagerException $e) {
            $failed = ['number' => $number, 'line' => null, 'statement' => '', 'message' => $e->getMessage()];
        } finally {
            // Leave nothing of the file's session state behind (open transaction, locks, FK checks).
            // A file that turned autocommit off without a final COMMIT is committed when it ran clean.
            foreach ([$failed === null ? 'COMMIT' : 'ROLLBACK', 'UNLOCK TABLES', 'SET FOREIGN_KEY_CHECKS=1', 'SET UNIQUE_CHECKS=1'] as $reset) {
                try {
                    $db->unprepared($reset);
                } catch (Throwable) {
                    //
                }
            }
            DB::purge(SchoolDatabaseManager::CONNECTION);
        }

        $migrations = ['ran' => false, 'output' => null, 'error' => null];
        if ($failed === null && $runMigrations) {
            try {
                $this->provisioner->runTenantMigrations((string) $school->db_name, $school->db_host ?: null);
                $migrations['ran'] = true;
                $migrations['output'] = mb_substr(trim(Artisan::output()), 0, 4000);
            } catch (Throwable $e) {
                $migrations['error'] = $e->getMessage();
            } finally {
                DB::purge('tenant_provision');
            }
        }

        return [
            'ok' => $failed === null,
            'file' => $plan['file'],
            'statements' => $plan['statements'],
            'executed' => $executed,
            'skipped' => $skipped,
            'seconds' => round(microtime(true) - $started, 1),
            'failed' => $failed,
            'tables' => array_map(fn ($t) => $t['name'], $plan['tables']),
            'backup' => $backup,
            'migrations' => $migrations,
        ];
    }

    /** Full .sql dump of the current database, stored next to the uploads under its own token. */
    private function backup(School $school): array
    {
        $this->cleanup();
        File::ensureDirectoryExists($this->dir());
        $token = bin2hex(random_bytes(16));
        $name = $school->db_name.'_before-import_'.now()->format('Y-m-d_His').'.sql';

        $fh = fopen($this->path($token), 'wb');
        if (! $fh) {
            throw new DatabaseManagerException('Could not write the pre-import backup; nothing was imported.', 500);
        }
        try {
            $this->manager->dump(null, function (string $chunk) use ($fh) {
                if (fwrite($fh, $chunk) === false) {
                    throw new DatabaseManagerException('Could not write the pre-import backup (disk full?); nothing was imported.', 500);
                }
            });
        } catch (Throwable $e) {
            fclose($fh);
            File::delete($this->path($token));
            throw $e instanceof DatabaseManagerException ? $e
                : new DatabaseManagerException('Pre-import backup failed; nothing was imported: '.$this->manager->driverMessage($e), 500);
        }
        fclose($fh);

        $meta = [
            'school_id' => $school->id,
            'db_name' => $school->db_name,
            'kind' => 'backup',
            'name' => $name,
            'size' => filesize($this->path($token)),
            'created_at' => now()->toDateTimeString(),
        ];
        $this->writeMeta($token, $meta);

        return ['token' => $token, 'name' => $name, 'size' => $meta['size']];
    }

    // ── Statement checks ────────────────────────────────────────────────────────────────────

    /**
     * What one statement does. kind: create|drop|view|alter|rename|truncate|insert|update|delete|session|skip|error.
     *
     * @param  array<string, true>  $others  lower-cased names of databases other than the target
     * @return array{kind: string, tables: list<string>, rows: int, reason?: string, database?: string}
     */
    public function classify(string $skeleton, array $others): array
    {
        $s = trim(preg_replace('/\s+/', ' ', $skeleton));
        $out = ['kind' => 'error', 'tables' => [], 'rows' => 0];
        $name = '(`(?:[^`]|``)+`|[A-Za-z0-9_$]+)(?:\s*\.\s*(`(?:[^`]|``)+`|[A-Za-z0-9_$]+))?';

        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b|\bLOAD_FILE\s*\(|\bLOAD\s+(DATA|XML)\b|\b(DATA|INDEX)\s+DIRECTORY\b/i', $s)) {
            return $out + ['reason' => 'Reads or writes files on the database server; not allowed.'];
        }
        if (preg_match_all('/(`(?:[^`]|``)+`|\b[A-Za-z0-9_$]+)\s*\.\s*(?=[`A-Za-z_$])/', $s, $m)) {
            foreach ($m[1] as $qualifier) {
                $db = strtolower($this->unquote($qualifier));
                if (isset($others[$db])) {
                    return $out + ['reason' => "Refers to another database `{$this->unquote($qualifier)}`; only this school's database can be changed."];
                }
            }
        }

        $tablesFrom = function (string $list) use ($name): array {
            preg_match_all('/'.$name.'/', $list, $mm, PREG_SET_ORDER);

            return array_map(fn ($x) => $this->unquote(($x[2] ?? '') !== '' ? $x[2] : $x[1]), $mm);
        };
        $one = fn (array $m) => [$this->unquote(($m[2] ?? '') !== '' ? $m[2] : $m[1])];

        return match (true) {
            preg_match('/^USE\s+'.$name.'\s*$/i', $s, $m) === 1 => ['kind' => 'skip', 'tables' => [], 'rows' => 0, 'reason' => 'USE skipped: the file is always imported into this school\'s database.', 'database' => $this->unquote($m[1])],
            preg_match('/^CREATE\s+(?:DATABASE|SCHEMA)\s+(?:IF\s+NOT\s+EXISTS\s+)?'.$name.'/i', $s, $m) === 1 => ['kind' => 'skip', 'tables' => [], 'rows' => 0, 'reason' => 'CREATE DATABASE skipped.', 'database' => $this->unquote($m[1])],
            preg_match('/^\\\\/', $s) === 1 => ['kind' => 'skip', 'tables' => [], 'rows' => 0, 'reason' => 'mysql client command skipped.'],
            preg_match('/^(ANALYZE|OPTIMIZE|CHECK|REPAIR)\s+(NO_WRITE_TO_BINLOG\s+|LOCAL\s+)?TABLES?\b/i', $s) === 1 => ['kind' => 'skip', 'tables' => [], 'rows' => 0, 'reason' => 'Table maintenance statement skipped.'],
            preg_match('/^SET\b/i', $s) === 1 => preg_match('/\b(GLOBAL|PERSIST|PERSIST_ONLY|PASSWORD|ROLE)\b|@@(GLOBAL|PERSIST)/i', $s)
                ? $out + ['reason' => 'Changes server-wide settings, passwords or roles; not allowed.']
                : ['kind' => 'session', 'tables' => [], 'rows' => 0],
            preg_match('/^(LOCK\s+TABLES?|UNLOCK\s+TABLES?|START\s+TRANSACTION|BEGIN\s*$|BEGIN\s+WORK\s*$|COMMIT|ROLLBACK\s*$)/i', $s) === 1 => ['kind' => 'session', 'tables' => [], 'rows' => 0],
            preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?'.$name.'/i', $s, $m) === 1 => ['kind' => 'create', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^DROP\s+(?:TEMPORARY\s+)?TABLES?\s+(?:IF\s+EXISTS\s+)?(.+?)(?:\s+(?:RESTRICT|CASCADE))?$/i', $s, $m) === 1 => ['kind' => 'drop', 'tables' => $tablesFrom($m[1]), 'rows' => 0],
            preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?VIEW\s+(?:IF\s+NOT\s+EXISTS\s+)?'.$name.'/i', $s, $m) === 1 => ['kind' => 'view', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^DROP\s+VIEW\s+(?:IF\s+EXISTS\s+)?(.+?)(?:\s+(?:RESTRICT|CASCADE))?$/i', $s, $m) === 1 => ['kind' => 'drop', 'tables' => $tablesFrom($m[1]), 'rows' => 0],
            preg_match('/^ALTER\s+(?:ONLINE\s+|IGNORE\s+)*TABLE\s+'.$name.'/i', $s, $m) === 1 => ['kind' => 'alter', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?\S+\s+(?:USING\s+\w+\s+)?ON\s+'.$name.'/i', $s, $m) === 1 => ['kind' => 'alter', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^DROP\s+INDEX\s+(?:IF\s+EXISTS\s+)?\S+\s+ON\s+'.$name.'/i', $s, $m) === 1 => ['kind' => 'alter', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^RENAME\s+TABLES?\s+(.+)$/i', $s, $m) === 1 => ['kind' => 'rename', 'tables' => array_values(array_filter($tablesFrom($m[1]), fn ($t) => strtoupper($t) !== 'TO')), 'rows' => 0],
            preg_match('/^TRUNCATE\s+(?:TABLE\s+)?'.$name.'/i', $s, $m) === 1 => ['kind' => 'truncate', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^(?:INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*(?:INTO\s+)?'.$name.'/i', $s, $m) === 1 => ['kind' => 'insert', 'tables' => $one($m), 'rows' => $this->countRows($s)],
            preg_match('/^UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*'.$name.'/i', $s, $m) === 1 => ['kind' => 'update', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+'.$name.'/i', $s, $m) === 1 => ['kind' => 'delete', 'tables' => $one($m), 'rows' => 0],
            preg_match('/^DELIMITER\b/i', $s) === 1 => $out + ['reason' => 'DELIMITER blocks (stored procedures, functions, triggers, events) are not supported. Export without routines/triggers.'],
            default => $out + ['reason' => 'Statement type not allowed here ('.mb_substr($s, 0, 40).'…). Only table data and structure can be imported.'],
        };
    }

    /** Row tuples in an INSERT … VALUES (…),(…) — string contents are already blanked in the skeleton. */
    private function countRows(string $s): int
    {
        $pos = stripos($s, ' VALUES');
        if ($pos === false) {
            $pos = stripos($s, ' VALUE');
        }
        if ($pos === false) {
            return 1; // INSERT … SELECT / SET col=…
        }

        return 1 + preg_match_all('/\)\s*,\s*\(/', substr($s, $pos));
    }

    /** Database names on the server other than $target (plus system schemas and the master DB). */
    private function otherSchemas(string $target): array
    {
        $names = ['mysql', 'information_schema', 'performance_schema', 'sys', 'phpmyadmin',
            strtolower((string) config('database.connections.master.database'))];
        try {
            foreach ($this->manager->db()->select('SHOW DATABASES') as $row) {
                $names[] = strtolower((string) array_values((array) $row)[0]);
            }
        } catch (Throwable) {
            //
        }

        $out = [];
        foreach ($names as $n) {
            if ($n !== '' && $n !== $target) {
                $out[$n] = true;
            }
        }

        return $out;
    }

    private function stripDefiner(string $sql): string
    {
        return (string) preg_replace('/\bDEFINER\s*=\s*(?:`[^`]*`|\'[^\']*\'|[^\s@]+)\s*@\s*(?:`[^`]*`|\'[^\']*\'|[^\s*]+)/i', '', $sql);
    }

    private function headerDatabase(string $path): ?string
    {
        $fh = @gzopen($path, 'rb');
        if (! $fh) {
            return null;
        }
        $head = (string) gzread($fh, 16384);
        gzclose($fh);

        return preg_match('/^--.*\bDatabase:\s*`?([A-Za-z0-9_$]+)`?/mi', $head, $m) ? $m[1] : null;
    }

    private function unquote(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_starts_with($identifier, '`') && str_ends_with($identifier, '`') && strlen($identifier) >= 2) {
            return str_replace('``', '`', substr($identifier, 1, -1));
        }

        return $identifier;
    }

    private function snippet(string $sql): string
    {
        $one = preg_replace('/\s+/', ' ', mb_substr($sql, 0, 400));

        return mb_strlen($one) > 240 ? mb_substr($one, 0, 240).'…' : $one;
    }

    private function errorText(QueryException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $driver = $this->manager->driverMessage($e);
        $hint = match ($code) {
            1062 => 'A row with the same key already exists. ',
            1050 => 'The table already exists (the file has no DROP TABLE before CREATE TABLE). ',
            1146 => 'The table does not exist. ',
            1451, 1452, 1216, 1217 => 'Blocked by a foreign key. ',
            1153, 2006 => 'A statement is larger than the MySQL max_allowed_packet setting. ',
            1142, 1044 => 'The MySQL user is not allowed to do this on the school database. ',
            default => '',
        };

        return $hint.$driver;
    }

    // ── Streaming statement splitter ────────────────────────────────────────────────────────

    /**
     * Yields every statement of a .sql or .sql.gz file, without loading the file into memory.
     * Understands '…', "…" and `…` quoting (with \ and doubled-quote escapes), -- / # / block
     * comments (dropped) and MySQL /*! … *\/ executable comments (kept).
     *
     * @return \Generator<int, array{line: int, sql: string, skeleton: string}>
     *   sql: the text to execute; skeleton: the same with comments removed and string literal
     *   contents blanked, used only for checking what the statement does.
     */
    public function statements(string $path): \Generator
    {
        $fh = @gzopen($path, 'rb');
        if (! $fh) {
            throw new DatabaseManagerException('Cannot read the uploaded file.', 422);
        }

        try {
            $buf = '';
            $pos = 0;
            $eof = false;
            $first = true;
            $line = 1;
            $start = null;
            $sql = '';
            $skel = '';
            $state = 'code';
            $quote = '';
            $cond = false;
            $quoteLine = 0;

            while (true) {
                if (! $eof && $pos >= strlen($buf) - 4) {
                    $more = gzread($fh, 1048576);
                    if ($more === false || $more === '') {
                        $eof = true;
                    } else {
                        if ($first) {
                            $first = false;
                            if (str_starts_with($more, "\xEF\xBB\xBF")) {
                                $more = substr($more, 3);
                            }
                        }
                        $buf = substr($buf, $pos).$more;
                        $pos = 0;
                    }
                }
                $len = strlen($buf);
                if ($pos >= $len) {
                    break;
                }

                if ($state === 'code') {
                    $span = strcspn($buf, "'\"`;-#/*\n", $pos);
                    if ($span > 0) {
                        $chunk = substr($buf, $pos, $span);
                        if ($start === null && trim($chunk) !== '') {
                            $start = $line;
                            if (preg_match('/^\s*DELIMITER\s/i', $chunk)) {
                                throw new DatabaseManagerException("Line {$line}: DELIMITER blocks (stored procedures, functions, triggers, events) are not supported. Export the database without routines and triggers.", 422);
                            }
                        }
                        $sql .= $chunk;
                        $skel .= $chunk;
                        $pos += $span;

                        continue;
                    }
                    $ch = $buf[$pos];
                    $next = $buf[$pos + 1] ?? '';
                    if ($ch === "\n") {
                        $line++;
                        $sql .= "\n";
                        $skel .= "\n";
                        $pos++;
                    } elseif ($ch === "'" || $ch === '"' || $ch === '`') {
                        $start ??= $line;
                        $state = $ch === '`' ? 'ident' : 'string';
                        $quote = $ch;
                        $quoteLine = $line;
                        $sql .= $ch;
                        $skel .= $ch;
                        $pos++;
                    } elseif ($ch === ';' && ! $cond) {
                        if (trim($sql) !== '') {
                            yield ['line' => $start ?? $line, 'sql' => trim($sql), 'skeleton' => trim($skel)];
                        }
                        $sql = '';
                        $skel = '';
                        $start = null;
                        $pos++;
                    } elseif ($ch === '-' && $next === '-' && in_array($buf[$pos + 2] ?? "\n", [' ', "\t", "\n", "\r"], true)) {
                        $state = 'line';
                        $pos += 2;
                    } elseif ($ch === '#') {
                        $state = 'line';
                        $pos++;
                    } elseif ($ch === '/' && $next === '*') {
                        if (preg_match('/\G\/\*(M?!)(\d*)/', $buf, $m, 0, $pos)) {
                            $after = $pos + strlen($m[0]);
                            if (($buf[$after] ?? '') === '\\') {
                                // MariaDB "/*M!999999\- enable the sandbox mode */" — a client command.
                                $state = 'block';
                                $pos += 2;
                            } else {
                                $start ??= $line;
                                $cond = true;
                                $sql .= $m[0];
                                $skel .= ' ';
                                $pos = $after;
                            }
                        } else {
                            $state = 'block';
                            $pos += 2;
                        }
                    } elseif ($ch === '*' && $next === '/' && $cond) {
                        $cond = false;
                        $sql .= '*/';
                        $skel .= ' ';
                        $pos += 2;
                    } else {
                        $start ??= $line;
                        $sql .= $ch;
                        $skel .= $ch;
                        $pos++;
                    }
                } elseif ($state === 'string' || $state === 'ident') {
                    $stops = $state === 'string' ? $quote."\\\n" : "`\n";
                    $span = strcspn($buf, $stops, $pos);
                    if ($span > 0) {
                        $chunk = substr($buf, $pos, $span);
                        $sql .= $chunk;
                        if ($state === 'ident') {
                            $skel .= $chunk;
                        }
                        $pos += $span;

                        continue;
                    }
                    $ch = $buf[$pos];
                    if ($ch === "\n") {
                        $line++;
                        $sql .= "\n";
                        $pos++;
                    } elseif ($ch === '\\') {
                        $escaped = $buf[$pos + 1] ?? '';
                        if ($escaped === "\n") {
                            $line++;
                        }
                        $sql .= $ch.$escaped;
                        $pos += 2;
                    } elseif (($buf[$pos + 1] ?? '') === $quote) {
                        $sql .= $quote.$quote;
                        if ($state === 'ident') {
                            $skel .= $quote.$quote;
                        }
                        $pos += 2;
                    } else {
                        $sql .= $quote;
                        $skel .= $quote;
                        $state = 'code';
                        $pos++;
                    }
                } elseif ($state === 'line') {
                    $nl = strpos($buf, "\n", $pos);
                    if ($nl === false) {
                        $pos = $len;

                        continue;
                    }
                    $pos = $nl;
                    $state = 'code';
                } else { // block comment
                    $end = strpos($buf, '*/', $pos);
                    if ($end === false) {
                        $keep = max($pos, $len - 1);
                        $line += substr_count($buf, "\n", $pos, $keep - $pos);
                        $pos = $keep;
                        if ($eof) {
                            break;
                        }

                        continue;
                    }
                    $line += substr_count($buf, "\n", $pos, $end - $pos);
                    $sql .= ' ';
                    $skel .= ' ';
                    $pos = $end + 2;
                    $state = 'code';
                }
            }

            if ($state === 'string' || $state === 'ident') {
                throw new DatabaseManagerException("The file ends inside a quoted value that starts on line {$quoteLine}; it looks cut off or damaged.", 422);
            }
            if (trim($sql) !== '') {
                yield ['line' => $start ?? $line, 'sql' => trim($sql), 'skeleton' => trim($skel)];
            }
        } finally {
            gzclose($fh);
        }
    }
}
