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
        Schema::create('erp_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['Notice', 'Circular'])->default('Notice');
            $table->enum('audience', ['All', 'Students', 'Teachers', 'Staff', 'Parents'])->default('All');
            $table->date('publish_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['Draft', 'Published'])->default('Published');
            $table->foreignId('created_by_id')->nullable()->constrained('erp_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_notices');
    }
};
