<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->string('father_photo_path')->nullable()->after('mother_pan_path');
            $table->string('mother_photo_path')->nullable()->after('father_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropColumn(['father_photo_path', 'mother_photo_path']);
        });
    }
};
