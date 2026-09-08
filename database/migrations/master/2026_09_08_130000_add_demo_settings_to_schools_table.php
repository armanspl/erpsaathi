<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            $table->json('demo_settings')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            $table->dropColumn('demo_settings');
        });
    }
};
