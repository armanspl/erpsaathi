<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // RTE (Right to Education) — no school fees; transport is still charged if used.
            $table->boolean('is_rte')->default(false)->after('status')->index();
            // RTE applies from this session's start onward; earlier sessions keep their fees.
            $table->unsignedBigInteger('rte_from_session_id')->nullable()->after('is_rte');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['is_rte']);
            $table->dropColumn(['is_rte', 'rte_from_session_id']);
        });
    }
};
