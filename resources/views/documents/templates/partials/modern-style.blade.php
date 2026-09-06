<style>
    * { box-sizing: border-box; }
    .doc {
        position: relative;
        width: 100%;
        margin: 0;
        padding: 0 0 6mm;
        font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
        color: #111827;
        background: #ffffff;
    }
    /* Flow accent — negative horizontal margins overflow Dompdf and clip the left edge. */
    .doc-accent { height: 4mm; margin: 0 0 4mm; background: #4f46e5; }
    .doc-header { display: table; width: 100%; margin-top: 2mm; margin-bottom: 3mm; }
    .doc-logo { width: 14mm; height: 14mm; object-fit: cover; border-radius: 2mm; }
    .doc-logo-cell { display: table-cell; width: 18mm; vertical-align: middle; }
    .doc-school-name { font-size: 14pt; font-weight: bold; color: #111827; }
    .doc-school-sub { font-size: 8pt; color: #6b7280; margin-top: 0.5mm; }
    .doc-header-text { display: table-cell; vertical-align: middle; }
    .doc-title { display: inline-block; margin-top: 1.5mm; padding: 1mm 3.5mm; background: #eef2ff; color: #4f46e5; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; border-radius: 1mm; }
    .doc-divider { border: none; border-top: 0.25mm solid #e5e7eb; margin: 3mm 0; }
    .doc-grid { display: table; width: 100%; margin-bottom: 2mm; }
    .doc-row { display: table-row; }
    .doc-cell { display: table-cell; vertical-align: top; padding: 1.2mm 2mm; font-size: 9pt; }
    .doc-label { color: #9ca3af; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.3pt; }
    .doc-value { font-weight: bold; color: #111827; }
    .doc-block { border: 0.25mm solid #e5e7eb; border-left: 1mm solid #4f46e5; background: #f9fafb; padding: 2mm 2.5mm; margin: 2mm 0; font-family: 'DejaVu Sans', Helvetica, sans-serif; font-size: 8pt; line-height: 1.3; }
    .dues-flow, .pay-flow { width: 100%; line-height: 1.35; }
    .dues-group, .pay-group {
        display: inline-block;
        vertical-align: top;
        white-space: nowrap;
        margin: 0.35mm 4mm 0.35mm 0;
        padding: 0.2mm 0;
    }
    .dues-month, .pay-date { font-weight: bold; color: #111827; }
    .dues-month { margin-right: 1.6mm; }
    .dues-name, .pay-mode { color: #4b5563; }
    .dues-amt, .pay-amt { font-weight: bold; color: #111827; }
    .dues-sep, .pay-pipe { color: #9ca3af; padding: 0 1.2mm; }
    .dues-empty, .pay-empty { color: #9ca3af; font-style: italic; }
    .doc-total { font-size: 10pt; font-weight: bold; color: #111827; }
    .doc-total-hl { background: #4f46e5; color: #ffffff; padding: 1.5mm 3mm; display: inline-block; font-weight: bold; border-radius: 1mm; }
    .doc-footer { margin-top: 4mm; font-size: 7.5pt; color: #9ca3af; }
    .doc-signature { text-align: center; font-size: 8pt; color: #6b7280; border-top: 0.3mm solid #d1d5db; padding-top: 1.5mm; margin-top: 8mm; width: 45mm; display: inline-block; }
    .doc-photo { width: 22mm; height: 26mm; object-fit: cover; border-radius: 1.5mm; border: 0.25mm solid #e5e7eb; }
    .doc-center { text-align: center; }
    .doc-right { text-align: right; }
    .doc-body-text { font-size: 9.5pt; line-height: 1.7; padding: 0 2mm; }
</style>
