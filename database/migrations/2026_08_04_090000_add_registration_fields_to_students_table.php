<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->foreignId('branch_id')->nullable()->after('section_id')->constrained('branches')->nullOnDelete();
            $table->string('pan_no')->nullable()->after('aadhar_no');
            $table->boolean('re_admission')->default(false)->after('status');
            $table->string('address_line_2')->nullable()->after('address');
            $table->boolean('permanent_same_as_current')->default(true)->after('pincode');
            $table->string('permanent_address_line_1')->nullable()->after('permanent_same_as_current');
            $table->string('permanent_address_line_2')->nullable()->after('permanent_address_line_1');
            $table->string('permanent_city')->nullable()->after('permanent_address_line_2');
            $table->string('permanent_state')->nullable()->after('permanent_city');
            $table->string('permanent_pincode')->nullable()->after('permanent_state');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'pan_no', 're_admission', 'address_line_2',
                'permanent_same_as_current', 'permanent_address_line_1', 'permanent_address_line_2',
                'permanent_city', 'permanent_state', 'permanent_pincode',
            ]);
        });
    }
};
