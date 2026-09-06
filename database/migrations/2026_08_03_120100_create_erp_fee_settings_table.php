<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_payment_mode')->default('Cash');
            $table->string('receipt_paid_at')->default('SCHOOL');
            $table->boolean('auto_select_current_month')->default(true);
            $table->string('tally_company_name')->nullable();
            $table->string('tally_cash_ledger')->default('Cash');
            $table->string('tally_bank_ledger')->default('Bank');
            $table->string('tally_party_ledger')->default('Fee Receivable');
            $table->json('tally_fee_ledgers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_fee_settings');
    }
};
