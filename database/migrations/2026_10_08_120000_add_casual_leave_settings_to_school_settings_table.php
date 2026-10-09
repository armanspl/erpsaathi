<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->decimal('cl_yearly_limit', 5, 2)->default(12)->after('student_portal_visibility');
            $table->decimal('cl_monthly_limit', 5, 2)->default(2)->after('cl_yearly_limit');
            $table->string('cl_leave_year', 20)->default('calendar')->after('cl_monthly_limit'); // calendar | academic
            $table->boolean('cl_allow_carry_forward')->default(false)->after('cl_leave_year');
            $table->decimal('cl_max_carry_forward', 5, 2)->default(0)->after('cl_allow_carry_forward');
            $table->boolean('cl_allow_half_day')->default(false)->after('cl_max_carry_forward');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cl_yearly_limit',
                'cl_monthly_limit',
                'cl_leave_year',
                'cl_allow_carry_forward',
                'cl_max_carry_forward',
                'cl_allow_half_day',
            ]);
        });
    }
};
