<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_advances')) {
            Schema::create('salary_advances', function (Blueprint $table) {
                $table->id();
                $table->string('advance_no')->unique();
                $table->string('employee_type', 20);
                $table->unsignedBigInteger('employee_id');
                // The salary month this advance is taken against ("2026-10") — it is deducted as ADV on that month's slip.
                $table->string('period', 7);
                $table->decimal('basic_salary', 12, 2)->default(0);
                $table->decimal('amount', 12, 2);
                $table->date('paid_on');
                $table->string('payment_mode', 20)->default('Cash');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('remarks')->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->timestamps();

                $table->index(['employee_type', 'employee_id', 'period']);
            });
        }

        if (! Schema::hasColumn('bank_transactions', 'salary_advance_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                // Set on the Withdrawal for an advance paid by bank (kept in step by SalaryAdvance's model events).
                $table->unsignedBigInteger('salary_advance_id')->nullable()->unique()->after('salary_slip_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_transactions', 'salary_advance_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->dropUnique(['salary_advance_id']);
                $table->dropColumn('salary_advance_id');
            });
        }
        Schema::dropIfExists('salary_advances');
    }
};
