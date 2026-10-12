<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_scholastic_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Built-in areas keep their old key so the result PDF can still fall back to the
            // derived grade (Work Education = overall grade, Drawing & Art = Drawing subject).
            $table->string('code', 32)->nullable()->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('co_scholastic_areas')->insert([
            ['name' => 'Work Education', 'code' => 'work_education', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Drawing & Art', 'code' => 'drawing_art', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sports', 'code' => 'sports', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('co_scholastic_student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('co_scholastic_area_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 16);
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id', 'co_scholastic_area_id'], 'co_scholastic_student_grade_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_scholastic_student_grades');
        Schema::dropIfExists('co_scholastic_areas');
    }
};
