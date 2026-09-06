<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            // Raw text, never auto-linked to the real Transport/Hostel modules — same
            // precedent as Stoppage/Vehicle from Student Master Import (a route/room/bed
            // needs real geography/capacity data a form field can't supply).
            $table->string('route')->nullable()->after('stoppage');
            $table->string('hostel_room_no')->nullable()->after('hostel');
            $table->string('hostel_bed_no')->nullable()->after('hostel_room_no');
        });
    }

    public function down(): void
    {
        Schema::table('student_udise_details', function (Blueprint $table) {
            $table->dropColumn(['route', 'hostel_room_no', 'hostel_bed_no']);
        });
    }
};
