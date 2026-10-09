<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session-scoped calendar months (1–12) where a student's fee is fully waived.
 * Not a payment — FeeBalanceService treats these as discount/concession only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_discount_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->json('months'); // e.g. [4, 8, 12] for April, August, December
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'student_session_discount_months_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_discount_months');
    }
};
