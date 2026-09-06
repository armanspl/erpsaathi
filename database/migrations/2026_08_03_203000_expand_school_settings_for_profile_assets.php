<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->string('school_code')->nullable()->after('school_name');
            $table->string('registration_no')->nullable()->after('school_code');
            $table->string('udise_code')->nullable()->after('registration_no');
            $table->string('address_line_1')->nullable()->after('address');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode', 20)->nullable()->after('state');
            $table->string('banner_path')->nullable()->after('logo_path');
            $table->string('favicon_path')->nullable()->after('banner_path');
            $table->string('browser_title')->nullable()->after('favicon_path');
            $table->text('meta_description')->nullable()->after('browser_title');
            $table->string('instagram')->nullable()->after('website');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('youtube')->nullable()->after('facebook');
            $table->string('linkedin')->nullable()->after('youtube');
            $table->string('twitter')->nullable()->after('linkedin');
            $table->string('watermark_text')->nullable()->after('twitter');
            $table->boolean('show_watermark')->default(true)->after('watermark_text');
            $table->boolean('compact_sidebar')->default(false)->after('show_watermark');
            $table->json('signatures')->nullable()->after('compact_sidebar');
            $table->json('stamps')->nullable()->after('signatures');
        });

        // Preserve any existing single-line address into address_line_1.
        foreach (DB::table('school_settings')->get() as $row) {
            if (! empty($row->address) && empty($row->address_line_1 ?? null)) {
                DB::table('school_settings')->where('id', $row->id)->update([
                    'address_line_1' => $row->address,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'school_code', 'registration_no', 'udise_code',
                'address_line_1', 'address_line_2', 'city', 'state', 'pincode',
                'banner_path', 'favicon_path', 'browser_title', 'meta_description',
                'instagram', 'facebook', 'youtube', 'linkedin', 'twitter',
                'watermark_text', 'show_watermark', 'compact_sidebar',
                'signatures', 'stamps',
            ]);
        });
    }
};
