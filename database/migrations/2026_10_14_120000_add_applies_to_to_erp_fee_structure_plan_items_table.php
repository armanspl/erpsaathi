<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('erp_fee_structure_plan_items', 'applies_to')) {
            return;
        }

        Schema::table('erp_fee_structure_plan_items', function (Blueprint $table) {
            // Fee Structure "For students": all / new / old admission type. NULL = default rule
            // (Admission / Registration Fee → New only, everything else → all).
            $table->string('applies_to', 10)->nullable()->after('months');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('erp_fee_structure_plan_items', 'applies_to')) {
            return;
        }

        Schema::table('erp_fee_structure_plan_items', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
