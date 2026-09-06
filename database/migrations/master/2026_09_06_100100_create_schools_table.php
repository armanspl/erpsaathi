<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('db_name')->unique();
            $table->string('db_host')->nullable();
            $table->string('status', 32)->default('provisioning'); // provisioning|active|inactive|failed
            $table->string('admin_email')->nullable();
            $table->string('storage_path')->nullable();
            $table->boolean('is_first_school')->default(false);
            $table->text('notes')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('schools');
    }
};
