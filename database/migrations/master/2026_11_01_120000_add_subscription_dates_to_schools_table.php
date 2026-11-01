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
            $table->date('creation_date')->nullable()->after('billing_currency');
            $table->date('renewal_date')->nullable()->after('creation_date');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            $table->dropColumn(['creation_date', 'renewal_date']);
        });
    }
};
