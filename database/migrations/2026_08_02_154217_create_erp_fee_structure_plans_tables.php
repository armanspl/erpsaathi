<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_fee_structure_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->enum('type', ['Faculty', 'Transport'])->default('Faculty');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['academic_session_id', 'type']);
        });

        Schema::create('erp_fee_structure_plan_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('erp_fee_structure_plans')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'branch_id', 'school_class_id', 'section_id'], 'fee_plan_scopes_unique');
        });

        Schema::create('erp_fee_structure_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('erp_fee_structure_plans')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('frequency', ['one_time', 'monthly', 'quarterly', 'annual'])->default('monthly');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_fee_structure_plan_items');
        Schema::dropIfExists('erp_fee_structure_plan_scopes');
        Schema::dropIfExists('erp_fee_structure_plans');
    }
};
