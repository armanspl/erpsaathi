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
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('salary', 10, 2)->nullable()->after('status');
            $table->json('custom_field_values')->nullable()->after('salary');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('salary', 10, 2)->nullable()->after('status');
            $table->json('custom_field_values')->nullable()->after('salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['salary', 'custom_field_values']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['salary', 'custom_field_values']);
        });
    }
};
