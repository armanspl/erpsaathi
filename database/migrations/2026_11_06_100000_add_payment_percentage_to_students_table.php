<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('students', 'payment_percentage')) {
            return;
        }
        Schema::table('students', function (Blueprint $table) {
            // Share of the class fee structure this student pays: 100 = full fee, 50 = HF, 0 = FF.
            // Existing students get 100, so their fees and saved records stay as they are.
            $table->decimal('payment_percentage', 5, 2)->default(100)->after('fee_start_month');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'payment_percentage')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('payment_percentage');
            });
        }
    }
};
