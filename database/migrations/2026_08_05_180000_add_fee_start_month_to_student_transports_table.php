<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_transports', function (Blueprint $table) {
            // Y-m — transport fare is due only from this month onward.
            $table->string('fee_start_month', 7)->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_transports', function (Blueprint $table) {
            $table->dropColumn('fee_start_month');
        });
    }
};
