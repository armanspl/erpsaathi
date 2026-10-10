<?php

use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\SchoolController;
use App\Http\Controllers\SuperAdmin\SchoolDatabaseController;
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

            // Database Manager — each route is scoped to one registered school's own database.
            Route::get('/databases', [SchoolDatabaseController::class, 'index'])->name('databases.index');
            Route::prefix('/schools/{school}/database')->name('schools.database.')->controller(SchoolDatabaseController::class)->group(function () {
                Route::get('/', 'overview')->name('overview');
                Route::get('/audits', 'audits')->name('audits');
                Route::get('/backup', 'backup')->name('backup');
                Route::prefix('/tables/{table}')->where(['table' => '[A-Za-z0-9_$]{1,64}'])->group(function () {
                    Route::get('/structure', 'structure')->name('structure');
                    Route::get('/rows', 'rows')->name('rows');
                    Route::get('/record', 'show')->name('record');
                    Route::post('/rows', 'store')->name('rows.store');
                    Route::put('/rows', 'update')->name('rows.update');
                    Route::delete('/rows', 'destroyRows')->name('rows.destroy');
                    Route::post('/empty', 'emptyTable')->name('empty');
                    Route::post('/truncate', 'truncateTable')->name('truncate');
                    Route::delete('/', 'dropTable')->name('drop');
                    Route::get('/backup', 'backup')->name('table-backup');
                });
            });
        });

        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/{any}', DashboardController::class)->where('any', '^(?!api).*$')->name('spa');
    });
});
