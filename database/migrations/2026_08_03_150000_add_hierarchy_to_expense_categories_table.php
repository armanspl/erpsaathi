<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('type')->default('legacy')->after('description');
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('expense_categories')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->after('parent_id');
        });

        // Intentionally no seed data — schools start with an empty expense_categories table.
        // Categories are created by staff in Finance → Expense Categories.
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['type', 'is_active']);
        });
    }
};
