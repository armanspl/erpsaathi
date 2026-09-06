<?php

use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\SchoolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'central'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware('super_admin.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary'])->name('summary');
            Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
            Route::post('/schools', [SchoolController::class, 'store'])->name('schools.store');
            Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('schools.show');
            Route::put('/schools/{school}', [SchoolController::class, 'update'])->name('schools.update');
            Route::patch('/schools/{school}/status', [SchoolController::class, 'setStatus'])->name('schools.status');
            Route::post('/schools/{school}/reset-admin', [SchoolController::class, 'resetAdmin'])->name('schools.reset-admin');
            Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->name('schools.destroy');
        });

        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/{any}', DashboardController::class)->where('any', '^(?!api).*$')->name('spa');
    });
});
