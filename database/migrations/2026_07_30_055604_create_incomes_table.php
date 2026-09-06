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
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->string('source');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->enum('payment_mode', ['Cash', 'Bank', 'UPI', 'Cheque'])->default('Cash');
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->foreignId('received_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
