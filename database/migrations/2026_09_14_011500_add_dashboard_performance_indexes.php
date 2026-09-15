<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->index(['student_id', 'academic_session_id'], 'fee_payments_student_session_idx');
            $table->index('payment_date', 'fee_payments_payment_date_idx');
            $table->index(['academic_session_id', 'payment_date'], 'fee_payments_session_date_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index('status', 'students_status_idx');
            $table->index(['status', 'admission_date'], 'students_status_admission_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['attendable_type', 'date'], 'attendances_type_date_idx');
        });

        Schema::table('student_session_history', function (Blueprint $table) {
            $table->index('session', 'student_session_history_session_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('fee_payments_student_session_idx');
            $table->dropIndex('fee_payments_payment_date_idx');
            $table->dropIndex('fee_payments_session_date_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_status_idx');
            $table->dropIndex('students_status_admission_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_type_date_idx');
        });

        Schema::table('student_session_history', function (Blueprint $table) {
            $table->dropIndex('student_session_history_session_idx');
        });
    }
};
