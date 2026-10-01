<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * class_subject stays the class-level subject catalog — this only tags which of a class's
 * subjects are optional/elective (e.g. Urdu vs Sanskrit) instead of compulsory for everyone.
 * elective_group is a plain label (e.g. "Language") for grouping related electives in the UI;
 * it carries no enforcement (a student can hold more than one), kept intentionally simple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('subject_id');
            $table->string('elective_group', 60)->nullable()->after('is_optional');
        });
    }

    public function down(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'elective_group']);
        });
    }
};
