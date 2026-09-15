<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_fee_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('erp_fee_settings', 'tc_fee_enabled')) {
                $table->boolean('tc_fee_enabled')->default(true)->after('auto_select_current_month');
            }
            if (! Schema::hasColumn('erp_fee_settings', 'tc_fee_amount')) {
                $table->decimal('tc_fee_amount', 12, 2)->default(500)->after('tc_fee_enabled');
            }
            if (! Schema::hasColumn('erp_fee_settings', 'tc_allow_pending_fees')) {
                $table->boolean('tc_allow_pending_fees')->default(false)->after('tc_fee_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('erp_fee_settings', function (Blueprint $table) {
            foreach (['tc_allow_pending_fees', 'tc_fee_amount', 'tc_fee_enabled'] as $col) {
                if (Schema::hasColumn('erp_fee_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
