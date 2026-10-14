<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            // Super Admin switch: may this school enter fee receipts with a past payment date,
            // its own receipt no. and a chosen session (Fee Receipt → Back-date entry).
            $table->boolean('allow_backdate_fee')->default(false)->after('demo_settings');
        });

        // Schools that already exist could always pick any payment date on Fee Receipt — keep
        // that working for them; only schools added from now on start with it switched off.
        DB::connection('master')->table('schools')->update(['allow_backdate_fee' => true]);
    }

    public function down(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            $table->dropColumn('allow_backdate_fee');
        });
    }
};
