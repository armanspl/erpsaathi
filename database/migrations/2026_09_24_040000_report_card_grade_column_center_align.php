<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reverts the Grade column back to center-aligned (undoes the left-align/padding change from
 * 2026_09_24_030000). Since every row in a table column shares the same column width, centering
 * already puts a 1-char grade ("C") and a 2-char grade ("A+") on the same vertical center line —
 * left-align isn't needed for that. Patches the frozen templates.raw_html rows the same way the
 * prior migration did.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'report_card')
            ->get(['id', 'raw_html']);

        foreach ($rows as $row) {
            $html = (string) $row->raw_html;
            $updated = $this->centerAlignGrade($html);

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
        // Alignment fix — not reverted.
    }

    private function centerAlignGrade(string $html): string
    {
        $html = str_replace(
            'table.marks td.grade { text-align: left; padding-left: 8pt; font-weight: bold; background: {{accent_light}}; }',
            'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; }',
            $html
        );

        $html = str_replace(
            'table.marks td.grade { text-align: left; padding-left: 8pt; font-weight: bold; background: {{accent_light}}; color: {{accent_dark}}; }',
            'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; color: {{accent_dark}}; }',
            $html
        );

        return $html;
    }
};
