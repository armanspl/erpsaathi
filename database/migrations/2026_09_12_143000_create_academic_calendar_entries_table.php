<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('category', 32); // holiday | examination | programme | deadline
            $table->string('title');
            $table->string('month_label', 40)->nullable();
            $table->string('date_label', 80)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'start_date']);
            $table->index(['academic_session_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendar_entries');
    }
};
