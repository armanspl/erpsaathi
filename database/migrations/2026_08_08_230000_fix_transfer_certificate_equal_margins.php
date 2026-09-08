<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Transfer Certificate PDFs were clipping on the right because the template used
 * white-space:nowrap on long address lines and a fixed 120mm label column that
 * exceeded Dompdf's printable width (A4 minus equal @page margins).
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
            $updated = $this->replaceStyleBlock($html, $this->fixedCss());
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
        // Irreversible layout fix — previous CSS caused clipped PDF edges.
    }

    private function replaceStyleBlock(string $html, string $css): string
    {
        if (preg_match('/<style>.*?<\/style>/s', $html)) {
            return preg_replace('/<style>.*?<\/style>/s', '<style>'."\n".$css."\n".'</style>', $html, 1);
        }

        return '<style>'."\n".$css."\n".'</style>'."\n".$html;
    }

    private function fixedCss(): string
    {
        return <<<'CSS'
/* Fill the printable area only — equal left/right @page margins come from DocumentRenderService. */
.tc-page{
    width:100%;
    max-width:100%;
    box-sizing:border-box;
    padding:0;
    margin:0 auto;
    font-family:'DejaVu Sans', Helvetica, Arial, sans-serif;
    color:#111827;
    overflow:hidden;
}

.tc-topline-row{ display:table; width:100%; table-layout:fixed; font-size:8.5pt; font-weight:bold; margin-bottom:2mm; }
.tc-topline-cell{ display:table-cell; width:50%; vertical-align:middle; overflow-wrap:anywhere; word-wrap:break-word; }

.tc-logo-row{ display:table; width:100%; table-layout:fixed; margin-bottom:1mm; }
.tc-logo-cell{ display:table-cell; width:18mm; vertical-align:middle; }
.tc-logo-cell-right{ display:table-cell; width:18mm; vertical-align:middle; text-align:right; }
.tc-logo{ width:16mm; height:16mm; object-fit:contain; }
/* Must stay block — official TC HTML uses <div class="tc-head"> outside any table.
   display:table-cell here makes Dompdf throw "Parent table not found for table cell". */
.tc-head{ display:block; text-align:center; vertical-align:middle; padding:0 2mm; margin-bottom:0.5mm; overflow:hidden; }
.tc-school{ font-size:18pt; font-weight:bold; color:#1e3a8a; letter-spacing:0.3px; margin:0; overflow-wrap:anywhere; word-wrap:break-word; }
.tc-address{ font-size:8pt; font-weight:bold; margin-top:1mm; overflow-wrap:anywhere; word-wrap:break-word; }
.tc-phone{ font-size:8pt; font-weight:bold; margin-top:0.6mm; overflow-wrap:anywhere; word-wrap:break-word; }

.tc-titlebar{ border:0.35mm solid #1f2937; background:#d1d5db; text-align:center; font-weight:bold; font-size:11.5pt; letter-spacing:2.5px; text-decoration:underline; padding:1.4mm 0; margin:2.5mm 0; box-sizing:border-box; }

.tc-refrow-row{ display:table; width:100%; table-layout:fixed; font-size:8.5pt; font-weight:bold; margin-bottom:3mm; }
.tc-refrow-cell{ display:table-cell; vertical-align:middle; overflow-wrap:anywhere; word-wrap:break-word; padding-right:1mm; }

.tc-list{ width:100%; table-layout:fixed; font-size:9pt; line-height:1.35; border-collapse:collapse; }
.tc-list td{ padding:0.55mm 0; vertical-align:top; border:none; overflow-wrap:anywhere; word-wrap:break-word; }
.tc-list .num{ width:7mm; }
.tc-list .label{ width:58%; }
.tc-list .colon{ width:6mm; text-align:center; }
.tc-list .value{ width:34%; font-weight:bold; }

.tc-footer-row{ display:table; width:100%; table-layout:fixed; margin-top:6mm; font-size:8.5pt; font-weight:bold; }
.tc-footer-cell{ display:table-cell; width:50%; vertical-align:bottom; overflow-wrap:anywhere; word-wrap:break-word; }
CSS;
    }
};
