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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('attendable_type');
            $table->unsignedBigInteger('attendable_id');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Leave', 'Late', 'Half Day'])->default('Present');
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendable_type', 'attendable_id']);
            $table->unique(['attendable_type', 'attendable_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
