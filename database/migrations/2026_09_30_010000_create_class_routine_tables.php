<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_routine_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedTinyInteger('periods_per_day')->default(7);
            $table->timestamps();

            $table->unique(['academic_session_id', 'branch_id']);
        });

        Schema::create('class_routine_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_routine_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            // 1=Monday ... 6=Saturday (matches the source workbook's Mon-Sat routine; Sunday is
            // never a teaching day in this school's data, but the column isn't restricted to 1-6
            // at the DB level in case a future school needs it).
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedTinyInteger('period_number');
            // Both an id and a free-text label are kept for subject/teacher: an import can name a
            // subject/teacher that doesn't exist yet in the catalog (e.g. a teacher not added to
            // People > Teachers yet) — the label keeps the cell readable and editable immediately,
            // the id is filled in (by the parser's fuzzy match, or later by hand) once it exists.
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_label', 100)->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('teacher_label', 100)->nullable();
            // Captured at write time (from the teacher's colour, or the source workbook's own cell
            // colour on import) so the Excel export can always reproduce a definite colour per cell
            // without depending on the teacher record's current colour at export time.
            $table->string('color', 7)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->index(['class_routine_sheet_id', 'school_class_id', 'section_id', 'day_of_week', 'period_number'], 'class_routine_entry_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_routine_entries');
        Schema::dropIfExists('class_routine_sheets');
    }
};
