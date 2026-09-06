<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedule_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'branch_id']);
        });

        Schema::create('exam_schedule_sittings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_sheet_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('exam_schedule_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_sheet_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_holiday')->default(false);
            $table->timestamps();

            $table->unique(['exam_schedule_sheet_id', 'date']);
        });

        Schema::create('exam_schedule_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_date_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_schedule_date_id', 'school_class_id', 'section_id'], 'exam_schedule_cell_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedule_cells');
        Schema::dropIfExists('exam_schedule_dates');
        Schema::dropIfExists('exam_schedule_sittings');
        Schema::dropIfExists('exam_schedule_sheets');
    }
};
