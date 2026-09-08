<?php

namespace App\Services;

use Illuminate\Support\Facades\View;

/**
 * Pre-built Blade designs offered when creating a new template — an alternative starting point
 * to the blank canvas starter. Each category ships theme keys; picking one seeds a
 * render_mode="html" template (see DocumentRenderService) whose raw_html is generated
 * once here and then edited/saved like any other custom-HTML template via the Code panel.
 */
class DesignCatalog
{
    /** Default themes for most document categories. */
    public const THEMES = [
        'classic' => 'Classic',
        'modern' => 'Modern',
    ];

    /** Admit Card ships three layout options (A4 Classic, A5 compact, A4 Modern). */
    public const ADMIT_CARD_THEMES = [
        'classic' => 'Classic (A4)',
        'a5' => 'A5 Compact (2 per A4)',
        'modern' => 'Modern (A4)',
    ];

    /** [width_mm, height_mm] default per category. */
    public const PAGE_SIZES = [
        'certificate' => [297, 210],
        'admit_card' => [210, 297],
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

    /** Optional per-design page size overrides: category => designKey => [w, h]. */
    public const DESIGN_PAGE_SIZES = [
        'admit_card' => [
            'classic' => [210, 297],
            'modern' => [210, 297],
            'a5' => [148, 210],
        ],
    ];

    /** @return array<string, string> theme key => label for a category */
    public static function themesFor(string $category): array
    {
        return $category === 'admit_card' ? self::ADMIT_CARD_THEMES : self::THEMES;
    }

    /** @return array<int, array{key: string, label: string}> */
    public static function list(string $category): array
    {
        if (! isset(self::PAGE_SIZES[$category])) {
            return [];
        }

        return collect(self::themesFor($category))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    public static function pageSize(string $category, ?string $designKey = null): array
    {
        if ($designKey && isset(self::DESIGN_PAGE_SIZES[$category][$designKey])) {
            return self::DESIGN_PAGE_SIZES[$category][$designKey];
        }

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
