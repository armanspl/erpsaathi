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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no')->unique();
            $table->unsignedInteger('roll_no')->nullable();
            $table->string('name');
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('father_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->foreignId('mother_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('category')->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->default('Indian');
            $table->string('aadhar_no')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Transferred'])->default('Active');
            $table->date('admission_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
