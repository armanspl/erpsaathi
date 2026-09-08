<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds document templates (and certificate type links) from the global-school snapshot
 * under database/data/global_school_templates/.
 */
class GlobalSchoolTemplateImporter
{
    public static function dataPath(string $file): string
    {
        return database_path('data/global_school_templates/'.$file);
    }

    public function import(): int
    {
        if (! Schema::hasTable('templates')) {
            return 0;
        }

        $templatesPath = self::dataPath('templates.json');
        if (! File::exists($templatesPath)) {
            return 0;
        }

        $templates = json_decode(File::get($templatesPath), true);
        if (! is_array($templates)) {
            return 0;
        }

        $imported = 0;
        $defaultsByCategory = [];

        foreach ($templates as $row) {
            if (! is_array($row) || empty($row['category']) || empty($row['name'])) {
                continue;
            }

            $payload = [
                'category' => (string) $row['category'],
                'name' => (string) $row['name'],
                'render_mode' => (string) ($row['render_mode'] ?? 'html'),
                'is_default' => (bool) ($row['is_default'] ?? false),
                'is_favorite' => (bool) ($row['is_favorite'] ?? false),
                'page_width_mm' => $row['page_width_mm'] ?? 210,
                'page_height_mm' => $row['page_height_mm'] ?? 297,
                'background_color' => $row['background_color'] ?? '#ffffff',
                'background_image_path' => $row['background_image_path'] ?? null,
                'elements' => $this->normalizeJsonColumn($row['elements'] ?? []),
                'raw_html' => $row['raw_html'] ?? null,
                'updated_at' => now(),
            ];

            $existing = DB::table('templates')
                ->where('category', $payload['category'])
                ->where('name', $payload['name'])
                ->first();

            if ($existing) {
                DB::table('templates')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('templates')->insert($payload);
            }

            if ($payload['is_default']) {
                $defaultsByCategory[$payload['category']] = $payload['name'];
            }

            $imported++;
        }

        foreach ($defaultsByCategory as $category => $defaultName) {
            DB::table('templates')->where('category', $category)->update(['is_default' => false]);
            DB::table('templates')
                ->where('category', $category)
                ->where('name', $defaultName)
                ->update(['is_default' => true, 'updated_at' => now()]);
        }

        // Prefer the live global-school TC over the older official-format seed name.
        if (DB::table('templates')->where('category', 'certificate')->where('name', 'Transfer Certificate')->exists()) {
            $legacy = DB::table('templates')
                ->where('category', 'certificate')
                ->where('name', 'Transfer Certificate (Official Format)')
                ->first();

            if ($legacy) {
                if (Schema::hasTable('certificate_types')) {
                    DB::table('certificate_types')
                        ->where('template_id', $legacy->id)
                        ->update(['template_id' => null]);
                }
                DB::table('templates')->where('id', $legacy->id)->delete();
            }
        }

        $this->syncCertificateTypes();

        return $imported;
    }

    private function syncCertificateTypes(): void
    {
        if (! Schema::hasTable('certificate_types')) {
            return;
        }

        $typesPath = self::dataPath('certificate_types.json');
        if (! File::exists($typesPath)) {
            return;
        }

        $types = json_decode(File::get($typesPath), true);
        if (! is_array($types)) {
            return;
        }

        foreach ($types as $row) {
            if (! is_array($row) || empty($row['label']) || empty($row['prefix'])) {
                continue;
            }

            $templateName = $row['template_name'] ?? null;
            $templateId = null;
            if ($templateName) {
                $templateId = DB::table('templates')
                    ->where('category', 'certificate')
                    ->where('name', $templateName)
                    ->value('id');
            }

            $payload = [
                'label' => (string) $row['label'],
                'prefix' => (string) $row['prefix'],
                'roles' => $this->normalizeJsonColumn($row['roles'] ?? ['student']),
                'custom_fields' => $this->normalizeJsonColumn($row['custom_fields'] ?? []),
                'is_system' => (bool) ($row['is_system'] ?? true),
                'template_id' => $templateId,
                'updated_at' => now(),
            ];

            $existing = DB::table('certificate_types')
                ->where(function ($q) use ($payload) {
                    $q->where('prefix', $payload['prefix'])
                        ->orWhere('label', $payload['label']);
                })
                ->first();

            if ($existing) {
                DB::table('certificate_types')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('certificate_types')->insert($payload);
            }
        }
    }

    private function normalizeJsonColumn(mixed $value): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '[]';
            }
            json_decode($trimmed);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $trimmed;
            }

            return json_encode($value);
        }

        return json_encode($value ?? []);
    }
}
