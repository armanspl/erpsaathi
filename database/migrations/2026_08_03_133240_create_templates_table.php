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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->decimal('page_width_mm', 8, 2);
            $table->decimal('page_height_mm', 8, 2);
            $table->string('background_color')->nullable();
            $table->string('background_image_path')->nullable();
            $table->json('elements');
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
