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
            $table->decimal('price', 12, 2)->nullable()->after('admin_email');
            $table->decimal('renewal_charge', 12, 2)->nullable()->after('price');
            $table->string('billing_currency', 8)->default('INR')->after('renewal_charge');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('schools', function (Blueprint $table) {
            $table->dropColumn(['price', 'renewal_charge', 'billing_currency']);
        });
    }
};
