<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Transfer Certificate polish:
 * - Slightly larger school name
 * - Slightly larger field labels/values (1–23) with more horizontal gaps
 * - No visible borders around the fields table or principal signature area
 * - More vertical space between "Checked By" and the date line
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
        // 1) School name — slightly more prominent
        $html = preg_replace(
            '/(\.tc-school\s*\{[^}]*?font-size\s*:\s*)18pt/i',
            '${1}20pt',
            $html,
            1
        ) ?? $html;

        // 2) Fields 1–23 — slightly larger type
        $html = preg_replace(
            '/(\.tc-list\s*\{[^}]*?font-size\s*:\s*)9pt/i',
            '${1}9.8pt',
            $html,
            1
        ) ?? $html;

        // Horizontal gaps between number / label / colon / value
        $html = preg_replace(
            '/\.tc-list\s+\.num\s*\{[^}]*\}/i',
            '.tc-list .num { width: 5%; padding-right: 2.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.label\s*\{[^}]*\}/i',
            '.tc-list .label { width: 58%; padding-right: 2.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.colon\s*\{[^}]*\}/i',
            '.tc-list .colon { width: 6%; text-align: center; padding: 0 1.5mm; }',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/\.tc-list\s+\.value\s*\{[^}]*\}/i',
            '.tc-list .value { width: 31%; font-weight: bold; padding-left: 2.5mm; }',
            $html,
            1
        ) ?? $html;

        // 3) Remove any visible borders around the fields table / signature block
        $html = preg_replace(
            '/\.tc-list\s+td\s*\{[^}]*\}/i',
            '.tc-list td { padding: 0.55mm 0; vertical-align: top; border: none !important; outline: none; box-sizing: border-box; overflow-wrap: anywhere; word-wrap: break-word; }',
            $html,
            1
        ) ?? $html;

        if (! preg_match('/\.tc-list\s*,\s*\.tc-list\s+tr/i', $html)) {
            $html = preg_replace(
                '/(\.tc-list\s*\{)/i',
                ".tc-list, .tc-list tr, .tc-list td { border: none !important; outline: none; }\n\$1",
                $html,
                1
            ) ?? $html;
        }

        $html = preg_replace(
            '/\.tc-sig-wrap\s*\{[^}]*\}/i',
            '.tc-sig-wrap { display: inline-block; text-align: center; border: none !important; outline: none; box-shadow: none; }',
            $html,
            1
        ) ?? $html;

        if (! preg_match('/\.tc-footer-right\s*\{[^}]*border\s*:/i', $html)) {
            $html = preg_replace(
                '/(\.tc-footer-row\s+\.tc-footer-right\s*\{)/i',
                '$1 border: none !important; outline: none;',
                $html,
                1
            ) ?? $html;
        }

        // 4) More vertical space between Checked By and Date
        if (! preg_match('/\.tc-checked-date\s*\{/i', $html)) {
            $html = preg_replace(
                '/(<\/style>)/i',
                ".tc-checked-date { display: block; margin-top: 4.5mm; }\n\$1",
                $html,
                1
            ) ?? $html;
        }

        $html = preg_replace(
            '/Checked By\s*<br\s*\/?>\s*Date:/i',
            'Checked By<br /><span class="tc-checked-date">Date:',
            $html,
            1
        ) ?? $html;

        // Close the span if we opened it and it isn't already closed before </td>
        if (str_contains($html, 'class="tc-checked-date">Date:')
            && ! preg_match('/class="tc-checked-date">Date:[^<]*<\/span>/i', $html)
        ) {
            $html = preg_replace(
                '/(class="tc-checked-date">Date:\s*\{\{issue_date_numeric\}\})/i',
                '$1</span>',
                $html,
                1
            ) ?? $html;
        }

        return $html;
    }
};
