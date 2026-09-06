<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize pre-existing SCHOOL/TRANSPORT rows (created before the type column
        // existed, so they picked up the 'legacy' default) to their real group type.
        DB::table('expense_categories')->where('name', 'SCHOOL')->update(['type' => 'school']);
        DB::table('expense_categories')->where('name', 'TRANSPORT')->update(['type' => 'transport']);

        $legacyDefaults = ['Fuel', 'Stationery', 'Maintenance', 'Utilities', 'Travel', 'Food', 'Driver Advance', 'Teacher Advance', 'Miscellaneous'];
        foreach ($legacyDefaults as $name) {
            DB::table('expense_categories')->insertOrIgnore([
                'name' => $name,
                'description' => null,
                'type' => 'legacy',
                'parent_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data-only seed — nothing structural to reverse.
    }
};
