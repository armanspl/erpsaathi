<?php

use App\Services\GlobalSchoolTemplateImporter;
use Illuminate\Database\Migrations\Migration;

/**
 * Copy document templates from the global-school database snapshot so every new
 * tenant school gets the same Templates / Certificates designs automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new GlobalSchoolTemplateImporter)->import();
    }

    public function down(): void
    {
        // Keep templates — they may have been customized per school.
    }
};
