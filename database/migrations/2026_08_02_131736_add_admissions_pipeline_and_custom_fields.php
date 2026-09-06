<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_enquiries', function (Blueprint $table) {
            // CRM lead status on the enquiry form (distinct from pipeline stage).
            $table->string('lead_status', 30)->default('New')->after('stage');
        });

        Schema::table('students', function (Blueprint $table) {
            // Admissions pipeline: New → Registered → Admitted (independent of Active/Inactive).
            $table->string('admission_status', 30)->default('Admitted')->after('status');
            $table->json('custom_field_values')->nullable()->after('admission_status');
        });

        Schema::create('admission_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type', 30)->default('text'); // text, number, date, textarea
            $table->string('placeholder')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_custom_fields');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['admission_status', 'custom_field_values']);
        });

        Schema::table('admission_enquiries', function (Blueprint $table) {
            $table->dropColumn('lead_status');
        });
    }
};
