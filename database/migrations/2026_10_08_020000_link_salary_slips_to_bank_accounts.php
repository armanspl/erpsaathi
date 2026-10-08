<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salary_slips', 'bank_account_id')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                // The account a "Bank" salary is paid from; the debit itself is a bank_transactions row.
                $table->foreignId('bank_account_id')->nullable()->after('payment_mode')->constrained('bank_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('bank_transactions', 'salary_slip_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                // Set on the Withdrawal created when a salary slip is paid by bank (kept in sync by
                // App\Services\Payroll\SalaryBankSync — edited from the slip, not by hand).
                $table->unsignedBigInteger('salary_slip_id')->nullable()->unique()->after('bank_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_transactions', 'salary_slip_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->dropUnique(['salary_slip_id']);
                $table->dropColumn('salary_slip_id');
            });
        }
        if (Schema::hasColumn('salary_slips', 'bank_account_id')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_account_id');
            });
        }
    }
};
