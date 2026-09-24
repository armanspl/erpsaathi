<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** "Administrator" is a role label, not a signatory — drop the (Principal's) signature image over it. */
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
            $updated = $this->removeSignature($html);

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
        // Layout removal — not reverted.
    }

    private function removeSignature(string $html): string
    {
        return str_replace(
            '<td class="tc-footer-right"><div class="tc-sig-wrap"><img src="{{principal_signature_image}}" class="tc-sig-img" alt="" /><br />Administrator</div></td>',
            '<td class="tc-footer-right">Administrator</td>',
            $html
        );
    }
};
