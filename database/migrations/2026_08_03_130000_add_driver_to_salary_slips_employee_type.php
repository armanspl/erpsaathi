<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE salary_slips MODIFY employee_type ENUM('teacher', 'staff', 'driver') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE salary_slips MODIFY employee_type ENUM('teacher', 'staff') NOT NULL");
    }
};
