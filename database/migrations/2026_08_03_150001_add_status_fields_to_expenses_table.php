<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('paid_to')->nullable()->after('title');
            $table->text('notes')->nullable()->after('remarks');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending')->after('date');
        });

        // Office Expenses no longer captures a payment mode / bank account on create —
        // make it truly optional instead of silently defaulting every new row to 'Cash',
        // which would falsify the Cash Book's Cash-only totals.
        DB::statement("ALTER TABLE expenses MODIFY payment_mode ENUM('Cash', 'Bank', 'UPI', 'Cheque') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY payment_mode ENUM('Cash', 'Bank', 'UPI', 'Cheque') NOT NULL DEFAULT 'Cash'");

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['paid_to', 'notes', 'status']);
        });
    }
};
