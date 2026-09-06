<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->string('slip_no')->nullable()->unique()->after('id');
            $table->json('earnings')->nullable()->after('allowances');
            $table->json('deduction_items')->nullable()->after('deductions');
        });

        DB::statement("ALTER TABLE salary_slips MODIFY status ENUM('Draft', 'Pending', 'Paid') NOT NULL DEFAULT 'Draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE salary_slips MODIFY status ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending'");

        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropColumn(['slip_no', 'earnings', 'deduction_items']);
        });
    }
};
