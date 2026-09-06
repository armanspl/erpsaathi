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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->enum('payment_mode', ['Cash', 'Bank', 'UPI', 'Cheque'])->default('Cash');
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->foreignId('paid_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
