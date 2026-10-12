<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which school owns which WhatsApp Business phone number. Meta sends every school's webhook
 * events to one callback URL; metadata.phone_number_id in each event is looked up here so the
 * event is written into that school's database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('phone_number_id', 64)->unique();
            $table->string('waba_id', 64)->nullable();
            $table->string('display_phone_number', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('whatsapp_accounts');
    }
};
