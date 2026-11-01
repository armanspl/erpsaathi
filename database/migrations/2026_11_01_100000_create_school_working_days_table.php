<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School-wide working days per calendar month (e.g. Mar 2026 = 20), entered on
 * Attendance › Student Attendance › Working Days and shown on the dashboard. Student monthly
 * attendance pre-fills its Working Days from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_working_days')) {
            return;
        }

        Schema::create('school_working_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('working_days');
            $table->foreignId('updated_by_id')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_working_days');
    }
};
