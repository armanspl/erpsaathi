<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            $table->string('out_of_school_child')->nullable()->after('student_pen');
            $table->string('mainstreamed_when')->nullable()->after('out_of_school_child');
            $table->string('disability_certificate')->nullable()->after('mainstreamed_when');
            $table->string('disability_percentage')->nullable()->after('disability_certificate');
            $table->string('medium_of_instruction')->nullable()->after('disability_percentage');
            $table->string('languages_group')->nullable()->after('medium_of_instruction');
            $table->string('academic_stream')->nullable()->after('languages_group');
            $table->string('subjects_group')->nullable()->after('academic_stream');
            $table->string('rte_amount_claimed')->nullable()->after('subjects_group');
            $table->string('facilities_provided')->nullable()->after('rte_amount_claimed');
            $table->string('cwsn_facilities')->nullable()->after('facilities_provided');
            $table->string('olympiads')->nullable()->after('cwsn_facilities');
            $table->string('ncc')->nullable()->after('olympiads');
            $table->string('nss')->nullable()->after('ncc');
            $table->string('scouts_guides')->nullable()->after('nss');
            $table->string('distance_to_school')->nullable()->after('scouts_guides');
            $table->string('parents_education')->nullable()->after('distance_to_school');
        });
    }

    public function down(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            $table->dropColumn([
                'out_of_school_child',
                'mainstreamed_when',
                'disability_certificate',
                'disability_percentage',
                'medium_of_instruction',
                'languages_group',
                'academic_stream',
                'subjects_group',
                'rte_amount_claimed',
                'facilities_provided',
                'cwsn_facilities',
                'olympiads',
                'ncc',
                'nss',
                'scouts_guides',
                'distance_to_school',
                'parents_education',
            ]);
        });
    }
};
