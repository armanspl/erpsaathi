<?php

use App\Services\DesignCatalog;
use App\Services\TemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORIES = ['report_card', 'exam_schedule'];

    public function up(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        foreach (self::CATEGORIES as $category) {
            if (! isset(TemplateCatalog::CATEGORIES[$category])) {
                continue;
            }

            [$width, $height] = DesignCatalog::pageSize($category);
            $rawHtml = DesignCatalog::seedHtml($category, 'classic');
            $name = TemplateCatalog::CATEGORIES[$category].' — Classic';

            $existingHtml = DB::table('templates')
                ->where('category', $category)
                ->where('render_mode', 'html')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($existingHtml) {
                DB::table('templates')->where('category', $category)->update(['is_default' => false]);
                DB::table('templates')->where('id', $existingHtml->id)->update([
                    'is_default' => true,
                    'raw_html' => $rawHtml,
                    'page_width_mm' => $width,
                    'page_height_mm' => $height,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('templates')->where('category', $category)->update(['is_default' => false]);

            DB::table('templates')->insert([
                'category' => $category,
                'name' => $name,
                'render_mode' => 'html',
                'is_default' => true,
                'is_favorite' => false,
                'page_width_mm' => $width,
                'page_height_mm' => $height,
                'background_color' => '#ffffff',
                'background_image_path' => null,
                'elements' => json_encode([]),
                'raw_html' => $rawHtml,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        foreach (self::CATEGORIES as $category) {
            DB::table('templates')
                ->where('category', $category)
                ->where('render_mode', 'html')
                ->where('name', 'like', '%— Classic')
                ->delete();
        }
    }
};
