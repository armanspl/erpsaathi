<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The typography/spacing boost in the two previous migrations pushed a fully-filled
 * Transfer Certificate onto a 2nd page — mainly because:
 *  - .tc-checked-date's padding now actually renders (previously a <span> margin-top,
 *    which Dompdf silently ignores), stacking on top of the pre-existing 35mm
 *    .tc-footer-row padding-top instead of replacing it.
 *  - 11pt/line-height 1.45 plus 4.5mm gaps on every side of a 23-row table compounds
 *    fast, and shrinks the label column's usable width, forcing more lines to wrap.
 * This dials the sizes back to values that still read as "larger with more breathing
 * room" than the original, but fit one page with every field filled in.
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
            $updated = $this->fit($html);

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

    private function fit(string $html): string
    {
        // Fields 1–23 — dial back from 11pt/1.45 to 10pt/1.25 (still above the original 9pt).
        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?font-size\s*:\s*)11pt/i',
            '${1}10pt',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?line-height\s*:\s*)1\.45/i',
            '${1}1.25',
            $html,
            1
        ) ?? $html;

        // Smaller (but still real) horizontal gaps, and give the label column its width
        // back so long labels wrap onto fewer extra lines.
        $html = preg_replace(
            '/\.tc-list\s+\.num\s*\{[^}]*\}/i',
            '.tc-list .num { width: 5%; padding-right: 2mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.label\s*\{[^}]*\}/i',
            '.tc-list .label { width: 60%; padding-right: 2mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.colon\s*\{[^}]*\}/i',
            '.tc-list .colon { width: 5%; text-align: center; padding: 0 1.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.value\s*\{[^}]*\}/i',
            '.tc-list .value { width: 30%; font-weight: bold; padding-left: 2mm; }',
            $html,
            1
        ) ?? $html;

        // The 35mm footer gap was tuned for when .tc-checked-date's own spacing was a
        // no-op (Dompdf ignores span margin-top) — now that it's a real block-level
        // padding, the footer's own padding-top needs to shrink to compensate, or the
        // two stack and the footer runs onto page 2.
        $html = preg_replace(
            '/(\.tc-footer-row\s+td\s*\{[^}]*?padding-top\s*:\s*)35mm/i',
            '${1}10mm',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/(\.tc-checked-date\s*\{[^}]*?padding-top\s*:\s*)8mm/i',
            '${1}4mm',
            $html,
            1
        ) ?? $html;

        return $html;
    }
};
