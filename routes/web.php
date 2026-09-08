<?php

use Illuminate\Support\Facades\Route;

// Root: public marketing homepage.
// Apex (erpsaathi.com): no Login CTA.
// School subdomain (takla.erpsaathi.com): homepage + Login to ERP.
Route::get('/', function () {
    $tenant = app(\App\Support\Tenancy\TenantContext::class)->get();
    $authenticated = false;

    if ($tenant) {
        try {
            $authenticated = \Illuminate\Support\Facades\Auth::guard('erp')->check();
        } catch (\Throwable $e) {
            $authenticated = false;
        }
    }

    $welcome = [
        'showLoginCta'  => (bool) $tenant,
        'authenticated' => $authenticated,
        'loginUrl'      => url('/erp/login'),
        'dashboardUrl'  => url('/erp/dashboard'),
        'tryDemoUrl'    => app(\App\Services\Tenancy\DemoSchoolService::class)->publicEnterUrl(),
        'schoolName'    => $tenant?->name ?: 'erpsaathi',
        'logoUrl'       => asset('assets/img/logo/erpsaathi.png'),
    ];

    return view('welcome', ['welcome' => $welcome]);
})->name('home');

// Public Try Demo — provisions Demo school + sample data, then opens ERP dashboard.
Route::get('/erp/demo', App\Http\Controllers\DemoEnterController::class)->name('erp.demo');

// ERP Auth Routes
Route::post('/erp/authenticate', [App\Http\Controllers\ErpAuthController::class, 'login'])->name('erp.authenticate');
Route::middleware('erp.auth')->group(function () {
    // Catch-all so the Vue Router SPA can own any /erp/dashboard/* sub-path on refresh/deep-link.
    Route::get('/erp/dashboard/{any?}', [App\Http\Controllers\ErpAuthController::class, 'dashboard'])
        ->where('any', '.*')
        ->name('erp.dashboard');
    Route::post('/erp/logout', [App\Http\Controllers\ErpAuthController::class, 'logout'])->name('erp.logout');

    // JSON API consumed by the Vue ERP SPA.
    Route::prefix('erp/api')->name('erp.api.')->group(function () {
        require base_path('routes/erp_api.php');
    });
});

// ERP Login (guest)
Route::get('/erp/login', function () {
    return view('erp.login');
})->name('erp.login');

// ERP Forgot / Reset Password
Route::post('/erp/send-reset-link', [App\Http\Controllers\ErpAuthController::class, 'sendResetLink'])->name('erp.send-reset-link');
Route::post('/erp/update-password', [App\Http\Controllers\ErpAuthController::class, 'updatePassword'])->name('erp.update-password');
Route::get('/erp/reset-password', function () {
    return view('erp.reset-password');
})->name('erp.reset-password');
Route::get('/erp/forgot-password', function () {
    return view('erp.forgot-password');
})->name('erp.forgot-password');
