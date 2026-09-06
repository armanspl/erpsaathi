<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_plan_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rows');
            $table->unsignedInteger('columns');
            $table->unsignedInteger('students_per_bench')->default(1);
            $table->unsignedInteger('room_count');
            $table->string('fill_order')->default('roll_number');
            $table->boolean('separate_by_gender')->default(false);
            $table->string('room_prefix')->default('Room');
            $table->string('room_suffix')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_plan_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_plan_sheet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gender')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seat_plan_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_plan_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seat_no');
            $table->unsignedInteger('row_no');
            $table->unsignedInteger('col_no');
            $table->unsignedInteger('bench_slot');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_plan_seats');
        Schema::dropIfExists('seat_plan_rooms');
        Schema::dropIfExists('seat_plan_sheets');
    }
};
