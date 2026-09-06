<?php

namespace App\Providers;

use App\Services\Tenancy\TenantManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantManager::class);

        $this->app->singleton('tenant', function () {
            return $this->app->make(TenantContext::class)->get();
        });
    }

    public function boot(): void
    {
        //
    }
}
