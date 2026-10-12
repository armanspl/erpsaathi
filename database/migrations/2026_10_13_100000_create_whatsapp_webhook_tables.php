<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school WhatsApp Cloud API records (lives in each school's own database):
 * - whatsapp_webhook_events: one row per distinct webhook event; the unique event_key makes
 *   Meta's retries / duplicate deliveries no-ops. Payload is stored encrypted.
 * - whatsapp_messages: one row per WhatsApp message (wamid), outbound or inbound, with its
 *   latest delivery status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64)->unique();
            $table->string('kind', 16); // status | message | error
            $table->string('phone_number_id', 64)->nullable();
            $table->string('wamid', 191)->nullable()->index();
            $table->string('status', 32)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->longText('payload')->nullable(); // encrypted JSON of this single event
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wamid', 191)->unique();
            $table->string('direction', 16); // outbound | inbound
            $table->string('phone_number_id', 64)->nullable()->index();
            $table->string('contact_wa_id', 32)->nullable()->index();
            $table->string('type', 32)->nullable();
            $table->text('body')->nullable(); // encrypted (inbound text / captions)
            $table->string('context_wamid', 191)->nullable(); // message this one replies to
            $table->string('status', 32)->nullable();
            $table->unsignedTinyInteger('status_rank')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->string('error_title', 255)->nullable();
            $table->string('conversation_id', 191)->nullable();
            $table->string('pricing_category', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
