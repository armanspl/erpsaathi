<?php

use App\Models\Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the metadata the "frozen templates.raw_html" design has been missing since day
 * one: a way to tell a system-managed template (still tracking the Blade source, safe to
 * auto-refresh) apart from one an admin has hand-edited via the Template Editor's Code panel
 * (must never be silently overwritten). See TemplateSyncService for how these are used.
 *
 * - design_key: which Blade design ("classic"/"modern"/"a5"/...) this row was seeded from —
 *   DesignCatalog::seedHtml() needs this to know which view to re-render on a sync.
 * - source: 'system' (tracks the Blade source, auto-synced by erp:deploy) or 'custom' (an
 *   admin has edited raw_html directly — frozen until they explicitly reset/resync it).
 * - template_version: sha256 of what DesignCatalog::seedHtml() currently produces for this
 *   row's (category, design_key) — a mismatch means the Blade source has moved on since this
 *   row's raw_html was last written. Backfilled to NULL for every existing row so the very
 *   first sync pass after this migration brings every tenant's existing system-managed
 *   templates up to the current Blade source in one pass (closing the drift between tenants
 *   that accumulated from the old one-off str_replace() migrations).
 * - synced_at: last time this row's raw_html was (re)written by the sync mechanism — audit/
 *   display only, not used for any sync decision (template_version is).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('design_key')->nullable()->after('render_mode');
            $table->string('source', 20)->default('system')->after('design_key');
            $table->string('template_version', 64)->nullable()->after('source');
            $table->timestamp('synced_at')->nullable()->after('template_version');
        });

        // Every pre-existing row is treated as system-managed (the only thing that could have
        // written raw_html until now was DesignCatalog::seedHtml() or one of the old one-off
        // migrations — both "system" writes in spirit); design_key is guessed from the name
        // ("... — Modern" / "... (A5 Compact...)" etc., the labels DesignCatalog::THEMES /
        // ADMIT_CARD_THEMES use), defaulting to 'classic'. template_version is left NULL on
        // purpose — see class docblock.
        Template::query()->where('render_mode', 'html')->whereNull('design_key')->get()->each(function (Template $template) {
            $name = mb_strtolower((string) $template->name);
            $designKey = match (true) {
                str_contains($name, 'modern') => 'modern',
                str_contains($name, 'a5') => 'a5',
                default => 'classic',
            };
            $template->forceFill(['design_key' => $designKey, 'source' => 'system'])->save();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['design_key', 'source', 'template_version', 'synced_at']);
        });
    }
};
