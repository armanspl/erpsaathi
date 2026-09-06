<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Seed the two special top-level groups the Category Types manager expects.
        // Existing categories keep the 'legacy' default set above — untouched otherwise.
        foreach (['School' => 'school', 'Transport' => 'transport'] as $name => $type) {
            DB::table('expense_categories')->insertOrIgnore([
                'name' => $name,
                'description' => null,
                'type' => $type,
                'parent_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['type', 'is_active']);
        });
    }
};
