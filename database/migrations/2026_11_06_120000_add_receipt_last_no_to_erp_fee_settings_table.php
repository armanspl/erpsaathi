<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('erp_fee_settings', 'receipt_last_no')) {
            return;
        }
        Schema::table('erp_fee_settings', function (Blueprint $table) {
            // The school's last receipt-book number, when it is ahead of the last receipt saved:
            // automatic receipts continue after the higher of the two. Null = last saved receipt.
            $table->unsignedBigInteger('receipt_last_no')->nullable()->after('receipt_paid_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('erp_fee_settings', 'receipt_last_no')) {
            Schema::table('erp_fee_settings', function (Blueprint $table) {
                $table->dropColumn('receipt_last_no');
            });
        }
    }
};
