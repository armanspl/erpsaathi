<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Report-card templates are frozen into templates.raw_html the first time a school generates one
 * (DesignCatalog::seedHtml()) — editing the blade source alone never reaches schools that already
 * have a row. The Grade column was center-aligned, so a 1-char grade ("C") and a 2-char grade
 * ("A+") started at different horizontal positions instead of stacking in one column.
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
            $updated = $this->leftAlignGrade($html);

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

    private function leftAlignGrade(string $html): string
    {
        $html = str_replace(
            'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; }',
            'table.marks td.grade { text-align: left; padding-left: 8pt; font-weight: bold; background: {{accent_light}}; }',
            $html
        );

        $html = str_replace(
            'table.marks td.grade { text-align: center; font-weight: bold; background: {{accent_light}}; color: {{accent_dark}}; }',
            'table.marks td.grade { text-align: left; padding-left: 8pt; font-weight: bold; background: {{accent_light}}; color: {{accent_dark}}; }',
            $html
        );

        return $html;
    }
};
