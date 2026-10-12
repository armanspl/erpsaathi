<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Server-to-server callbacks from Meta. Deliberately outside the "web" group: no session,
// cookies, CSRF or host-based school lookup. POSTs are authenticated by signature instead.
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');
});
