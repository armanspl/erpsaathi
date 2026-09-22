<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The previous fit-to-one-page pass was tuned against a near-empty school address; a
 * real school profile (multi-line address, real logo) pushes the field list down just
 * enough that the "Checked By / Sign of Principal" row no longer fits the remaining
 * space on page 1 — and since a table row doesn't split across pages, the *whole* row
 * jumps to page 2 regardless of how much its own padding is trimmed. Reclaims a few more
 * mm from the list's line-height and a couple of small header/footer paddings instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'certificate')
            ->where('raw_html', 'like', '%tc-page%')
            ->get(['id', 'raw_html']);

        foreach ($rows as $row) {
            $html = (string) $row->raw_html;
            $updated = $this->reclaim($html);

            if ($updated !== $html) {
                DB::table('templates')->where('id', $row->id)->update([
                    'raw_html' => $updated,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Layout polish — not reverted.
    }

    private function reclaim(string $html): string
    {
        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?line-height\s*:\s*)1\.25/i',
            '${1}1.15',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-footer-row\s+td\s*\{[^}]*?padding-top\s*:\s*)10mm/i',
            '${1}4mm',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-refrow-row\s+td\s*\{[^}]*?padding-bottom\s*:\s*)3mm/i',
            '${1}1.5mm',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-titlebar\s*\{[^}]*?margin\s*:\s*)2\.5mm 0/i',
            '${1}1.5mm 0',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-sig-img\s*\{[^}]*?max-height\s*:\s*)14mm/i',
            '${1}11mm',
            $html,
            1
        ) ?? $html;

        return $html;
    }
};
