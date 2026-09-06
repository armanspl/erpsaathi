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
        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_no')->unique();
            $table->string('student_name');
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->foreignId('class_applying_for_id')->constrained('school_classes')->restrictOnDelete();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('source')->nullable();
            $table->enum('stage', ['enquiry', 'follow_up', 'registered', 'admitted', 'rejected'])->default('enquiry');
            $table->text('remarks')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->boolean('fee_paid')->default(false);
            $table->json('documents_submitted')->nullable();
            $table->foreignId('admitted_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_enquiries');
    }
};
