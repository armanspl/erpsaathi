<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_session_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('session');

            // Snapshot of this session's Class/Section/Roll — stored raw, not FK-linked,
            // since this is a historical record, not the student's current relationship
            // (that lives on students.school_class_id/section_id).
            $table->string('class_name')->nullable();
            $table->string('section_name')->nullable();
            $table->unsignedInteger('roll_no')->nullable();

            $table->string('status')->nullable();
            $table->string('promotion_status')->nullable();
            $table->string('previous_year_schooling_status')->nullable();
            $table->string('previous_year_class')->nullable();
            $table->string('exam_appeared')->nullable();
            $table->string('exam_result')->nullable();
            $table->string('exam_marks_percent')->nullable();
            $table->string('attendance_days')->nullable();
            $table->string('attendance_percent')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_session_history');
    }
};
