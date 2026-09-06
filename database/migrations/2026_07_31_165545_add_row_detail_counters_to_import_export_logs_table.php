<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_export_logs', function (Blueprint $table) {
            $table->unsignedInteger('classes_created')->default(0)->after('failed_count');
            $table->unsignedInteger('sections_created')->default(0)->after('classes_created');
            $table->unsignedInteger('vehicles_created')->default(0)->after('sections_created');
            $table->unsignedInteger('rooms_created')->default(0)->after('vehicles_created');
            $table->unsignedInteger('beds_created')->default(0)->after('rooms_created');
            $table->json('ignored_columns')->nullable()->after('beds_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_export_logs', function (Blueprint $table) {
            $table->dropColumn(['classes_created', 'sections_created', 'vehicles_created', 'rooms_created', 'beds_created', 'ignored_columns']);
        });
    }
};
