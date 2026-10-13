<?php

use App\Http\Controllers\WhatsAppEmbeddedSignupController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Server-to-server callbacks from Meta. Deliberately outside the "web" group: no session,
// cookies, CSRF or host-based school lookup. POSTs are authenticated by signature instead.
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');
});

// Central Meta Embedded Signup page (one host for every school). Authorised by the signed,
// single-use ticket the school's ERP issues — not by session — so it also sits outside "web".
Route::prefix('whatsapp/embedded-signup')->name('whatsapp.embedded-signup.')->group(function () {
    Route::get('/', [WhatsAppEmbeddedSignupController::class, 'page'])->name('page');
    Route::post('complete', [WhatsAppEmbeddedSignupController::class, 'complete'])->name('complete');
});
