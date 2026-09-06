<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['MCQ', 'Short Answer', 'Long Answer'])->default('MCQ');
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->decimal('marks', 5, 2)->default(1);
            $table->enum('difficulty', ['Easy', 'Medium', 'Hard'])->default('Medium');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
