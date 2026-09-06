<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_in_month')->nullable()->after('basic_salary');
            $table->decimal('present', 5, 2)->nullable()->after('days_in_month');
            $table->decimal('absent', 5, 2)->nullable()->after('present');
            $table->decimal('cl', 5, 2)->nullable()->after('absent');
            $table->decimal('total_days', 5, 2)->nullable()->after('cl');
            $table->decimal('per_day_rate', 10, 2)->nullable()->after('total_days');
            $table->decimal('this_month_salary', 10, 2)->nullable()->after('per_day_rate');
            $table->decimal('advance', 10, 2)->default(0)->after('this_month_salary');
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropColumn([
                'days_in_month', 'present', 'absent', 'cl',
                'total_days', 'per_day_rate', 'this_month_salary', 'advance',
            ]);
        });
    }
};
