<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_additional_details', function (Blueprint $table) {
            $table->string('class_admitted')->nullable()->after('previous_class');
            $table->string('tc_number')->nullable()->after('class_admitted');
            $table->date('tc_date')->nullable()->after('tc_number');
            $table->string('last_class_studied')->nullable()->after('tc_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_additional_details', function (Blueprint $table) {
            $table->dropColumn(['class_admitted', 'tc_number', 'tc_date', 'last_class_studied']);
        });
    }
};
