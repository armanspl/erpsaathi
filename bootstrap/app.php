<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/super_admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'erp.auth' => App\Http\Middleware\RedirectErp::class,
            'erp.permission' => App\Http\Middleware\CheckErpPermission::class,
            'tenant' => App\Http\Middleware\IdentifyTenant::class,
            'central' => App\Http\Middleware\EnsureCentralDomain::class,
            'super_admin.auth' => App\Http\Middleware\RedirectSuperAdmin::class,
        ]);

        $middleware->encryptCookies(except: [
            'tenant',
        ]);

        // Must run before StartSession so cookie name + DB connection are tenant-scoped.
        $middleware->web(prepend: [
            App\Http\Middleware\IdentifyTenant::class,
        ]);
        $middleware->prependToPriorityList(
            Illuminate\Session\Middleware\StartSession::class,
            App\Http\Middleware\IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
