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
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->json('items'); // [{ fee_head_id, fee_head_name, amount }]
            $table->decimal('amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->enum('payment_mode', ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'])->default('Cash');
            $table->date('payment_date');
            $table->string('remarks')->nullable();
            $table->enum('status', ['Paid', 'Partially Refunded', 'Refunded'])->default('Paid');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->string('refund_reason')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->foreignId('collected_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
