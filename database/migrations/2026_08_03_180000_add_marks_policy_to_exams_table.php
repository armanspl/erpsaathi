<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->decimal('total_marks', 8, 2)->nullable()->after('type');
            $table->decimal('passing_marks', 8, 2)->nullable()->after('total_marks');
            $table->decimal('min_marks', 8, 2)->nullable()->after('passing_marks');
            $table->decimal('max_marks', 8, 2)->nullable()->after('min_marks');
            $table->text('description')->nullable()->after('max_marks');
        });

        // The Create Exam form no longer collects a linked exam type, session, or
        // schedule dates directly (that's now Exam Schedule's job) — relax these so
        // simple marks-policy exams can be created without them. Nothing currently
        // reads exam_type_id/academic_session_id/start_date/end_date/status besides
        // this controller and the standalone Exam Types page, so nothing else breaks.
        DB::statement('ALTER TABLE exams MODIFY exam_type_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE exams MODIFY academic_session_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE exams MODIFY start_date DATE NULL');
        DB::statement('ALTER TABLE exams MODIFY end_date DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exams MODIFY exam_type_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE exams MODIFY academic_session_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE exams MODIFY start_date DATE NOT NULL');
        DB::statement('ALTER TABLE exams MODIFY end_date DATE NOT NULL');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['type', 'total_marks', 'passing_marks', 'min_marks', 'max_marks', 'description']);
        });
    }
};
