<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_routine_sheets', function (Blueprint $table) {
            // Optional per-day period counts, e.g. {"6":5} for Saturday half-day.
            // Missing days fall back to periods_per_day.
            $table->json('periods_by_day')->nullable()->after('periods_per_day');
        });

        Schema::table('class_routine_entries', function (Blueprint $table) {
            // Explicit free period for a class (no subject/teacher) — e.g. Class 1 Period 5 FREE.
            $table->boolean('is_free')->default(false)->after('period_number');
        });
    }

    public function down(): void
    {
        Schema::table('class_routine_entries', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
        Schema::table('class_routine_sheets', function (Blueprint $table) {
            $table->dropColumn('periods_by_day');
        });
    }
};
