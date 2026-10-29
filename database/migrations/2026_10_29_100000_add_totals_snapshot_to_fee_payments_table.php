<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_payments', 'totals_snapshot')) {
            return;
        }

        Schema::table('fee_payments', function (Blueprint $table) {
            // Receipt totals as shown on the Fee Receipt form at submission (base fee, fee-wise and
            // overall discount, payable, received, due after, months left fully unpaid). Null on
            // receipts saved before this column existed — those keep printing from their lines.
            $table->json('totals_snapshot')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('fee_payments', 'totals_snapshot')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->dropColumn('totals_snapshot');
            });
        }
    }
};
