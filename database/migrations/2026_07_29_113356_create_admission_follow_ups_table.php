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
        Schema::create('admission_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_enquiry_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->date('follow_up_date');
            $table->date('next_follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_follow_ups');
    }
};
