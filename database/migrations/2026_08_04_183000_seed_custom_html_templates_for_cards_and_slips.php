<?php

use App\Services\DesignCatalog;
use App\Services\TemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORIES = [
        'admit_card',
        'id_card',
        'transport_card',
        'salary_slip',
        'book_expense',
    ];

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

            foreach (['classic', 'modern'] as $designKey) {
                $rawHtml = DesignCatalog::seedHtml($category, $designKey);
                $label = DesignCatalog::THEMES[$designKey];
                $name = TemplateCatalog::CATEGORIES[$category].' — '.$label;

                $existing = DB::table('templates')
                    ->where('category', $category)
                    ->where('render_mode', 'html')
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    DB::table('templates')->where('id', $existing->id)->update([
                        'raw_html' => $rawHtml,
                        'page_width_mm' => $width,
                        'page_height_mm' => $height,
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                DB::table('templates')->insert([
                    'category' => $category,
                    'name' => $name,
                    'render_mode' => 'html',
                    'is_default' => false,
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

            // Prefer Classic HTML as the category default for downloads/prints.
            $classic = DB::table('templates')
                ->where('category', $category)
                ->where('render_mode', 'html')
                ->where('name', TemplateCatalog::CATEGORIES[$category].' — Classic')
                ->first();

            if ($classic) {
                DB::table('templates')->where('category', $category)->update(['is_default' => false]);
                DB::table('templates')->where('id', $classic->id)->update(['is_default' => true, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        foreach (self::CATEGORIES as $category) {
            if (! isset(TemplateCatalog::CATEGORIES[$category])) {
                continue;
            }
            foreach (['Classic', 'Modern'] as $label) {
                DB::table('templates')
                    ->where('category', $category)
                    ->where('render_mode', 'html')
                    ->where('name', TemplateCatalog::CATEGORIES[$category].' — '.$label)
                    ->delete();
            }
        }
    }
};
