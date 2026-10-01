<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Layout-only pass over the frozen templates.raw_html rows for report_card (see the
 * matching blade edits in resources/views/documents/templates/{classic,modern}/report_card.blade.php):
 *   - bigger school-name/school-line header text
 *   - narrower page side-margins (more usable width)
 *   - modestly larger paddings/margins/font-sizes throughout, so the card fills the
 *     A4 page better instead of leaving a blank gap below the signature row.
 * No data columns, marks, calculations, or structural HTML are touched — CSS values only.
 * Frozen raw_html uses single-brace tokens ({{accent_color}}), not blade's {{ $accent_color }}.
 */
return new class extends Migration
{
    /** [search, replace] pairs. Applied to every report_card row regardless of design;
     *  a pair that doesn't match that row's design is simply a no-op. */
    private const PAIRS = [
        // shared / classic
        ['@page { margin: 6mm 8mm; size: A4 portrait; }', '@page { margin: 6mm 5mm; size: A4 portrait; }'],
        ['.sheet { border: 1.2pt solid #111; padding: 5pt 8pt 6pt; }', '.sheet { border: 1.2pt solid #111; padding: 7pt 10pt 8pt; }'],
        ['.top-meta { width: 100%; border-collapse: collapse; margin-bottom: 2pt; font-size: 8pt; }', '.top-meta { width: 100%; border-collapse: collapse; margin-bottom: 3pt; font-size: 8.5pt; }'],
        ['.top-meta { width: 100%; border-collapse: collapse; margin-bottom: 2pt; font-size: 8pt; color: #6b7280; }', '.top-meta { width: 100%; border-collapse: collapse; margin-bottom: 3pt; font-size: 8.5pt; color: #6b7280; }'],
        ['.brand-table { width: 100%; border-collapse: collapse; margin-bottom: 2pt; }', '.brand-table { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }'],
        ['.logo { width: 44pt; height: 44pt; object-fit: contain; }', '.logo { width: 50pt; height: 50pt; object-fit: contain; }'],
        [
            "        width: 44pt; height: 44pt; border-radius: 50%; background: {{accent_color}}; color: #fff;\n        text-align: center; line-height: 44pt; font-size: 13pt; font-weight: bold;",
            "        width: 50pt; height: 50pt; border-radius: 50%; background: {{accent_color}}; color: #fff;\n        text-align: center; line-height: 50pt; font-size: 14pt; font-weight: bold;",
        ],
        ['.school-name { font-size: 15pt; font-weight: bold; margin: 0; letter-spacing: 0.3pt; text-transform: uppercase; text-align: center; }', '.school-name { font-size: 18pt; font-weight: bold; margin: 0; letter-spacing: 0.3pt; text-transform: uppercase; text-align: center; }'],
        ['.school-line { font-size: 8pt; font-weight: bold; margin: 0; text-transform: uppercase; text-align: center; }', '.school-line { font-size: 9.5pt; font-weight: bold; margin: 1pt 0 0; text-transform: uppercase; text-align: center; }'],
        ['.title-row { width: 100%; border-collapse: collapse; margin: 3pt 0; }', '.title-row { width: 100%; border-collapse: collapse; margin: 4pt 0; }'],
        [
            "        display: inline-block; background: {{accent_light}}; border: 0.8pt solid #111;\n        padding: 1.5pt 12pt; font-weight: bold; font-size: 10pt;",
            "        display: inline-block; background: {{accent_light}}; border: 0.8pt solid #111;\n        padding: 2pt 14pt; font-weight: bold; font-size: 10.5pt;",
        ],
        ['.session { text-align: right; font-size: 9pt; font-weight: bold; }', '.session { text-align: right; font-size: 9.5pt; font-weight: bold; }'],
        ['.student { width: 100%; border-collapse: collapse; border: 0.9pt solid #111; margin-bottom: 3pt; }', '.student { width: 100%; border-collapse: collapse; border: 0.9pt solid #111; margin-bottom: 4pt; }'],
        ['.student > tr > td { border: none; padding: 1.5pt 6pt; vertical-align: top; width: 50%; }', '.student > tr > td { border: none; padding: 2pt 8pt; vertical-align: top; width: 50%; }'],
        ['.info-table td { border: none; padding: 0.5pt 0; font-size: 9pt; vertical-align: top; }', '.info-table td { border: none; padding: 1pt 0; font-size: 9.5pt; vertical-align: top; }'],
        ['.info-table td.lbl { width: 76pt; white-space: nowrap; }', '.info-table td.lbl { width: 84pt; white-space: nowrap; }'],
        ['.info-table td.lbl { width: 76pt; white-space: nowrap; color: #6b7280; }', '.info-table td.lbl { width: 84pt; white-space: nowrap; color: #6b7280; }'],
        ['table.marks { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }', 'table.marks { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }'],
        ['table.marks th, table.marks td { border: 0.7pt solid #111; padding: 2.5pt 4pt; }', 'table.marks th, table.marks td { border: 0.7pt solid #111; padding: 3pt 5pt; }'],
        ['table.marks th, table.marks td { border: 0.6pt solid {{accent_border}}; padding: 2.5pt 4pt; }', 'table.marks th, table.marks td { border: 0.6pt solid {{accent_border}}; padding: 3pt 5pt; }'],
        ['table.marks th { background: {{accent_color}}; color: #fff; font-size: 8.5pt; text-align: center; }', 'table.marks th { background: {{accent_color}}; color: #fff; font-size: 9pt; text-align: center; }'],
        ['table.marks th.band { background: {{accent_dark}}; font-size: 9pt; }', 'table.marks th.band { background: {{accent_dark}}; font-size: 9.5pt; }'],
        ['table.marks td.subj { font-weight: bold; text-transform: uppercase; font-size: 9pt; }', 'table.marks td.subj { font-weight: bold; text-transform: uppercase; font-size: 9.5pt; }'],
        ['table.marks td.num { text-align: center; font-size: 9pt; }', 'table.marks td.num { text-align: center; font-size: 9.5pt; }'],
        ['table.marks-summary { width: 100%; border-collapse: collapse; margin: 0 0 3pt; }', 'table.marks-summary { width: 100%; border-collapse: collapse; margin: 0 0 4pt; }'],
        ['table.marks-summary th, table.marks-summary td { border: 0.7pt solid #111; padding: 1.5pt 4pt; font-size: 8pt; text-align: center; }', 'table.marks-summary th, table.marks-summary td { border: 0.7pt solid #111; padding: 2pt 5pt; font-size: 8.5pt; text-align: center; }'],
        ['table.marks-summary th, table.marks-summary td { border: 0.6pt solid {{accent_border}}; padding: 1.5pt 4pt; font-size: 8pt; text-align: center; }', 'table.marks-summary th, table.marks-summary td { border: 0.6pt solid {{accent_border}}; padding: 2pt 5pt; font-size: 8.5pt; text-align: center; }'],
        ['.mid { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }', '.mid { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }'],
        ['.mid-left { width: 58%; padding-right: 6pt; }', '.mid-left { width: 58%; padding-right: 8pt; }'],
        ['.box-pad { padding: 3pt 7pt; }', '.box-pad { padding: 4pt 9pt; }'],
        ['.h-blue { color: {{accent_color}}; font-weight: bold; font-size: 9pt; margin: 0 0 1.5pt; }', '.h-blue { color: {{accent_color}}; font-weight: bold; font-size: 9.5pt; margin: 0 0 2pt; }'],
        ['table.co-grid { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }', 'table.co-grid { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }'],
        ['table.co-grid th, table.co-grid td { border: 0.7pt solid #111; padding: 1.5pt 6pt; font-size: 8pt; }', 'table.co-grid th, table.co-grid td { border: 0.7pt solid #111; padding: 2pt 7pt; font-size: 8.5pt; }'],
        ['table.co-grid th, table.co-grid td { border: 0.6pt solid {{accent_border}}; padding: 1.5pt 6pt; font-size: 8pt; }', 'table.co-grid th, table.co-grid td { border: 0.6pt solid {{accent_border}}; padding: 2pt 7pt; font-size: 8.5pt; }'],
        ['.remarks-opts { font-weight: bold; font-size: 9pt; margin-top: 1pt; }', '.remarks-opts { font-weight: bold; font-size: 9.5pt; margin-top: 1.5pt; }'],
        ['.remarks-opts { font-weight: bold; font-size: 9pt; margin-top: 1pt; color: #374151; }', '.remarks-opts { font-weight: bold; font-size: 9.5pt; margin-top: 1.5pt; color: #374151; }'],
        ['.sum-box { border: 0.9pt solid #111; padding: 2pt 7pt; margin-bottom: 2pt; font-size: 8.5pt; font-weight: bold; }', '.sum-box { border: 0.9pt solid #111; padding: 2.5pt 9pt; margin-bottom: 3pt; font-size: 9pt; font-weight: bold; }'],
        ['.sum-box { border: 0.8pt solid {{accent_border}}; border-radius: 2pt; padding: 2pt 7pt; margin-bottom: 2pt; font-size: 8.5pt; font-weight: bold; background: {{accent_zebra}}; }', '.sum-box { border: 0.8pt solid {{accent_border}}; border-radius: 2pt; padding: 2.5pt 9pt; margin-bottom: 3pt; font-size: 9pt; font-weight: bold; background: {{accent_zebra}}; }'],
        ['.bottom { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }', '.bottom { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }'],
        ['.bottom-left { width: 42%; padding-right: 6pt; }', '.bottom-left { width: 42%; padding-right: 8pt; }'],
        [
            "        display: inline-block; background: {{accent_light}}; border: 0.7pt solid #111;\n        padding: 1pt 10pt; color: {{accent_color}}; font-weight: bold; font-size: 8.5pt; margin-bottom: 2pt;",
            "        display: inline-block; background: {{accent_light}}; border: 0.7pt solid #111;\n        padding: 1.5pt 12pt; color: {{accent_color}}; font-weight: bold; font-size: 9pt; margin-bottom: 2pt;",
        ],
        [
            "        display: inline-block; background: {{accent_light}}; border: 0.6pt solid {{accent_border}}; border-radius: 2pt;\n        padding: 1pt 10pt; color: {{accent_color}}; font-weight: bold; font-size: 8.5pt; margin-bottom: 2pt;",
            "        display: inline-block; background: {{accent_light}}; border: 0.6pt solid {{accent_border}}; border-radius: 2pt;\n        padding: 1.5pt 12pt; color: {{accent_color}}; font-weight: bold; font-size: 9pt; margin-bottom: 2pt;",
        ],
        ['.grade-grid { width: 100%; border-collapse: collapse; font-size: 8pt; }', '.grade-grid { width: 100%; border-collapse: collapse; font-size: 8.5pt; }'],
        ['.grade-grid td { border: none; padding: 1pt 2pt; }', '.grade-grid td { border: none; padding: 1.5pt 2pt; }'],
        ['.chart-title { text-align: center; font-weight: bold; font-size: 9pt; margin: 1pt 0 2pt; }', '.chart-title { text-align: center; font-weight: bold; font-size: 9.5pt; margin: 1pt 0 3pt; }'],
        ['.chart-title { text-align: center; font-weight: bold; font-size: 9pt; margin: 1pt 0 2pt; color: {{accent_color}}; }', '.chart-title { text-align: center; font-weight: bold; font-size: 9.5pt; margin: 1pt 0 3pt; color: {{accent_color}}; }'],
        ['.sig-row { width: 100%; border-collapse: collapse; border-top: 0.9pt solid #111; margin-top: 3pt; padding-top: 3pt; }', '.sig-row { width: 100%; border-collapse: collapse; border-top: 0.9pt solid #111; margin-top: 4pt; padding-top: 3pt; }'],
        ['.sig-row { width: 100%; border-collapse: collapse; border-top: 0.8pt solid {{accent_border}}; margin-top: 3pt; }', '.sig-row { width: 100%; border-collapse: collapse; border-top: 0.8pt solid {{accent_border}}; margin-top: 4pt; }'],
        ['.sig-row td { border: none; width: 50%; vertical-align: bottom; padding-top: 16pt; font-weight: bold; font-size: 9.5pt; }', '.sig-row td { border: none; width: 50%; vertical-align: bottom; padding-top: 22pt; font-weight: bold; font-size: 10pt; }'],
        ['.sig-row td { border: none; width: 50%; vertical-align: bottom; padding-top: 16pt; font-weight: bold; font-size: 9.5pt; color: #374151; }', '.sig-row td { border: none; width: 50%; vertical-align: bottom; padding-top: 22pt; font-weight: bold; font-size: 10pt; color: #374151; }'],
        ['.sig-img { max-height: 30pt; max-width: 90pt; margin-bottom: 2pt; }', '.sig-img { max-height: 32pt; max-width: 100pt; margin-bottom: 2pt; }'],
        ['.sig-space { height: 22pt; }', '.sig-space { height: 26pt; }'],
        ['.stamp { max-height: 34pt; max-width: 34pt; margin-bottom: 2pt; }', '.stamp { max-height: 38pt; max-width: 38pt; margin-bottom: 2pt; }'],

        // modern-only
        ['.sheet { border: 1.5pt solid {{accent_color}}; padding: 5pt 8pt 6pt; }', '.sheet { border: 1.5pt solid {{accent_color}}; padding: 7pt 10pt 8pt; }'],
        ['.accent { height: 3pt; background: {{accent_color}}; margin: 0 0 4pt; }', '.accent { height: 3pt; background: {{accent_color}}; margin: 0 0 5pt; }'],
        ['.logo { width: 40pt; height: 40pt; object-fit: contain; border-radius: 6pt; }', '.logo { width: 46pt; height: 46pt; object-fit: contain; border-radius: 6pt; }'],
        [
            "        width: 40pt; height: 40pt; border-radius: 6pt; background: {{accent_color}}; color: #fff;\n        text-align: center; line-height: 40pt; font-size: 13pt; font-weight: bold;",
            "        width: 46pt; height: 46pt; border-radius: 6pt; background: {{accent_color}}; color: #fff;\n        text-align: center; line-height: 46pt; font-size: 14pt; font-weight: bold;",
        ],
        ['.school-name { font-size: 14pt; font-weight: bold; margin: 0; text-align: center; color: #111827; }', '.school-name { font-size: 17pt; font-weight: bold; margin: 0; text-align: center; color: #111827; }'],
        ['.school-line { font-size: 8pt; margin: 0; text-align: center; color: #6b7280; }', '.school-line { font-size: 9.5pt; margin: 1pt 0 0; text-align: center; color: #6b7280; }'],
    ];

    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'report_card')
            ->get(['id', 'raw_html']);

        foreach ($rows as $row) {
            $html = (string) $row->raw_html;
            if (str_contains($html, '@page { margin: 6mm 5mm; size: A4 portrait; }')) {
                continue; // already patched
            }

            $updated = $html;
            foreach (self::PAIRS as [$search, $replace]) {
                $updated = str_replace($search, $replace, $updated);
            }

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
        // Layout-only tweak — not reverted.
    }
};
