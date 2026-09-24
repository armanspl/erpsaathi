<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grade values are now wrapped in <span class="grade-val"> (see ReportCardPdfService::gradeCellHtml()).
 * The frozen templates.raw_html rows need the matching fixed-width-box rule so "A" and "A+" start at
 * the same horizontal position while the column itself stays center-aligned.
 */
return new class extends Migration
{
    private const RULE = "\n    table.marks td.grade .grade-val { display: inline-block; width: 16pt; text-align: left; }";

    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'report_card')
            ->get(['id', 'raw_html']);

        foreach ($rows as $row) {
            $html = (string) $row->raw_html;
            if (str_contains($html, '.grade-val')) {
                continue;
            }

            $updated = $html;
            foreach ([
                'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; }',
                'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; color: {{accent_dark}}; }',
            ] as $anchor) {
                $updated = str_replace($anchor, $anchor.self::RULE, $updated);
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
        // Layout addition — not reverted.
    }
};
