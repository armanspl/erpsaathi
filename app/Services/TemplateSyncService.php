<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps render_mode="html" "system-managed" templates (source='system') in sync with the
 * current Blade source — replacing the old approach of writing a new one-off str_replace()
 * migration for every Report Card CSS/HTML tweak (see e.g. 2026_09_29_000000_report_card_wider_
 * layout_and_bigger_header.php).
 *
 * How it decides whether a row needs a refresh: template_version holds a sha256 of whatever
 * DesignCatalog::seedHtml() currently produces for that row's (category, design_key). A
 * mismatch means the Blade source (or TemplateCatalog::FIELDS) has moved on since this row's
 * raw_html was last written, so it's due for a refresh; a match means it's already current —
 * making a repeat run a no-op (the idempotency guarantee requested for erp:deploy).
 *
 * A template an admin has hand-edited via the Template Editor's Code panel is flagged
 * source='custom' the moment that happens (see TemplateController::updateCode()) and is never
 * touched by this automatic path — only the explicit, confirmation-gated "Regenerate from
 * template" action (TemplateController::regenerate()) can resync one of those.
 */
class TemplateSyncService
{
    /**
     * Categories the automatic erp:deploy sync pass covers. Scoped to report_card for this
     * rollout (that's the category with the fragile one-off-migration history); add a category
     * here once its design is stable enough to want the same automatic treatment.
     */
    public const AUTO_SYNC_CATEGORIES = ['report_card'];

    /** Current content hash for a (category, design_key) pair — what a synced row's template_version should equal. */
    public static function currentVersion(string $category, string $designKey): string
    {
        return hash('sha256', DesignCatalog::seedHtml($category, $designKey));
    }

    /**
     * Regenerates one template's raw_html from the current Blade source and stamps it
     * system-managed + up to date. Used by both the automatic sync pass (only ever called
     * there on source='system' rows) and the admin's explicit "Regenerate" action (which may
     * call this on a source='custom' row, but only after the caller has obtained explicit
     * confirmation — see TemplateController::regenerate()). Either way, the result is the same:
     * raw_html now matches the Blade source exactly, so the row is marked 'system' again.
     */
    public static function regenerate(Template $template, ?string $designKey = null): Template
    {
        $designKey ??= $template->design_key ?: 'classic';
        $html = DesignCatalog::seedHtml($template->category, $designKey);

        $template->update([
            'render_mode' => 'html',
            'raw_html' => $html,
            'design_key' => $designKey,
            'source' => 'system',
            'template_version' => hash('sha256', $html),
            'synced_at' => now(),
        ]);

        return $template;
    }

    /**
     * Safe, automatic sync pass for whichever DB connection is active (or the given one) — run
     * from erp:deploy after a tenant's migrations. Only ever touches rows that are all of:
     * render_mode='html', category in AUTO_SYNC_CATEGORIES, source='system', and whose
     * template_version doesn't match the current Blade output — so a customized template, a
     * non-report_card template, or a row already on the latest version is always left alone.
     *
     * @return array{synced: int, skipped_customized: int, already_synced: int, synced_names: list<string>}
     */
    public static function syncAll(?string $connection = null): array
    {
        $synced = 0;
        $skippedCustomized = 0;
        $alreadySynced = 0;
        $syncedNames = [];

        foreach (self::AUTO_SYNC_CATEGORIES as $category) {
            /** @var Builder $query */
            $query = $connection ? Template::on($connection) : Template::query();
            $templates = $query->where('category', $category)->where('render_mode', 'html')->get();

            if ($templates->isEmpty()) {
                continue;
            }

            // One Blade render per distinct design_key actually in use (plus 'classic' as the
            // fallback for any legacy row still missing one) rather than per row.
            $designKeys = $templates->pluck('design_key')->filter()->push('classic')->unique();
            $currentVersions = [];
            foreach ($designKeys as $key) {
                try {
                    $currentVersions[$key] = self::currentVersion($category, $key);
                } catch (\Throwable) {
                    // No Blade view for this design key — leave it out; rows using it are
                    // skipped below (treated the same as "nothing to compare against").
                }
            }

            foreach ($templates as $template) {
                if ($template->source === 'custom') {
                    $skippedCustomized++;

                    continue;
                }

                $designKey = $template->design_key ?: 'classic';
                $currentHash = $currentVersions[$designKey] ?? null;
                if ($currentHash === null) {
                    continue;
                }

                if ($template->template_version === $currentHash) {
                    $alreadySynced++;

                    continue;
                }

                self::regenerate($template, $designKey);
                $synced++;
                $syncedNames[] = $template->name;
            }
        }

        return [
            'synced' => $synced,
            'skipped_customized' => $skippedCustomized,
            'already_synced' => $alreadySynced,
            'synced_names' => $syncedNames,
        ];
    }
}
