<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_export_logs', function (Blueprint $table) {
            $table->unsignedInteger('academic_sessions_created')->default(0)->after('sections_created');
        });
    }

    public function down(): void
    {
        Schema::table('import_export_logs', function (Blueprint $table) {
            $table->dropColumn('academic_sessions_created');
        });
    }
};
