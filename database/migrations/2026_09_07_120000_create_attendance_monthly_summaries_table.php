<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('class_sheet', 32)->nullable();
            $table->unsignedTinyInteger('month'); // 1–12
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('session_start_year');
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('days_present')->default(0);
            $table->decimal('percentage', 6, 2)->default(0);
            $table->foreignId('imported_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'year', 'month'], 'ams_student_year_month_unique');
            $table->index(['session_start_year', 'school_class_id'], 'ams_session_class_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_monthly_summaries');
    }
};
