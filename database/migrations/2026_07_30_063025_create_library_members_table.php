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
        Schema::create('library_members', function (Blueprint $table) {
            $table->id();
            $table->enum('member_type', ['student', 'teacher', 'staff']);
            $table->unsignedBigInteger('member_id');
            $table->string('library_card_no')->unique();
            $table->enum('status', ['Active', 'Blocked'])->default('Active');
            $table->unsignedInteger('max_books')->default(3);
            $table->date('joined_date');
            $table->timestamps();

            $table->unique(['member_type', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_members');
    }
};
