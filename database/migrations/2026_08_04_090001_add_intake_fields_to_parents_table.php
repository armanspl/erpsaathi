<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->decimal('annual_income', 12, 2)->nullable()->after('occupation');
            $table->string('aadhaar_no')->nullable()->after('dob');
            $table->string('pan_no')->nullable()->after('aadhaar_no');
            // Only meaningful when this record is linked as a student's guardian (guardian_id),
            // not father/mother — the relation to a specific student isn't otherwise stored.
            $table->string('relationship')->nullable()->after('pan_no');
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn(['annual_income', 'aadhaar_no', 'pan_no', 'relationship']);
        });
    }
};
