<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_udise_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('student_type')->nullable();
            $table->string('minority_group')->nullable();
            $table->boolean('bpl_beneficiary')->nullable();
            $table->boolean('ews_disadvantaged')->nullable();
            $table->boolean('cwsn')->nullable();
            $table->string('type_of_impairments')->nullable();
            $table->boolean('indian_national')->nullable();
            $table->string('mother_tongue')->nullable();
            $table->boolean('aay_beneficiary')->nullable();
            $table->string('rte_ews_admission')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('alternate_mobile')->nullable();
            $table->string('stoppage')->nullable();
            $table->string('vehicle')->nullable();
            $table->boolean('hostel')->nullable();
            $table->boolean('uses_transport')->nullable();
            $table->string('admission_type')->nullable();
            $table->string('clsl')->nullable();
            $table->string('name_as_per_aadhaar')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            // Reserved — always blank in the source files today.
            $table->string('student_state_code')->nullable();
            $table->boolean('is_repeater')->nullable();
            $table->string('entry_status')->nullable();
            $table->string('student_pen')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_udise_details');
    }
};
