<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's own WhatsApp Business Account, connected through Meta Embedded Signup.
 * Lives in each school's database; the access token is encrypted at rest (APP_KEY) and
 * never sent to the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('waba_id', 64);
            $table->string('waba_name')->nullable();
            $table->string('business_id', 64)->nullable();
            $table->string('phone_number_id', 64)->unique();
            $table->string('display_phone_number', 32)->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating', 32)->nullable();
            $table->string('platform_type', 32)->nullable();
            $table->string('code_verification_status', 32)->nullable();
            $table->text('access_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 16)->default('connected'); // connected | disconnected
            $table->unsignedBigInteger('connected_by_id')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
