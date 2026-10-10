<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('super_admin_db_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->nullable()->index();
            $table->string('super_admin_email')->nullable();
            $table->foreignId('school_id')->nullable()->index();
            $table->string('db_name', 128);
            $table->string('action', 40);
            $table->string('table_name', 64)->nullable();
            $table->json('record_key')->nullable();
            $table->json('details')->nullable();
            $table->unsignedInteger('affected_rows')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('super_admin_db_audits');
    }
};
