<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->boolean('is_absent')->default(false)->after('marks_obtained');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE marks MODIFY marks_obtained DECIMAL(6,2) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE marks ALTER COLUMN marks_obtained DROP NOT NULL');
        } else {
            // SQLite / others: recreate is not needed for local XAMPP (mysql).
            try {
                DB::statement('ALTER TABLE marks ALTER COLUMN marks_obtained DROP NOT NULL');
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE marks MODIFY marks_obtained DECIMAL(6,2) NOT NULL');
        }

        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn('is_absent');
        });
    }
};
