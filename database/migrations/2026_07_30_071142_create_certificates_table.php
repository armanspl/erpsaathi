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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_no')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['Bonafide', 'Transfer Certificate', 'Character Certificate', 'Migration Certificate']);
            $table->date('issue_date');
            $table->string('reason')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
