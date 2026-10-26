<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('erp_fee_settings', 'demand_slip_note')) {
            return;
        }

        Schema::table('erp_fee_settings', function (Blueprint $table) {
            // Printed on every Fee Demand Slip, above the "Please pay" line — editable from the Demand Slip page.
            $table->text('demand_slip_note')->nullable()->after('tc_allow_pending_fees');
        });

        DB::table('erp_fee_settings')->whereNull('demand_slip_note')->update([
            'demand_slip_note' => 'Please clear the monthly dues before 10th of every month otherwise RS 50 is added as late fine.',
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('erp_fee_settings', 'demand_slip_note')) {
            Schema::table('erp_fee_settings', function (Blueprint $table) {
                $table->dropColumn('demand_slip_note');
            });
        }
    }
};
