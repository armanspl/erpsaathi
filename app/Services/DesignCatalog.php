<?php

namespace App\Services;

use Illuminate\Support\Facades\View;

/**
 * Pre-built Blade designs offered when creating a new template — an alternative starting point
 * to the blank canvas starter. Each category ships two themes ("classic" and "modern"); picking
 * one seeds a render_mode="html" template (see DocumentRenderService) whose raw_html is generated
 * once here and then edited/saved like any other custom-HTML template via the Code panel.
 *
 * Blade is only used as an authoring/rendering convenience at seed time — the resulting HTML is
 * static. Real per-document values are filled in later by the existing {{field}} substitution in
 * DocumentRenderService, exactly like a hand-pasted custom-HTML template. This means fields that
 * are pre-flattened multi-line strings (items_table, marks_table, etc.) render as a single
 * monospace block rather than a row-by-row HTML table — a real semantic table would need each
 * document type's row data exposed as its own token, which isn't part of this pass.
 */
class DesignCatalog
{
    public const THEMES = [
        'classic' => 'Classic',
        'modern' => 'Modern',
    ];

    /** [width_mm, height_mm] per category — matches the dimensions the canvas starters already use. */
    public const PAGE_SIZES = [
        'certificate' => [297, 210],
        'admit_card' => [148, 210],
        'id_card' => [85.6, 54],
        'transport_card' => [85.6, 54],
        'library_card' => [85.6, 54],
        'report_card' => [210, 297],
        'exam_schedule' => [297, 210],
        'fee_receipt' => [210, 297],
        'fee_due_receipt' => [210, 297],
        'salary_slip' => [210, 297],
        'book_expense' => [210, 148],
    ];

    /** @return array<int, array{key: string, label: string}> */
    public static function list(string $category): array
    {
        if (! isset(self::PAGE_SIZES[$category])) {
            return [];
        }

        return collect(self::THEMES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    public static function pageSize(string $category): array
    {
        return self::PAGE_SIZES[$category] ?? [210, 297];
    }

    /** Seed HTML for a newly created template — every field renders as its own literal {{token}}. */
    public static function seedHtml(string $category, string $designKey): string
    {
        $tokens = collect(TemplateCatalog::FIELDS[$category] ?? [])
            ->mapWithKeys(fn ($f) => [$f[0] => '{{'.$f[0].'}}'])
            ->all();

        return self::render($category, $designKey, $tokens);
    }

    /** Live preview HTML using real sample data — for the design-picker cards. */
    public static function previewHtml(string $category, string $designKey): string
    {
        return self::render($category, $designKey, TemplateCatalog::sampleData($category));
    }

    private static function render(string $category, string $designKey, array $data): string
    {
        $view = "documents.templates.{$designKey}.{$category}";
        abort_unless(View::exists($view), 404, "No [{$designKey}] design for category [{$category}].");

        return view($view, $data)->render();
    }
}
