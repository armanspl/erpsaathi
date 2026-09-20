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
        Schema::table('expenses', function (Blueprint $table) {
            // Mirrors the legacy workbook's PART 2 / PART 3 columns (sub-classification under
            // PART 1 = expense category) as real fields instead of text mashed into remarks.
            $table->string('part2')->nullable()->after('expense_category_id');
            $table->string('part3')->nullable()->after('part2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['part2', 'part3']);
        });
    }
};
