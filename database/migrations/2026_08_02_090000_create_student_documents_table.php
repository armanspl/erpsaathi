<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            // Private-disk relative paths (storage/app/private/...) — never public URLs.
            // Downloads always go through StudentController::downloadDocument().
            $table->string('photo_path')->nullable();
            $table->string('aadhaar_path')->nullable();
            $table->string('pan_path')->nullable();
            $table->string('birth_certificate_path')->nullable();
            $table->string('transfer_certificate_path')->nullable();
            $table->string('marksheet_path')->nullable();
            $table->string('father_aadhaar_path')->nullable();
            $table->string('father_pan_path')->nullable();
            $table->string('mother_aadhaar_path')->nullable();
            $table->string('mother_pan_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
