<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Small consistent gap between each of the 23 numbered field rows, and an explicit
 * border:none on every footer cell (Checked By / Date / Sign of Principal) so no
 * default border can show under it.
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
        $html = preg_replace(
            '/(\.tc-list\s+td\s*\{\s*padding:\s*)0\.55mm 0/i',
            '${1}1.1mm 0',
            $html,
            1
        ) ?? $html;

        if (! preg_match('/\.tc-footer-row\s+td\s*\{\s*border\s*:/i', $html)) {
            $html = preg_replace(
                '/(\.tc-footer-row\s+td\s*\{)/i',
                '$1 border: none !important; outline: none;',
                $html,
                1
            ) ?? $html;
        }

        return $html;
    }
};
