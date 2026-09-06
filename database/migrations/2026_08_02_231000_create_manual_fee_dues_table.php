<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_fee_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->cascadeOnDelete();
            $table->date('month'); // first day of month
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['student_id', 'academic_session_id', 'fee_head_id', 'month'],
                'manual_fee_dues_unique_line'
            );
            $table->index(['academic_session_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_fee_dues');
    }
};
