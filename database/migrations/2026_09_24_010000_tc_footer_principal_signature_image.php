<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Principal's signature image was only shown over "Administrator" — show it over "Principal" too. */
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
            $updated = $this->addSignature($html);

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

    private function addSignature(string $html): string
    {
        return str_replace(
            '<td class="tc-footer-left">Principal</td>',
            '<td class="tc-footer-left"><div class="tc-sig-wrap"><img src="{{principal_signature_image}}" class="tc-sig-img" alt="" /><br />Principal</div></td>',
            $html
        );
    }
};
