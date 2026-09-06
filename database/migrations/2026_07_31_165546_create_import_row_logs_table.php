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
        Schema::create('import_row_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_export_log_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->enum('status', ['Success', 'Failed']);
            $table->string('identifier')->nullable();
            $table->json('summary')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_row_logs');
    }
};
