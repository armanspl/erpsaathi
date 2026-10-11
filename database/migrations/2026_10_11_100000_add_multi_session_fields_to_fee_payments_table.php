<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One real receipt can pay fees of two academic sessions (e.g. last session's February tuition
 * plus this session's Session Fee on the same receipt). Such a payment keeps one row; each item
 * carries its own academic_session_id, `multi_session` marks the row so session-scoped queries
 * can find it cheaply, and `session_fines` splits its fine per session ({"4": 50, "5": 50}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_payments', 'multi_session')) {
                $table->boolean('multi_session')->default(false)->after('academic_session_id')->index();
            }
            if (! Schema::hasColumn('fee_payments', 'session_fines')) {
                $table->json('session_fines')->nullable()->after('fine_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            if (Schema::hasColumn('fee_payments', 'multi_session')) {
                $table->dropIndex(['multi_session']);
                $table->dropColumn('multi_session');
            }
            if (Schema::hasColumn('fee_payments', 'session_fines')) {
                $table->dropColumn('session_fines');
            }
        });
    }
};
