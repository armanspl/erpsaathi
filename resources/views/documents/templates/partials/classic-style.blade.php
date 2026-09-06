<style>
    * { box-sizing: border-box; }
    .doc {
        position: relative;
        width: 100%;
        margin: 0;
        padding: 0;
        font-family: 'DejaVu Serif', Georgia, serif;
        color: #1f2937;
        background: #ffffff;
    }
    /* Flow border — avoid position:absolute/inset (Dompdf shifts content left/right unevenly).
       Outer inset comes from @page margins in DocumentRenderService (not width:100% + padding). */
    .doc-border {
        border: 0.6mm solid #b8860b;
        padding: 5mm 6mm;
        box-sizing: border-box;
        min-height: 100%;
    }
    .doc-header { text-align: center; margin-bottom: 3mm; }
    .doc-logo { width: 16mm; height: 16mm; object-fit: cover; border-radius: 50%; }
    .doc-logo-row { display: table; width: 100%; margin-bottom: 1mm; }
    .doc-logo-cell { display: table-cell; width: 18mm; vertical-align: middle; }
    .doc-logo-cell-right { display: table-cell; width: 18mm; vertical-align: middle; text-align: right; }
    .doc-school-name { display: table-cell; font-size: 16pt; font-weight: bold; color: #1e3a5f; letter-spacing: 0.3pt; vertical-align: middle; }
    .doc-school-sub { font-size: 8pt; color: #6b7280; margin-top: 1mm; }
    .doc-title { display: inline-block; margin-top: 2mm; padding: 1.2mm 5mm; border: 0.4mm solid #b8860b; color: #7c5c00; font-size: 9.5pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; }
    .doc-divider { border: none; border-top: 0.3mm solid #b8860b; margin: 3mm 0; }
    .doc-grid { display: table; width: 100%; margin-bottom: 2mm; }
    .doc-row { display: table-row; }
    .doc-cell { display: table-cell; vertical-align: top; padding: 1.2mm 2mm; font-size: 9pt; }
    .doc-label { color: #6b7280; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.3pt; }
    .doc-value { font-weight: bold; color: #1f2937; }
    .doc-block { border: 0.3mm solid #d6cbb0; background: #fdfaf3; padding: 3mm; margin: 2mm 0; font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; white-space: pre-wrap; line-height: 1.5; }
    .doc-total { font-size: 10pt; font-weight: bold; color: #1e3a5f; }
    .doc-total-hl { background: #1e3a5f; color: #ffffff; padding: 1.5mm 3mm; display: inline-block; font-weight: bold; }
    .doc-footer { margin-top: 4mm; font-size: 7.5pt; color: #6b7280; }
    .doc-signature { text-align: center; font-size: 8pt; color: #6b7280; border-top: 0.3mm solid #9ca3af; padding-top: 1.5mm; margin-top: 8mm; width: 45mm; display: inline-block; }
    .doc-photo { width: 22mm; height: 26mm; object-fit: cover; border: 0.3mm solid #b8860b; }
    .doc-center { text-align: center; }
    .doc-right { text-align: right; }
    .doc-body-text { font-size: 9.5pt; line-height: 1.7; text-align: center; padding: 0 4mm; }
</style>
