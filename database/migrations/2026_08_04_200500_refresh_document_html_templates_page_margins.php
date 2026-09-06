<?php

use App\Models\Template;
use App\Services\DesignCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-seed DesignCatalog HTML templates so PDF margin/clipping fixes apply to downloads.
 * Only touches html-mode rows whose markup still uses the old outer .doc padding pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (array_keys(DesignCatalog::PAGE_SIZES) as $category) {
            Template::query()
                ->where('category', $category)
                ->where('render_mode', 'html')
                ->whereNotNull('raw_html')
                ->orderBy('id')
                ->each(function (Template $template) use ($category) {
                    $html = (string) $template->raw_html;
                    $needsRefresh = str_contains($html, 'padding: 2.5mm')
                        || str_contains($html, 'padding: 5mm')
                        || str_contains($html, 'padding: 6mm')
                        || str_contains($html, 'padding: 7mm')
                        || str_contains($html, 'margin: -')
                        || str_contains($html, 'margin:-');

                    if (! $needsRefresh) {
                        return;
                    }

                    $theme = str_contains(strtolower((string) $template->name), 'modern')
                        || str_contains($html, 'doc-accent')
                        || str_contains($html, 'class="accent"')
                        || str_contains($html, "class='accent'")
                        ? 'modern'
                        : 'classic';

                    if (! view()->exists("documents.templates.{$theme}.{$category}")) {
                        return;
                    }

                    $template->update([
                        'raw_html' => DesignCatalog::seedHtml($category, $theme),
                    ]);
                });
        }
    }

    public function down(): void
    {
        //
    }
};
