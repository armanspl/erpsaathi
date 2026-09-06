<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Marks Management now creates exam_schedules rows purely to hold a subject's
     * max_marks for an exam+class (no date/time context) — relax those columns so
     * that flow doesn't need to invent placeholder scheduling data. The dedicated
     * Exam Schedule feature still populates them when it is used for that purpose.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE exam_schedules MODIFY date DATE NULL');
        DB::statement('ALTER TABLE exam_schedules MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE exam_schedules MODIFY end_time TIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exam_schedules MODIFY date DATE NOT NULL');
        DB::statement('ALTER TABLE exam_schedules MODIFY start_time TIME NOT NULL');
        DB::statement('ALTER TABLE exam_schedules MODIFY end_time TIME NOT NULL');
    }
};
