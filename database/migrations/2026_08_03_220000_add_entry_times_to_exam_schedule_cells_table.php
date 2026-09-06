<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A date × class-section cell now holds a repeatable list of subject entries
     * (each with its own time slot) instead of exactly one subject — drop the
     * one-per-cell uniqueness and give every entry its own start/end time.
     */
    public function up(): void
    {
        Schema::table('exam_schedule_cells', function (Blueprint $table) {
            // exam_schedule_date_id's FK relies on exam_schedule_cell_unique as its
            // supporting index (no dedicated single-column index exists) — give it
            // one first so MySQL doesn't refuse to drop that unique index below.
            $table->index('exam_schedule_date_id');
        });

        Schema::table('exam_schedule_cells', function (Blueprint $table) {
            $table->dropUnique('exam_schedule_cell_unique');
            $table->time('start_time')->nullable()->after('subject_id');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedule_cells', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
            $table->unique(['exam_schedule_date_id', 'school_class_id', 'section_id'], 'exam_schedule_cell_unique');
            $table->dropIndex(['exam_schedule_date_id']);
        });
    }
};
