<?php

namespace App\Services\Tenancy;

use App\Models\Master\School;
use App\Models\Master\SchoolDomain;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TenantManager
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function context(): TenantContext
    {
        return $this->context;
    }

    public function isCentralHost(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $central = array_map('strtolower', config('tenancy.central_domains', []));

        if (in_array($host, $central, true)) {
            return true;
        }

        // admin.{base_domain}
        $base = strtolower((string) config('tenancy.base_domain'));
        if ($base !== '' && $host === 'admin.'.$base) {
            return true;
        }

        return false;
    }

    public function resolveSlugFromRequest(Request $request): ?string
    {
        $header = $request->header(config('tenancy.header_name', 'X-Tenant'));
        if (is_string($header) && $header !== '') {
            return Str::slug($header);
        }

        $paramName = config('tenancy.query_parameter', 'school');
        $param = $request->query($paramName);
        if (is_string($param) && $param !== '') {
            return Str::slug($param);
        }

        // POST body / JSON (login & other forms often drop ?school=)
        $input = $request->input($paramName);
        if (is_string($input) && $input !== '') {
            return Str::slug($input);
        }

        $host = strtolower($request->getHost());
        $base = strtolower((string) config('tenancy.base_domain'));

        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $sub = substr($host, 0, -strlen('.'.$base));
            if ($sub !== '' && $sub !== 'admin' && ! str_contains($sub, '.')) {
                return Str::slug($sub);
            }
        }

        // *.localhost style (childrenschool.localhost)
        if (str_ends_with($host, '.localhost')) {
            $sub = substr($host, 0, -strlen('.localhost'));
            if ($sub !== '' && $sub !== 'admin' && ! str_contains($sub, '.')) {
                return Str::slug($sub);
            }
        }

        $cookie = $request->cookie(config('tenancy.cookie_name', 'tenant'));
        if (is_string($cookie) && $cookie !== '') {
            return Str::slug($cookie);
        }

        return null;
    }

    public function findBySlug(string $slug): ?School
    {
        return School::query()->where('slug', $slug)->first();
    }

    public function findByDomain(string $domain): ?School
    {
        $domain = strtolower($domain);
        $row = SchoolDomain::query()->where('domain', $domain)->first();

        return $row?->school;
    }

    /**
     * Switch default mysql connection to the school's database and apply isolation side-effects.
     */
    public function initialize(School $school): void
    {
        if (! in_array($school->status, ['active', 'provisioning'], true)) {
            throw new RuntimeException('School is not available.');
        }

        $connection = config('database.default', 'mysql');
        if ($connection === 'sqlite') {
            $connection = 'mysql';
        }

        $host = $school->db_host ?: config("database.connections.{$connection}.host");

        Config::set("database.connections.{$connection}.database", $school->db_name);
        Config::set("database.connections.{$connection}.host", $host);

        DB::purge($connection);
        DB::reconnect($connection);
        Config::set('database.default', $connection);

        $this->context->set($school);
        $this->context->setCentral(false);

        $this->applyStorage($school);
        $this->applyCachePrefix($school);
        $this->applySessionCookie($school);

        app()->instance('tenant', $school);
    }

    /**
     * Public apex (erpsaathi.com) — no tenant DB; avoid default mysql sessions/cache.
     */
    public function usePublic(): void
    {
        $this->context->setCentral(false);
        $this->context->set(null);
        app()->instance('tenant', null);

        $this->applyNonTenantSessionAndCache('public');
    }

    /**
     * Super Admin / central host — master models only; file sessions/cache.
     */
    public function useCentral(): void
    {
        $this->context->setCentral(true);
        $this->context->set(null);
        app()->instance('tenant', null);

        $this->applyNonTenantSessionAndCache('central');
    }

    /**
     * File session + file cache so public/central hosts do not need DB_DATABASE.
     */
    protected function applyNonTenantSessionAndCache(string $scope): void
    {
        $app = Str::slug((string) env('APP_NAME', 'laravel'), '_');

        Config::set('session.driver', 'file');
        Config::set('session.connection', null);
        Config::set('session.cookie', $app.'_'.$scope.'_session');

        Config::set('cache.default', 'file');
        Config::set(
            'cache.prefix',
            (env('CACHE_PREFIX') ?: $app.'_cache_').$scope.'_'
        );

        $this->forgetCacheDriver();
    }

    protected function applyStorage(School $school): void
    {
        $root = $school->storageRoot();

        if (
            $school->is_first_school
            && config('tenancy.legacy_storage_for_first_school')
            && is_dir(storage_path('app/private'))
        ) {
            $root = storage_path('app/private');
        }

        if (! is_dir($root)) {
            @mkdir($root, 0755, true);
        }

        // Default "local" disk is what existing ERP private uploads use.
        Config::set('filesystems.disks.local.root', $root);
        Config::set('filesystems.disks.tenant.root', $root);
        Storage::forgetDisk('local');
        Storage::forgetDisk('tenant');
    }

    protected function applyCachePrefix(School $school): void
    {
        $base = env('CACHE_PREFIX') ?: Str::slug((string) env('APP_NAME', 'laravel'), '_').'_cache_';
        // File cache with tenant prefix — no dependency on default DB existing.
        Config::set('cache.default', 'file');
        Config::set('cache.prefix', $base.'tenant_'.$school->slug.'_');

        $this->forgetCacheDriver();
    }

    protected function applySessionCookie(School $school): void
    {
        // Per-tenant cookie + sessions table inside the switched tenant DB.
        Config::set('session.driver', 'database');
        Config::set('session.cookie', 'erp_session_'.$school->slug);
        Config::set('session.connection', config('database.default'));
    }

    protected function forgetCacheDriver(): void
    {
        if (! app()->bound('cache')) {
            return;
        }

        try {
            app('cache')->forgetDriver(config('cache.default'));
        } catch (\Throwable) {
            //
        }
    }

    public function configureTemporaryConnection(string $name, string $database, ?string $host = null): void
    {
        $base = config('database.connections.mysql');
        $base['database'] = $database;
        if ($host) {
            $base['host'] = $host;
        }
        Config::set("database.connections.{$name}", $base);
        DB::purge($name);
    }
}
