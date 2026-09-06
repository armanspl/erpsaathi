<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['SMS', 'Email', 'WhatsApp', 'Push']);
            $table->enum('audience', ['All', 'Students', 'Teachers', 'Staff', 'Parents'])->default('All');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->enum('status', ['Sent', 'Failed'])->default('Sent');
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('sent_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
