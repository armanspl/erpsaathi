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
        Schema::create('hostel_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_allocation_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // 'YYYY-MM'
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['Pending', 'Paid'])->default('Pending');
            $table->date('paid_on')->nullable();
            $table->enum('payment_mode', ['Cash', 'Bank'])->nullable();
            $table->foreignId('collected_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hostel_allocation_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hostel_fees');
    }
};
