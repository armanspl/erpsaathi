<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            $table->boolean('is_in_udise')->default(false)->after('student_pen');
        });
    }

    public function down(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            $table->dropColumn('is_in_udise');
        });
    }
};
