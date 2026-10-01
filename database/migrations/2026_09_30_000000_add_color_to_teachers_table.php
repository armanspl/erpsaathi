<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Persistent badge colour for this teacher across the Class Routine grid/export — set
            // automatically the first time a Class Routine import recognises their name (from the
            // source workbook's own colour-coding), or manually from the Teacher form / routine UI.
            $table->string('color', 7)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
