<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DatabaseBackupController extends Controller
{
    public function index()
    {
        $dir = $this->backupDir();
        File::ensureDirectoryExists($dir);

        $files = collect(File::files($dir))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.sql'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'size_human' => $this->humanSize($file->getSize()),
                'created_at' => date('c', $file->getMTime()),
            ]);

        return response()->json([
            'database' => $this->connection()['database'] ?? null,
            'backups' => $files,
        ]);
    }

    public function store()
    {
        $connection = $this->connection();
        $driver = $connection['driver'] ?? config('database.default');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return response()->json([
                'message' => 'Database backups are only supported for MySQL / MariaDB connections.',
            ], 422);
        }

        $dumpBinary = $this->resolveMysqldump();
        if (! $dumpBinary) {
            return response()->json([
                'message' => 'mysqldump was not found. Install MySQL client tools or set MYSQLDUMP_PATH in .env (e.g. C:\\xampp\\mysql\\bin\\mysqldump.exe).',
            ], 422);
        }

        $dir = $this->backupDir();
        File::ensureDirectoryExists($dir);

        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($connection['database'] ?? 'database')) ?: 'database';
        $filename = sprintf('%s_backup_%s.sql', $dbName, now()->format('Ymd_His'));
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $command = [
            $dumpBinary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $connection['database'],
        ];

        $result = Process::env([
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ])->timeout(900)->run($command);

        if (! $result->successful() || ! File::isFile($path) || File::size($path) < 1) {
            if (File::exists($path)) {
                File::delete($path);
            }

            return response()->json([
                'message' => 'Backup failed: '.trim($result->errorOutput() ?: $result->output() ?: 'unknown mysqldump error'),
            ], 500);
        }

        return response()->json([
            'message' => 'Backup created successfully.',
            'backup' => [
                'name' => $filename,
                'size' => File::size($path),
                'size_human' => $this->humanSize(File::size($path)),
                'created_at' => date('c', File::lastModified($path)),
            ],
        ], 201);
    }

    public function download(string $filename)
    {
        $path = $this->safePath($filename);

        return response()->streamDownload(function () use ($path) {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function destroy(string $filename)
    {
        $path = $this->safePath($filename);
        File::delete($path);

        return response()->json(['success' => true]);
    }

    protected function backupDir(): string
    {
        return storage_path('app/backups');
    }

    protected function connection(): array
    {
        $name = config('database.default');

        return (array) config("database.connections.{$name}", []);
    }

    protected function resolveMysqldump(): ?string
    {
        $configured = env('MYSQLDUMP_PATH');
        if (is_string($configured) && $configured !== '' && File::exists($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        $which = Process::run([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'mysqldump']);
        if ($which->successful()) {
            $line = trim(explode("\n", str_replace("\r", '', $which->output()))[0] ?? '');
            if ($line !== '' && File::exists($line)) {
                return $line;
            }
        }

        return null;
    }

    protected function safePath(string $filename): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.sql$/', $filename)) {
            abort(404);
        }

        $path = $this->backupDir().DIRECTORY_SEPARATOR.$filename;
        if (! File::isFile($path)) {
            abort(404);
        }

        return $path;
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
