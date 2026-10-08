<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('drivers', 'email')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            // Contact email (Gmail etc.) — same as teachers/staff; set from the Drivers form or the
            // Staff Profile import on the Salary Sheet page. Not used for login.
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('drivers', 'email')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
