<?php

namespace App\Services\Tenancy;

use App\Models\Master\School;
use DateTime;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin "phpMyAdmin-like" access to ONE registered school's MySQL database.
 *
 * Safety model:
 *  - The database name always comes from the school's master record (never from the request) and
 *    must not be the master DB, a MySQL system schema, or shared with another school.
 *  - Every table name is checked against the live table list of that database before use;
 *    every column name against that table's columns. Only then are identifiers quoted into SQL.
 *  - All record values go through bound parameters. There is no free-form SQL entry point.
 *  - Integrity rules are never bypassed: no FOREIGN_KEY_CHECKS=0, MySQL errors are reported as-is.
 */
class SchoolDatabaseManager
{
    public const CONNECTION = 'super_admin_school_db';

    public const PER_PAGE_OPTIONS = [10, 25, 50, 100, 250];

    /** Below this row estimate, the overview shows an exact COUNT(*) instead of the InnoDB estimate. */
    private const EXACT_COUNT_BELOW = 50000;

    private const BROWSE_TEXT_LIMIT = 300;

    private const SYSTEM_SCHEMAS = ['mysql', 'information_schema', 'performance_schema', 'sys', 'phpmyadmin'];

    private const BINARY_TYPES = [
        'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob',
        'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection',
    ];

    private const INT_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    private const DECIMAL_TYPES = ['decimal', 'numeric', 'float', 'double', 'real'];

    private const TEXT_BYTE_TYPES = ['tinytext', 'text', 'mediumtext', 'longtext'];

    public const FILTER_OPERATORS = ['=', '!=', 'contains', 'not_contains', 'starts_with', '>', '>=', '<', '<=', 'is_null', 'not_null'];

    private ?School $school = null;

    private ?array $tables = null;

    private array $columns = [];

    public function __construct(private TenantManager $tenants) {}

    /** Connects to $school's own database after checking it is a legitimate, existing tenant database. */
    public function open(School $school): static
    {
        $dbName = $this->assertManageable($school);

        $this->tenants->configureTemporaryConnection(self::CONNECTION, $dbName, $school->db_host ?: null);
        $this->school = $school;
        $this->tables = null;
        $this->columns = [];

        try {
            $exists = $this->db()->selectOne(
                'SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$dbName]
            );
        } catch (\Throwable $e) {
            throw new DatabaseManagerException("Cannot connect to database `{$dbName}`: ".$this->driverMessage($e), 503);
        }
        if (! $exists) {
            throw new DatabaseManagerException("Database `{$dbName}` does not exist on the MySQL server.", 404);
        }
        if ((string) ($this->db()->selectOne('SELECT DATABASE() AS db')->db ?? '') !== $dbName) {
            throw new DatabaseManagerException('Connected to the wrong database; request refused.', 500);
        }

        return $this;
    }

    /**
     * Why $school's database may not be managed, or null when it may. Used for the school list and by open().
     */
    public function blockedReason(School $school): ?string
    {
        $db = (string) $school->db_name;

        if ($school->status === 'provisioning') {
            return 'School is still being provisioned.';
        }
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $db)) {
            return 'School has an invalid database name.';
        }
        $lower = strtolower($db);
        if (in_array($lower, self::SYSTEM_SCHEMAS, true)) {
            return 'MySQL system databases cannot be managed here.';
        }
        if ($lower === strtolower((string) config('database.connections.master.database'))) {
            return 'The Super Admin master database cannot be managed here.';
        }
        $shared = School::query()
            ->whereRaw('LOWER(db_name) = ?', [$lower])
            ->where('id', '!=', $school->id)
            ->exists();
        if ($shared) {
            return 'Another school is registered with the same database name.';
        }

        return null;
    }

    private function assertManageable(School $school): string
    {
        if ($reason = $this->blockedReason($school)) {
            throw new DatabaseManagerException($reason, 403);
        }

        return (string) $school->db_name;
    }

    /**
     * Database existence / table count / size for every school, grouped per MySQL host.
     *
     * @return array<int, array{exists: bool, tables: int|null, size_bytes: int|null, error: string|null}>
     */
    public function statusForSchools(iterable $schools): array
    {
        $byHost = [];
        foreach ($schools as $school) {
            $byHost[(string) ($school->db_host ?: '')][] = $school;
        }

        $out = [];
        foreach ($byHost as $host => $list) {
            try {
                $this->tenants->configureTemporaryConnection(self::CONNECTION.'_status', 'information_schema', $host ?: null);
                $names = array_values(array_unique(array_map(fn ($s) => (string) $s->db_name, $list)));
                $marks = implode(',', array_fill(0, count($names), '?'));
                $schemas = collect(DB::connection(self::CONNECTION.'_status')->select(
                    "SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ({$marks})",
                    $names
                ))->pluck('name')->map(fn ($n) => strtolower($n))->all();
                $stats = collect(DB::connection(self::CONNECTION.'_status')->select(
                    "SELECT TABLE_SCHEMA AS db, COUNT(*) AS tables_count, SUM(COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0)) AS size_bytes
                     FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ({$marks}) GROUP BY TABLE_SCHEMA",
                    $names
                ))->keyBy(fn ($r) => strtolower($r->db));

                foreach ($list as $school) {
                    $key = strtolower((string) $school->db_name);
                    $exists = in_array($key, $schemas, true);
                    $out[$school->id] = [
                        'exists' => $exists,
                        'tables' => $exists ? (int) ($stats[$key]->tables_count ?? 0) : null,
                        'size_bytes' => $exists ? (int) ($stats[$key]->size_bytes ?? 0) : null,
                        'error' => null,
                    ];
                }
            } catch (\Throwable $e) {
                foreach ($list as $school) {
                    $out[$school->id] = ['exists' => false, 'tables' => null, 'size_bytes' => null, 'error' => $this->driverMessage($e)];
                }
            } finally {
                DB::purge(self::CONNECTION.'_status');
            }
        }

        return $out;
    }

    public function db(): Connection
    {
        if (! $this->school) {
            throw new DatabaseManagerException('No school database is open.', 500);
        }

        return DB::connection(self::CONNECTION);
    }

    public function school(): School
    {
        return $this->school;
    }

    /** Database name, server version, size and every table/view with its row count. */
    public function overview(): array
    {
        $tables = $this->tables();
        foreach ($tables as &$t) {
            if (! $t['is_view'] && $t['rows_estimate'] < self::EXACT_COUNT_BELOW) {
                $t['rows'] = (int) $this->db()->table($t['name'])->count();
                $t['rows_exact'] = true;
            } else {
                $t['rows'] = $t['is_view'] ? null : $t['rows_estimate'];
                $t['rows_exact'] = false;
            }
        }
        unset($t);

        return [
            'db_name' => $this->school->db_name,
            'server_version' => (string) ($this->db()->selectOne('SELECT VERSION() AS v')->v ?? ''),
            'size_bytes' => array_sum(array_map(fn ($t) => $t['data_length'] + $t['index_length'], $tables)),
            'tables' => $tables,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function tables(): array
    {
        if ($this->tables !== null) {
            return $this->tables;
        }

        $rows = $this->db()->select(
            'SELECT TABLE_NAME AS name, TABLE_TYPE AS type, ENGINE AS engine, TABLE_ROWS AS rows_estimate,
                    DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length, AUTO_INCREMENT AS auto_increment,
                    TABLE_COLLATION AS collation, TABLE_COMMENT AS comment
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$this->school->db_name]
        );

        return $this->tables = array_map(fn ($r) => [
            'name' => (string) $r->name,
            'is_view' => strtoupper((string) $r->type) === 'VIEW',
            'engine' => $r->engine,
            'rows_estimate' => (int) $r->rows_estimate,
            'data_length' => (int) $r->data_length,
            'index_length' => (int) $r->index_length,
            'auto_increment' => $r->auto_increment !== null ? (int) $r->auto_increment : null,
            'collation' => $r->collation,
            'comment' => (string) $r->comment,
        ], $rows);
    }

    /** The table's metadata, or a 404 when $table is not an existing table/view of this database. */
    public function assertTable(string $table, bool $writable = false): array
    {
        foreach ($this->tables() as $t) {
            if ($t['name'] === $table) {
                if ($writable && $t['is_view']) {
                    throw new DatabaseManagerException("`{$table}` is a view; it can only be browsed.", 422);
                }

                return $t;
            }
        }

        throw new DatabaseManagerException("Table `{$table}` does not exist in `{$this->school->db_name}`.", 404);
    }

    /** @return list<array<string, mixed>> */
    public function columns(string $table): array
    {
        $this->assertTable($table);
        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $rows = $this->db()->select(
            'SELECT COLUMN_NAME AS name, COLUMN_DEFAULT AS default_value, IS_NULLABLE AS nullable, DATA_TYPE AS data_type,
                    COLUMN_TYPE AS column_type, CHARACTER_MAXIMUM_LENGTH AS max_length, COLUMN_KEY AS column_key,
                    EXTRA AS extra, COLUMN_COMMENT AS comment
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$this->school->db_name, $table]
        );

        return $this->columns[$table] = array_map(function ($r) {
            $type = strtolower((string) $r->data_type);
            $columnType = (string) $r->column_type;
            $extra = strtolower((string) $r->extra);
            $default = $r->default_value;
            // MariaDB reports a NULL default as the string 'NULL' and quotes string defaults.
            if ($default === 'NULL') {
                $default = null;
            } elseif (is_string($default) && preg_match("/^'(.*)'$/s", $default, $m)) {
                $default = str_replace("''", "'", $m[1]);
            }
            $options = [];
            if (in_array($type, ['enum', 'set'], true) && preg_match('/^\w+\((.*)\)$/s', $columnType, $m)) {
                preg_match_all("/'((?:[^']|'')*)'/", $m[1], $mm);
                $options = array_map(fn ($o) => str_replace("''", "'", $o), $mm[1]);
            }

            return [
                'name' => (string) $r->name,
                'data_type' => $type,
                'column_type' => $columnType,
                'nullable' => strtoupper((string) $r->nullable) === 'YES',
                'default' => $default,
                'has_default' => $r->default_value !== null,
                'max_length' => $r->max_length !== null ? (int) $r->max_length : null,
                'key' => (string) $r->column_key,
                'extra' => (string) $r->extra,
                'auto_increment' => str_contains($extra, 'auto_increment'),
                'generated' => str_contains($extra, 'generated') || str_contains($extra, 'virtual') || str_contains($extra, 'persistent'),
                'binary' => in_array($type, self::BINARY_TYPES, true),
                'unsigned' => str_contains(strtolower($columnType), 'unsigned'),
                'options' => $options,
                'comment' => (string) $r->comment,
            ];
        }, $rows);
    }

    /** @return list<string> */
    public function primaryKey(string $table): array
    {
        $this->assertTable($table);

        return array_map(fn ($r) => (string) $r->name, $this->db()->select(
            "SELECT COLUMN_NAME AS name FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX",
            [$this->school->db_name, $table]
        ));
    }

    /** Columns, indexes, outgoing and incoming foreign keys and the CREATE statement. */
    public function structure(string $table): array
    {
        $meta = $this->assertTable($table);

        $indexes = [];
        foreach ($this->db()->select(
            'SELECT INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS column_name, INDEX_TYPE AS type, SUB_PART AS sub_part
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->school->db_name, $table]
        ) as $r) {
            $indexes[$r->name] ??= [
                'name' => $r->name,
                'unique' => ! (int) $r->non_unique,
                'primary' => $r->name === 'PRIMARY',
                'type' => $r->type,
                'columns' => [],
            ];
            $indexes[$r->name]['columns'][] = $r->column_name.($r->sub_part ? "({$r->sub_part})" : '');
        }

        $create = (array) $this->db()->selectOne('SHOW CREATE TABLE '.$this->q($table));

        return [
            'table' => $meta,
            'columns' => $this->columns($table),
            'primary_key' => $this->primaryKey($table),
            'indexes' => array_values($indexes),
            'foreign_keys' => $this->foreignKeys($table),
            'referenced_by' => $this->referencedBy($table),
            'create_sql' => (string) ($create['Create Table'] ?? $create['Create View'] ?? ''),
        ];
    }

    /** Foreign keys declared on $table (this table → others). */
    public function foreignKeys(string $table): array
    {
        return $this->groupForeignKeys($this->db()->select(
            'SELECT k.CONSTRAINT_NAME AS name, k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name,
                    k.REFERENCED_TABLE_NAME AS ref_table, k.REFERENCED_COLUMN_NAME AS ref_column,
                    r.UPDATE_RULE AS on_update, r.DELETE_RULE AS on_delete
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$this->school->db_name, $table]
        ));
    }

    /** Foreign keys in other tables that point at $table (others → this table). */
    public function referencedBy(string $table): array
    {
        return $this->groupForeignKeys($this->db()->select(
            'SELECT k.CONSTRAINT_NAME AS name, k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name,
                    k.REFERENCED_TABLE_NAME AS ref_table, k.REFERENCED_COLUMN_NAME AS ref_column,
                    r.UPDATE_RULE AS on_update, r.DELETE_RULE AS on_delete
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = ?
             ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$this->school->db_name, $this->school->db_name, $table]
        ));
    }

    private function groupForeignKeys(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $key = $r->table_name.'.'.$r->name;
            $out[$key] ??= [
                'name' => $r->name,
                'table' => $r->table_name,
                'columns' => [],
                'ref_table' => $r->ref_table,
                'ref_columns' => [],
                'on_update' => $r->on_update,
                'on_delete' => $r->on_delete,
            ];
            $out[$key]['columns'][] = $r->column_name;
            $out[$key]['ref_columns'][] = $r->ref_column;
        }

        return array_values($out);
    }

    /**
     * One page of records with optional full-text-ish search, column filters and sorting.
     *
     * @param  array{page?: mixed, per_page?: mixed, search?: mixed, sort?: mixed, dir?: mixed, filters?: mixed}  $opts
     */
    public function rows(string $table, array $opts): array
    {
        $meta = $this->assertTable($table);
        $columns = $this->columns($table);
        $names = array_column($columns, 'name');
        $pk = $meta['is_view'] ? [] : $this->primaryKey($table);

        $perPage = (int) ($opts['per_page'] ?? 25);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
        $page = max(1, (int) ($opts['page'] ?? 1));

        $query = $this->db()->table($table);

        $search = trim((string) ($opts['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$this->escapeLike($search).'%';
            $searchable = array_filter($columns, fn ($c) => ! $c['binary']);
            $query->where(function (Builder $q) use ($searchable, $like) {
                foreach ($searchable as $c) {
                    $q->orWhereRaw('CAST('.$this->q($c['name']).' AS CHAR) LIKE ?', [$like]);
                }
            });
        }

        $filters = is_array($opts['filters'] ?? null) ? array_slice($opts['filters'], 0, 10) : [];
        foreach ($filters as $f) {
            $this->applyFilter($query, $names, is_array($f) ? $f : []);
        }

        $total = (clone $query)->count();

        $sort = (string) ($opts['sort'] ?? '');
        $dir = strtolower((string) ($opts['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sort !== '') {
            if (! in_array($sort, $names, true)) {
                throw new DatabaseManagerException("Unknown column `{$sort}`.", 422);
            }
            $query->orderBy($sort, $dir);
        }
        foreach ($pk as $col) {
            if ($col !== $sort) {
                $query->orderBy($col);
            }
        }

        $records = $query->forPage($page, $perPage)->get();

        return [
            'table' => $meta,
            'columns' => $columns,
            'primary_key' => $pk,
            'editable' => $this->isEditable($table, $meta, $pk),
            'rows' => $records->map(fn ($r) => $this->presentRow((array) $r, $columns, $pk, true))->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function applyFilter(Builder $query, array $names, array $f): void
    {
        $column = (string) ($f['column'] ?? '');
        $op = (string) ($f['op'] ?? '=');
        $value = $f['value'] ?? null;
        if ($column === '') {
            return;
        }
        if (! in_array($column, $names, true)) {
            throw new DatabaseManagerException("Unknown filter column `{$column}`.", 422);
        }
        if (! in_array($op, self::FILTER_OPERATORS, true)) {
            throw new DatabaseManagerException("Unknown filter operator `{$op}`.", 422);
        }
        if (! in_array($op, ['is_null', 'not_null'], true) && ! is_scalar($value)) {
            throw new DatabaseManagerException("Filter on `{$column}` needs a value.", 422);
        }

        $value = (string) $value;
        $cast = 'CAST('.$this->q($column).' AS CHAR)';
        match ($op) {
            'is_null' => $query->whereNull($column),
            'not_null' => $query->whereNotNull($column),
            'contains' => $query->whereRaw("{$cast} LIKE ?", ['%'.$this->escapeLike($value).'%']),
            'not_contains' => $query->where(fn ($q) => $q->whereNull($column)->orWhereRaw("{$cast} NOT LIKE ?", ['%'.$this->escapeLike($value).'%'])),
            'starts_with' => $query->whereRaw("{$cast} LIKE ?", [$this->escapeLike($value).'%']),
            default => $query->where($column, $op, $value),
        };
    }

    /** A single full record (no truncation) identified by its primary key. */
    public function find(string $table, array $key): array
    {
        $this->assertTable($table, true);
        $pk = $this->requirePrimaryKey($table);
        $row = $this->whereKey($this->db()->table($table), $pk, $key)->first();
        if (! $row) {
            throw new DatabaseManagerException('Record not found (it may have been changed or deleted).', 404);
        }

        return [
            'columns' => $this->columns($table),
            'primary_key' => $pk,
            'record' => $this->presentRow((array) $row, $this->columns($table), $pk, false),
        ];
    }

    /** @return array{key: array<string, mixed>, values: array<string, mixed>} */
    public function insert(string $table, array $values): array
    {
        $this->assertTable($table, true);
        $columns = $this->columns($table);
        $data = $this->prepareValues($columns, $values, true);

        $missing = [];
        foreach ($columns as $c) {
            if (! array_key_exists($c['name'], $data) && ! $c['nullable'] && ! $c['has_default'] && ! $c['auto_increment'] && ! $c['generated']) {
                $missing[$c['name']] = "{$c['name']} is required (NOT NULL without a default).";
            }
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        $pk = $this->primaryKey($table);
        $this->db()->table($table)->insert($data);
        // Read the generated id straight away: any later statement resets it to 0.
        $insertId = (string) $this->db()->getPdo()->lastInsertId();

        $key = [];
        foreach ($pk as $col) {
            $c = $this->column($columns, $col);
            $key[$col] = array_key_exists($col, $data) ? $data[$col] : ($c['auto_increment'] ? $insertId : null);
        }

        return ['key' => $key, 'values' => $data];
    }

    /** @return array{key: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>} */
    public function update(string $table, array $key, array $values): array
    {
        $this->assertTable($table, true);
        $pk = $this->requirePrimaryKey($table);
        $columns = $this->columns($table);

        $current = $this->whereKey($this->db()->table($table), $pk, $key)->first();
        if (! $current) {
            throw new DatabaseManagerException('Record not found (it may have been changed or deleted).', 404);
        }
        $current = (array) $current;
        $data = $this->prepareValues($columns, $values, false);

        $before = [];
        $after = [];
        foreach ($data as $col => $value) {
            $old = $current[$col] ?? null;
            if ($old === null && $value === null) {
                continue;
            }
            if ($old !== null && $value !== null && (string) $old === (string) $value) {
                continue;
            }
            $before[$col] = $old;
            $after[$col] = $value;
        }

        if ($after) {
            $this->whereKey($this->db()->table($table), $pk, $key)->limit(1)->update($after);
        }

        $newKey = [];
        foreach ($pk as $col) {
            $newKey[$col] = array_key_exists($col, $after) ? $after[$col] : $current[$col];
        }

        return ['key' => $newKey, 'before' => $before, 'after' => $after];
    }

    /** Deletes the records with the given primary keys in one transaction (all or nothing). */
    public function deleteRecords(string $table, array $keys): int
    {
        $this->assertTable($table, true);
        $pk = $this->requirePrimaryKey($table);
        if (! $keys || count($keys) > 500) {
            throw new DatabaseManagerException('Select between 1 and 500 records to delete.', 422);
        }

        return $this->db()->transaction(function () use ($table, $pk, $keys) {
            $deleted = 0;
            foreach ($keys as $key) {
                $deleted += $this->whereKey($this->db()->table($table), $pk, is_array($key) ? $key : [])->limit(1)->delete();
            }

            return $deleted;
        });
    }

    /** DELETE FROM — keeps structure and AUTO_INCREMENT; obeys ON DELETE rules of referencing tables. */
    public function emptyTable(string $table): int
    {
        $this->assertTable($table, true);

        return $this->db()->transaction(fn () => $this->db()->table($table)->delete());
    }

    /** TRUNCATE TABLE — fast, resets AUTO_INCREMENT; MySQL refuses it when other tables reference this one. */
    public function truncateTable(string $table): int
    {
        $this->assertTable($table, true);
        $rows = (int) $this->db()->table($table)->count();
        $this->db()->statement('TRUNCATE TABLE '.$this->q($table));
        $this->tables = null;

        return $rows;
    }

    /** DROP TABLE — removes structure and data; MySQL refuses it while foreign keys reference this table. */
    public function dropTable(string $table): int
    {
        $this->assertTable($table, true);
        $rows = (int) $this->db()->table($table)->count();
        $this->db()->statement('DROP TABLE '.$this->q($table));
        $this->tables = null;
        unset($this->columns[$table]);

        return $rows;
    }

    /**
     * Writes a restorable SQL dump of one table (or the whole database) through $write.
     * FOREIGN_KEY_CHECKS=0 appears only inside the dump file so it can be restored in any table order.
     */
    public function dump(?string $table, callable $write): void
    {
        $tables = $table !== null ? [$this->assertTable($table)] : $this->tables();
        usort($tables, fn ($a, $b) => [$a['is_view'], $a['name']] <=> [$b['is_view'], $b['name']]);

        $write("-- ERPSaathi Super Admin backup\n");
        $write('-- School: '.$this->school->name.' ('.$this->school->slug.")\n");
        $write('-- Database: '.$this->school->db_name.($table !== null ? "  Table: {$table}" : '')."\n");
        $write('-- Generated: '.now()->toDateTimeString()."\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $pdo = $this->db()->getPdo();
        foreach ($tables as $t) {
            $name = $t['name'];
            $create = (array) $this->db()->selectOne('SHOW CREATE TABLE '.$this->q($name));
            if ($t['is_view']) {
                $sql = preg_replace('/\sDEFINER=\S+/i', '', (string) ($create['Create View'] ?? ''));
                $write('DROP VIEW IF EXISTS '.$this->q($name).";\n{$sql};\n\n");

                continue;
            }

            $write('DROP TABLE IF EXISTS '.$this->q($name).";\n".($create['Create Table'] ?? '').";\n\n");

            $columns = array_values(array_filter($this->columns($name), fn ($c) => ! $c['generated']));
            if (! $columns) {
                continue;
            }
            $colList = implode(', ', array_map(fn ($c) => $this->q($c['name']), $columns));
            $pk = $this->primaryKey($name);
            $page = 1;
            do {
                $query = $this->db()->table($name)->select(array_column($columns, 'name'));
                foreach ($pk as $col) {
                    $query->orderBy($col);
                }
                $chunk = $query->forPage($page++, 500)->get();
                if ($chunk->isEmpty()) {
                    break;
                }
                $values = $chunk->map(function ($row) use ($columns, $pdo) {
                    $row = (array) $row;

                    return '('.implode(', ', array_map(function ($c) use ($row, $pdo) {
                        $v = $row[$c['name']] ?? null;
                        if ($v === null) {
                            return 'NULL';
                        }
                        if ($c['binary']) {
                            return $v === '' ? "''" : '0x'.bin2hex((string) $v);
                        }
                        if ((in_array($c['data_type'], self::INT_TYPES, true) || in_array($c['data_type'], self::DECIMAL_TYPES, true)) && is_numeric($v)) {
                            return (string) $v;
                        }

                        return $pdo->quote((string) $v);
                    }, $columns)).')';
                })->implode(",\n");
                $write('INSERT INTO '.$this->q($name)." ({$colList}) VALUES\n{$values};\n");
            } while ($chunk->count() === 500);
            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** Validates and normalises submitted values against the column definitions. */
    private function prepareValues(array $columns, array $values, bool $insert): array
    {
        $byName = array_column($columns, null, 'name');
        $data = [];
        $errors = [];

        foreach ($values as $name => $value) {
            $c = $byName[$name] ?? null;
            if (! $c) {
                $errors[$name] = "Unknown column `{$name}`.";

                continue;
            }
            if ($c['generated']) {
                $errors[$name] = "{$name} is a generated column and cannot be set.";

                continue;
            }
            if ($c['binary']) {
                $errors[$name] = "{$name} is a binary column and cannot be edited here.";

                continue;
            }
            if ($value === null) {
                if (! $c['nullable']) {
                    $errors[$name] = "{$name} cannot be NULL.";

                    continue;
                }
                $data[$name] = null;

                continue;
            }
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (! is_scalar($value)) {
                $errors[$name] = "{$name} must be a single value.";

                continue;
            }
            $value = (string) $value;
            if ($insert && $value === '' && $c['auto_increment']) {
                continue;
            }

            $error = null;
            $data[$name] = $this->normaliseValue($c, $value, $error);
            if ($error !== null) {
                $errors[$name] = $error;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function normaliseValue(array $c, string $value, ?string &$error): string
    {
        $type = $c['data_type'];
        $name = $c['name'];

        if (in_array($type, self::INT_TYPES, true)) {
            if (! preg_match('/^-?\d+$/', trim($value)) || ($c['unsigned'] && str_starts_with(trim($value), '-'))) {
                $error = "{$name} must be a whole number".($c['unsigned'] ? ' (0 or more)' : '').'.';
            }

            return trim($value);
        }
        if (in_array($type, self::DECIMAL_TYPES, true)) {
            if (! is_numeric(trim($value))) {
                $error = "{$name} must be a number.";
            }

            return trim($value);
        }
        if ($type === 'date') {
            if (! $this->validDate($value, ['Y-m-d'])) {
                $error = "{$name} must be a date (YYYY-MM-DD).";
            }

            return $value;
        }
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            $value = str_replace('T', ' ', trim($value));
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
                $value .= ':00';
            }
            if (! $this->validDate(preg_replace('/\.\d+$/', '', $value), ['Y-m-d H:i:s'])) {
                $error = "{$name} must be a date and time (YYYY-MM-DD HH:MM:SS).";
            }

            return $value;
        }
        if ($type === 'time' && ! preg_match('/^-?\d{1,3}:\d{2}(:\d{2}(\.\d+)?)?$/', trim($value))) {
            $error = "{$name} must be a time (HH:MM:SS).";
        }
        if ($type === 'year' && ! preg_match('/^\d{4}$/', trim($value))) {
            $error = "{$name} must be a 4-digit year.";
        }
        if ($type === 'enum' && ! in_array($value, $c['options'], true)) {
            $error = "{$name} must be one of: ".implode(', ', $c['options']).'.';
        }
        if ($type === 'set' && $value !== '' && array_diff(explode(',', $value), $c['options'])) {
            $error = "{$name} may only contain: ".implode(', ', $c['options']).'.';
        }
        if ($type === 'json' && json_decode($value) === null && strtolower(trim($value)) !== 'null') {
            $error = "{$name} must be valid JSON.";
        }
        if ($c['max_length'] !== null && in_array($type, ['char', 'varchar'], true) && mb_strlen($value) > $c['max_length']) {
            $error = "{$name} may be at most {$c['max_length']} characters.";
        }
        if ($c['max_length'] !== null && in_array($type, self::TEXT_BYTE_TYPES, true) && strlen($value) > $c['max_length']) {
            $error = "{$name} is too long for a {$type} column.";
        }

        return $value;
    }

    private function validDate(string $value, array $formats): bool
    {
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat('!'.$format, $value);
            if ($d && $d->format($format) === $value) {
                return true;
            }
        }
        // MySQL zero dates are legal stored values in older data.
        return str_starts_with($value, '0000-00-00');
    }

    /** Values safe for JSON: binary → placeholder, long text → truncated (browse only), plus a row key. */
    private function presentRow(array $row, array $columns, array $pk, bool $truncate): array
    {
        $values = [];
        $meta = [];
        foreach ($columns as $c) {
            $v = $row[$c['name']] ?? null;
            if ($v !== null && ($c['binary'] || ! mb_check_encoding((string) $v, 'UTF-8'))) {
                $values[$c['name']] = '(binary, '.strlen((string) $v).' bytes)';
                $meta[$c['name']] = 'binary';

                continue;
            }
            if ($truncate && is_string($v) && ! in_array($c['name'], $pk, true) && mb_strlen($v) > self::BROWSE_TEXT_LIMIT) {
                $values[$c['name']] = mb_substr($v, 0, self::BROWSE_TEXT_LIMIT);
                $meta[$c['name']] = 'truncated';

                continue;
            }
            $values[$c['name']] = $v;
        }

        $key = $pk ? array_intersect_key($row, array_flip($pk)) : null;

        return ['key' => $key, 'values' => $values, 'meta' => (object) $meta];
    }

    private function isEditable(string $table, array $meta, array $pk): bool
    {
        if ($meta['is_view'] || ! $pk) {
            return false;
        }
        foreach ($this->columns($table) as $c) {
            if (in_array($c['name'], $pk, true) && $c['binary']) {
                return false;
            }
        }

        return true;
    }

    private function requirePrimaryKey(string $table): array
    {
        $pk = $this->primaryKey($table);
        if (! $pk) {
            throw new DatabaseManagerException("`{$table}` has no primary key, so single records cannot be safely identified for edit or delete.", 422);
        }

        return $pk;
    }

    private function whereKey(Builder $query, array $pk, array $key): Builder
    {
        if (array_diff($pk, array_keys($key)) || count($key) !== count($pk)) {
            throw new DatabaseManagerException('Record key must contain exactly: '.implode(', ', $pk).'.', 422);
        }
        foreach ($pk as $col) {
            if (! is_scalar($key[$col])) {
                throw new DatabaseManagerException('Invalid record key.', 422);
            }
            $query->where($col, '=', (string) $key[$col]);
        }

        return $query;
    }

    private function column(array $columns, string $name): array
    {
        foreach ($columns as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }

        return [];
    }

    /** Quotes an identifier that has ALREADY been validated against the live schema. */
    private function q(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function driverMessage(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException && ! empty($e->errorInfo[2])) {
            return (string) $e->errorInfo[2];
        }

        return $e->getPrevious() instanceof \PDOException ? $e->getPrevious()->getMessage() : $e->getMessage();
    }
}
