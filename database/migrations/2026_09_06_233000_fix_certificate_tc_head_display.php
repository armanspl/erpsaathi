<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair Transfer Certificate templates broken by the equal-margins CSS update:
 * `.tc-head { display: table-cell }` was applied to a lone <div class="tc-head">,
 * which makes Dompdf throw "Parent table not found for table cell" on download.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'certificate')
            ->where('raw_html', 'like', '%tc-head%')
            ->get(['id', 'raw_html']);

        foreach ($rows as $row) {
            $html = (string) $row->raw_html;
            $updated = preg_replace(
                '/\.tc-head\s*\{[^}]*display\s*:\s*table-cell[^}]*\}/i',
                '.tc-head{ display:block; text-align:center; vertical-align:middle; padding:0 2mm; margin-bottom:0.5mm; overflow:hidden; }',
                $html
            );

            if (is_string($updated) && $updated !== $html) {
                DB::table('templates')->where('id', $row->id)->update([
                    'raw_html' => $updated,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
