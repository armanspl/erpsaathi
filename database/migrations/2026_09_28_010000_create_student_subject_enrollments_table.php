<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which optional/elective subject(s) each student takes, per academic session — e.g. Student A
 * enrolled in Urdu, Student B in Sanskrit, both in the same Class 1-A. Compulsory subjects (the
 * normal case) need no row here at all; they come from class_subject and apply to everyone.
 * school_class_id is denormalized from the student's class at enrollment time (same pattern as
 * student_session_histories.class_name) so class-scoped roster queries don't need a join back to
 * the students table, and a class change doesn't retroactively move a past session's enrollment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subject_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'academic_session_id'], 'student_subject_session_unique');
            $table->index(['school_class_id', 'academic_session_id', 'subject_id'], 'student_subject_class_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_enrollments');
    }
};
