<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TC footer: "Checked By" + a Date line becomes just "Principal" (no date), and
 * "Sign of Principal & Seal" becomes "Administrator" — the signature image itself
 * is unchanged, only the caption text under each side.
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
            $updated = $this->relabel($html);

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
        // Label change — not reverted.
    }

    private function relabel(string $html): string
    {
        $html = str_replace(
            '<td class="tc-footer-left">Checked By<br /><div class="tc-checked-date">Date: {{issue_date_numeric}}</div></td>',
            '<td class="tc-footer-left">Principal</td>',
            $html
        );

        $html = str_replace(
            'Sign of Principal &amp; Seal</div></td>',
            'Administrator</div></td>',
            $html
        );

        return $html;
    }
};
