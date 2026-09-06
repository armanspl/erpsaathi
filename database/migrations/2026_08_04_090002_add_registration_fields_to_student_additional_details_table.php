<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_additional_details', function (Blueprint $table) {
            $table->text('medical_conditions')->nullable()->after('family');
            $table->text('allergies')->nullable()->after('medical_conditions');
            $table->string('emergency_contact_name')->nullable()->after('allergies');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('previous_class')->nullable()->after('last_school_name');
        });
    }

    public function down(): void
    {
        Schema::table('student_additional_details', function (Blueprint $table) {
            $table->dropColumn(['medical_conditions', 'allergies', 'emergency_contact_name', 'emergency_contact_phone', 'previous_class']);
        });
    }
};
