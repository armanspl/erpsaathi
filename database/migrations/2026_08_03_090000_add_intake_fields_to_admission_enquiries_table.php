<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_enquiries', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            $table->boolean('whatsapp_optin')->default(false)->after('phone');
            $table->string('present_school')->nullable()->after('class_applying_for_id');
            $table->string('address_line_1')->nullable()->after('present_school');
            $table->string('city')->nullable()->after('address_line_1');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode')->nullable()->after('state');
            $table->string('preferred_contact_time')->nullable()->after('source');
            $table->string('preferred_mode')->nullable()->after('preferred_contact_time');
            $table->date('preferred_contact_date')->nullable()->after('preferred_mode');
            $table->boolean('transport_required')->default(false)->after('preferred_contact_date');
            $table->boolean('consent_given')->default(false)->after('transport_required');
        });
    }

    public function down(): void
    {
        Schema::table('admission_enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'whatsapp_optin', 'present_school', 'address_line_1', 'city', 'state', 'pincode',
                'preferred_contact_time', 'preferred_mode', 'preferred_contact_date',
                'transport_required', 'consent_given',
            ]);
        });
    }
};
