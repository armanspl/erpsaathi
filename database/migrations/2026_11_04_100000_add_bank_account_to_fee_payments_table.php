<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some school databases already have this column (with a foreign key) from the earlier
        // 2026_09_22_000001_add_bank_account_id_to_fee_payments_table migration — keep it then.
        if (! Schema::hasColumn('fee_payments', 'bank_account_id')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                // The bank account the money went to (PMNT MODE "GAS 11662" on the Global Workbook).
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('payment_mode')->index();
            });
        }

        // Receipts whose bank deposit (reference no. = receipt no.) still links them to an account.
        DB::statement(<<<'SQL'
            UPDATE fee_payments p
            JOIN (
                SELECT reference_no, MIN(bank_account_id) AS acc
                FROM bank_transactions
                WHERE type = 'Deposit' AND reference_no IS NOT NULL AND reference_no <> ''
                GROUP BY reference_no
            ) t ON t.reference_no = p.receipt_no
            SET p.bank_account_id = t.acc
            WHERE p.bank_account_id IS NULL AND p.payment_mode NOT IN ('Cash', 'UPI', 'Card', 'Cheque')
            SQL);
    }

    public function down(): void
    {
        // Only undo what up() added — a column from the earlier migration (it has a foreign key,
        // not this index) stays.
        $added = collect(Schema::getIndexes('fee_payments'))->contains(fn ($i) => $i['name'] === 'fee_payments_bank_account_id_index');
        if ($added) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->dropIndex(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            });
        }
    }
};
