<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root: professional School ERP landing/login page. Authenticated staff are offered a
// "Go to Dashboard" action; everyone else gets a "Login to ERP" button that routes into
// the existing /erp/login flow. No ERP routes, auth logic or business logic are changed.
Route::get('/', function () {
    $schoolName = 'Global Access School';
    // Publicly reachable logo (same asset the ERP login screen uses). The configured
    // SchoolSetting logo is only used when it is a truly public URL — the default
    // accessor points at the auth-gated /erp/api asset route, which 401s for guests.
    $logoUrl = asset('assets/img/logo/demo.jpeg');
    $authenticated = false;

    try {
        $authenticated = Auth::guard('erp')->check();

        $school = App\Models\SchoolSetting::current();
        $schoolName = $school->school_name ?: $schoolName;

        $configuredLogo = $school->logo_url;
        if ($configuredLogo && ! str_contains($configuredLogo, '/erp/api/')) {
            $logoUrl = $configuredLogo;
        }
    } catch (\Throwable $e) {
        // Database unavailable — fall back to defaults so the landing page still renders.
    }

    $welcome = [
        'authenticated' => (bool) $authenticated,
        'loginUrl'      => route('erp.login'),
        'dashboardUrl'  => route('erp.dashboard'),
        'schoolName'    => $schoolName,
        'logoUrl'       => $logoUrl,
    ];

    return view('welcome', ['welcome' => $welcome]);
})->name('home');

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
