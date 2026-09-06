<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE fee_payments MODIFY status ENUM('Paid', 'Partially Refunded', 'Refunded', 'Rolled Back') NOT NULL DEFAULT 'Paid'");

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('remarks');
            $table->dateTime('edited_at')->nullable()->after('collected_by_id');
            $table->foreignId('edited_by_id')->nullable()->after('edited_at')->constrained('erp_users')->nullOnDelete();
            $table->string('rollback_reason')->nullable()->after('edited_by_id');
            $table->dateTime('rolled_back_at')->nullable()->after('rollback_reason');
            $table->foreignId('rolled_back_by_id')->nullable()->after('rolled_back_at')->constrained('erp_users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rolled_back_by_id');
            $table->dropColumn(['rollback_reason', 'rolled_back_at']);
            $table->dropConstrainedForeignId('edited_by_id');
            $table->dropColumn(['edited_at', 'reference_no']);
        });

        DB::statement("ALTER TABLE fee_payments MODIFY status ENUM('Paid', 'Partially Refunded', 'Refunded') NOT NULL DEFAULT 'Paid'");
    }
};
