<style>
    * { box-sizing: border-box; }
    .doc { position: relative; width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Sans', Helvetica, sans-serif; color: #1e293b; background: #ffffff; }
    .doc-border { border: 0.5mm solid #1e293b; padding: 5mm; }

    .doc-divider { border: none; border-top: 0.4mm solid #1e293b; margin: 0.8mm 0; }

    .doc-grid { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .doc-grid td { border: 0.3mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; }
    .doc-label { color: #1e293b; }
    .doc-value { font-weight: bold; color: #1e293b; }

    .doc-block { border: 0.3mm solid #1e293b; border-top: none; padding: 1.2mm 2mm; font-family: 'DejaVu Sans', Helvetica, sans-serif; font-size: 8pt; line-height: 1.3; }
    .doc-block-title { border: 0.3mm solid #1e293b; border-top: none; background: #cbd5e1; padding: 1mm 2.5mm; font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt; }

    /* Inline-block month groups — DomPDF wraps these to fill horizontal width. */
    .dues-flow, .pay-flow { width: 100%; line-height: 1.35; }
    .dues-group {
        display: inline-block;
        vertical-align: top;
        white-space: nowrap;
        margin: 0.35mm 4mm 0.35mm 0;
        padding: 0.2mm 0;
    }
    .dues-month { font-weight: bold; color: #0f172a; margin-right: 1.6mm; }
    .dues-fees { color: #334155; }
    .dues-name { color: #475569; }
    .dues-amt { font-weight: bold; color: #0f172a; }
    .dues-sep { color: #94a3b8; padding: 0 1.2mm; }
    .dues-empty, .pay-empty { color: #94a3b8; font-style: italic; padding: 1mm 0; }

    .pay-group {
        display: inline-block;
        vertical-align: top;
        white-space: nowrap;
        margin: 0.35mm 4mm 0.35mm 0;
        padding: 0.2mm 0;
    }
    .pay-date { font-weight: bold; color: #0f172a; }
    .pay-mode { color: #334155; }
    .pay-pipe { color: #94a3b8; padding: 0 1.4mm; }
    .pay-amt { font-weight: bold; color: #0f172a; }

    .doc-total-hl { width: 100%; border-collapse: collapse; background: #b91c1c; margin-top: 1.5mm; }
    .doc-total-hl td { color: #ffffff; font-weight: bold; padding: 1.5mm 2.5mm; font-size: 9.5pt; }
    .doc-total-hl td.doc-right { text-align: right; }

    .doc-right { text-align: right; }
    .doc-signature { text-align: center; font-size: 8.5pt; font-weight: bold; color: #1e293b; }

    .doc-bottom-band { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    .doc-bottom-band td { border: 0.35mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; vertical-align: middle; }
    .doc-bottom-band td.gray { background: #cbd5e1; font-weight: bold; }
    .doc-bottom-band td.value { font-weight: bold; }
</style>
<div class="doc">
  <div class="doc-border">

    @include('documents.templates.partials.classic-fee-header', [
        'title_bg' => '#b91c1c',
        'show_phone' => false,
    ])

    <hr class="doc-divider">

    <table class="doc-grid">
        <tr>
            <td style="width: 63%;"><span class="doc-label">Student:</span> <span class="doc-value" style="color:#7f1d1d;">{{ $student_name }}</span></td>
            <td style="width: 37%;"><span class="doc-label">Receipt No.</span> <span class="doc-value">{{ $receipt_no }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Father:</span> <span class="doc-value">{{ $father_name }}</span></td>
            <td><span class="doc-label">Date:</span> <span class="doc-value">{{ $receipt_date }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Mother:</span> <span class="doc-value">{{ $mother_name }}</span></td>
            <td><span class="doc-label">Adm. No.</span> <span class="doc-value">{{ $admission_id }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Roll No.</span> <span class="doc-value">{{ $roll_number }}</span></td>
            <td><span class="doc-label">Class:</span> <span class="doc-value">{{ $class }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Due Date:</span> <span class="doc-value">{{ $due_date }}</span></td>
            <td><span class="doc-label">Section:</span> <span class="doc-value">{{ $section }}</span></td>
        </tr>
    </table>

    <div class="doc-block-title">Outstanding dues</div>
    <div class="doc-block">{!! $due_table !!}</div>

    <table class="doc-total-hl">
        <tr><td>TOTAL DUE</td><td class="doc-right">{{ $total_due }}</td></tr>
    </table>

    <div class="doc-block-title" style="margin-top: 2mm; border-top: 0.3mm solid #1e293b;">Payment history</div>
    <div class="doc-block">{!! $payment_history_table !!}</div>

    <table class="doc-bottom-band">
        <tr>
            <td class="gray" style="width: 25%;">Remarks</td>
            <td class="value" style="width: 50%;">{{ $remarks }}</td>
            <td class="doc-signature" style="width: 25%;">Accountant Signature</td>
        </tr>
    </table>

  </div>
</div>
