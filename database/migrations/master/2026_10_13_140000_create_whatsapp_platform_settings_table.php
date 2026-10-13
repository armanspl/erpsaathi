<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide Meta / WhatsApp settings, managed from Super Admin → WhatsApp Configuration.
 * One row. app_secret and webhook_verify_token are encrypted with APP_KEY.
 *
 * Seeded from whatever is currently in .env (WHATSAPP_*) so an already-verified webhook keeps
 * working after deploy; the .env values can then be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('whatsapp_platform_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('embedded_signup_enabled')->default(true);
            $table->string('app_id', 32)->nullable();
            $table->string('embedded_signup_config_id', 32)->nullable();
            $table->string('graph_version', 8)->nullable();
            $table->text('app_secret')->nullable();            // encrypted
            $table->text('webhook_verify_token')->nullable();  // encrypted
            $table->unsignedSmallInteger('signup_ticket_ttl')->default(15);
            $table->timestamp('app_secret_updated_at')->nullable();
            $table->timestamp('webhook_verify_token_updated_at')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->string('updated_by_email')->nullable();
            $table->json('last_check')->nullable();
            $table->timestamps();
        });

        // config(), not env(): env() is empty when the config is cached.
        $secret = (string) config('whatsapp.webhook.app_secret', '');
        $verify = (string) config('whatsapp.webhook.verify_token', '');
        $now = now();
        DB::connection('master')->table('whatsapp_platform_settings')->insert([
            'embedded_signup_enabled' => true,
            'app_id' => config('whatsapp.app_id') ?: null,
            'embedded_signup_config_id' => config('whatsapp.embedded_signup.config_id') ?: null,
            'graph_version' => config('whatsapp.graph_version') ?: 'v23.0',
            'app_secret' => $secret !== '' ? Crypt::encryptString($secret) : null,
            'webhook_verify_token' => $verify !== '' ? Crypt::encryptString($verify) : null,
            'signup_ticket_ttl' => (int) config('whatsapp.embedded_signup.ticket_ttl', 15) ?: 15,
            'app_secret_updated_at' => $secret !== '' ? $now : null,
            'webhook_verify_token_updated_at' => $verify !== '' ? $now : null,
            'updated_by_email' => 'migration (copied from .env)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('whatsapp_platform_settings');
    }
};
