<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('max_marks', 8, 2)->default(100);
            $table->timestamps();

            $table->unique(['academic_session_id', 'name']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('academic_term_id')->nullable()->after('academic_session_id')
                ->constrained('academic_terms')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0)->after('academic_term_id');
            $table->boolean('counts_toward_term')->default(true)->after('sort_order');
            $table->boolean('is_internal_component')->default(false)->after('counts_toward_term');
        });

        Schema::create('co_scholastic_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('area', 32); // work_education, drawing_art, sports
            $table->string('grade', 16);
            $table->timestamps();

            $table->unique(['student_id', 'academic_term_id', 'area'], 'co_scholastic_unique');
        });

        Schema::create('report_card_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'report_card_remarks_unique');
        });

        // Repair schedules stuck at default 100 when the exam policy max is smaller.
        $rows = DB::table('exam_schedules')
            ->join('exams', 'exams.id', '=', 'exam_schedules.exam_id')
            ->whereNotNull('exams.max_marks')
            ->where('exams.max_marks', '>', 0)
            ->where('exams.max_marks', '<', 100)
            ->where('exam_schedules.max_marks', '=', 100)
            ->select('exam_schedules.id', 'exams.max_marks')
            ->get();

        foreach ($rows as $row) {
            DB::table('exam_schedules')
                ->where('id', $row->id)
                ->update(['max_marks' => $row->max_marks]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_remarks');
        Schema::dropIfExists('co_scholastic_grades');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_term_id');
            $table->dropColumn(['sort_order', 'counts_toward_term', 'is_internal_component']);
        });

        Schema::dropIfExists('academic_terms');
    }
};
