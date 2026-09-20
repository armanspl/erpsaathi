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
        Schema::table('bank_transactions', function (Blueprint $table) {
            // Mirrors a BANK statement sheet's ITEM / CATEGORY columns as real fields, so an
            // imported ledger row (and its export) doesn't rely on parsing them back out of the
            // free-text `remarks` column — same reasoning as expenses.part2/part3.
            $table->string('item')->nullable()->after('reference_no');
            $table->string('category')->nullable()->after('item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn(['item', 'category']);
        });
    }
};
