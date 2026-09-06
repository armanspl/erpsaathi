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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('attendable_type');
            $table->unsignedBigInteger('attendable_id');
            $table->string('leave_type');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('approved_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->date('rejoined_at')->nullable();
            $table->timestamps();

            $table->index(['attendable_type', 'attendable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
