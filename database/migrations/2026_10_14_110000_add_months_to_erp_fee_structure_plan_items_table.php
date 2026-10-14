<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('erp_fee_structure_plan_items', 'months')) {
            return;
        }

        Schema::table('erp_fee_structure_plan_items', function (Blueprint $table) {
            // Month numbers (1–12) a monthly / quarterly line is charged in, e.g. [9, 3] for an
            // Examination Fee taken in September and March. NULL = the frequency's normal months.
            $table->json('months')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('erp_fee_structure_plan_items', 'months')) {
            return;
        }

        Schema::table('erp_fee_structure_plan_items', function (Blueprint $table) {
            $table->dropColumn('months');
        });
    }
};
