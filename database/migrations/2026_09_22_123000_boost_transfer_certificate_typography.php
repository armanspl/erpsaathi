<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Further Transfer Certificate typography polish:
 * - Larger school name
 * - Larger fields 1–23 with more horizontal gaps
 * - Reliable Checked By ↔ Date vertical gap (Dompdf ignores span margin-top)
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
            $updated = $this->polish($html);

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

    private function polish(string $html): string
    {
        // School name — more prominent
        $html = preg_replace(
            '/(\.tc-school\s*\{[^}]*?font-size\s*:\s*)(?:18|20|21|22)pt/i',
            '${1}23pt',
            $html,
            1
        ) ?? $html;

        // Fields 1–23 — larger type
        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?font-size\s*:\s*)(?:9(?:\.8)?|10|10\.5)pt/i',
            '${1}11pt',
            $html,
            1
        ) ?? $html;

        // Slightly taller row line-height for readability
        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?line-height\s*:\s*)[\d.]+/i',
            '${1}1.45',
            $html,
            1
        ) ?? $html;

        // More horizontal gaps between number / label / colon / value
        $html = preg_replace(
            '/\.tc-list\s+\.num\s*\{[^}]*\}/i',
            '.tc-list .num { width: 6%; padding-right: 4.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.label\s*\{[^}]*\}/i',
            '.tc-list .label { width: 55%; padding-right: 4.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.colon\s*\{[^}]*\}/i',
            '.tc-list .colon { width: 7%; text-align: center; padding: 0 2.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.value\s*\{[^}]*\}/i',
            '.tc-list .value { width: 32%; font-weight: bold; padding-left: 4.5mm; }',
            $html,
            1
        ) ?? $html;

        // Dompdf-friendly Checked By / Date gap (padding on a block div, not span margin)
        $html = preg_replace(
            '/\.tc-checked-date\s*\{[^}]*\}/i',
            '.tc-checked-date { display: block; padding-top: 8mm; margin: 0; line-height: 1.2; }',
            $html,
            1
        ) ?? $html;

        // Normalize footer markup: use a div so Dompdf applies padding-top
        $html = preg_replace(
            '/Checked By\s*<br\s*\/?>\s*<span class="tc-checked-date">Date:\s*\{\{issue_date_numeric\}\}<\/span>/i',
            'Checked By<br /><div class="tc-checked-date">Date: {{issue_date_numeric}}</div>',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/Checked By\s*<br\s*\/?>\s*Date:\s*\{\{issue_date_numeric\}\}/i',
            'Checked By<br /><div class="tc-checked-date">Date: {{issue_date_numeric}}</div>',
            $html,
            1
        ) ?? $html;

        return $html;
    }
};
