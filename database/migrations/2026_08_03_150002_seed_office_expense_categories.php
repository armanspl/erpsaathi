<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Previously seeded default expense categories (School, Transport, Fuel, etc.).
        // Disabled so newly provisioned school databases start with an empty table.
        // Staff create categories in the ERP UI as needed.
    }

    public function down(): void
    {
        //
    }
};
