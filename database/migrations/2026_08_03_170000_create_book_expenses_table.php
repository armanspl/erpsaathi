<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Snapshot of the student's branch/class/section at issue time, for filtering
            // without joining through students — matches book_expense_items' price snapshot.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('book_expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_expense_items');
        Schema::dropIfExists('book_expenses');
    }
};
