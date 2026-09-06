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
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->enum('employee_type', ['teacher', 'staff']);
            $table->unsignedBigInteger('employee_id');
            $table->char('period', 7); // 'YYYY-MM'
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('allowances', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2);
            $table->enum('status', ['Pending', 'Paid'])->default('Pending');
            $table->date('paid_on')->nullable();
            $table->enum('payment_mode', ['Cash', 'Bank'])->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('generated_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_type', 'employee_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
