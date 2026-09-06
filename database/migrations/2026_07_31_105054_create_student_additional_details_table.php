<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_additional_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('house')->nullable();
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('vision_left')->nullable();
            $table->string('vision_right')->nullable();
            $table->string('dental_hygiene')->nullable();
            $table->string('family')->nullable();
            $table->date('discontinue_date')->nullable();
            $table->string('scholarship_no')->nullable();

            $table->boolean('report_card_received')->default(false);
            $table->boolean('cc_received')->default(false);
            $table->boolean('tc_received')->default(false);
            $table->boolean('dob_certificate_received')->default(false);

            $table->string('form_no')->nullable();
            $table->string('remarks_1')->nullable();
            $table->string('remarks_2')->nullable();

            $table->string('last_school_name')->nullable();
            $table->string('last_exam')->nullable();
            $table->string('last_exam_year')->nullable();
            $table->string('last_exam_status')->nullable();
            $table->string('last_exam_marks')->nullable();
            $table->string('last_exam_board')->nullable();

            $table->date('parents_anniversary_date')->nullable();
            $table->string('student_ref_id')->nullable();
            $table->string('biometric_card_no')->nullable();
            $table->string('child_uid')->nullable();
            $table->string('gr_no')->nullable();
            $table->string('pen_no')->nullable();
            $table->decimal('opening_balance', 12, 2)->nullable();

            $table->string('additional_field_1')->nullable();
            $table->string('additional_field_2')->nullable();
            $table->string('additional_field_3')->nullable();
            $table->string('additional_field_4')->nullable();
            $table->string('additional_field_5')->nullable();
            $table->string('additional_field_6')->nullable();
            $table->string('additional_field_7')->nullable();
            $table->string('additional_field_8')->nullable();
            $table->string('additional_field_9')->nullable();
            $table->string('additional_field_10')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_additional_details');
    }
};
