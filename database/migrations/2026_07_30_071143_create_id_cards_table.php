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
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_no')->unique();
            $table->enum('holder_type', ['student', 'teacher', 'staff']);
            $table->unsignedBigInteger('holder_id');
            $table->date('issued_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['Active', 'Reissued', 'Lost'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
