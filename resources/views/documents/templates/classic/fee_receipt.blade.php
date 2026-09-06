<style>
    * { box-sizing: border-box; }
    .doc { position: relative; width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Sans', Helvetica, sans-serif; color: #1e293b; background: #ffffff; }
    .doc-border { border: 0.5mm solid #1e293b; padding: 5mm; }

    .doc-divider { border: none; border-top: 0.4mm solid #1e293b; margin: 0.8mm 0; }

    /* Single collapsed table for the whole info grid — avoids seam/misalignment
       from separately-bordered divs whose widths don't sum exactly */
    .doc-grid { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .doc-grid td { border: 0.3mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; }
    .doc-label { color: #1e293b; }
    .doc-value { font-weight: bold; color: #1e293b; }

    /* items_table renders as PLAIN TEXT from the backend today, not a <table> —
       styled as a bordered text box only; will not show internal grid lines
       (S.N/Particulars/Amount columns) unless the backend emits real <table> markup */
    .doc-block { border: 0.3mm solid #1e293b; border-top: none; padding: 2.5mm; font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; white-space: pre-wrap; line-height: 1.5; }

    .doc-side-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .doc-side-table td { padding: 1.5mm 2.5mm; }
    .doc-side-table .doc-right { text-align: right; }
    .doc-total td { font-weight: bold; border-top: 0.4mm solid #1e293b; }

    /* table-based bar instead of flex, so label/value get real space-between */
    .doc-total-hl { width: 100%; border-collapse: collapse; background: #16a34a; }
    .doc-total-hl td { color: #ffffff; font-weight: bold; padding: 2mm 2.5mm; font-size: 9pt; }
    .doc-total-hl td.doc-right { text-align: right; }

    .doc-right { text-align: right; }
    .doc-signature { text-align: right; font-size: 9pt; font-weight: bold; color: #1e293b; }

    /* bottom band as one collapsed table, no flex — four real cells so every
       label/value pair gets its own bordered box, matching the target design */
    .doc-bottom-band { width: 100%; border-collapse: collapse; }
    .doc-bottom-band td { border: 0.35mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; vertical-align: middle; }
    .doc-bottom-band td.gray { background: #cbd5e1; font-weight: bold; }
    .doc-bottom-band td.italic { font-style: italic; text-align: center; }
    .doc-bottom-band td.value { font-weight: bold; }
</style>
<div class="doc">
  <div class="doc-border">

    @include('documents.templates.partials.classic-fee-header', [
        'title_bg' => '#15803d',
        'show_phone' => true,
    ])

    <hr class="doc-divider">

    <!-- info grid: one collapsed table, left col ~63%, right col ~37% -->
    <table class="doc-grid">
        <tr>
            <td style="width: 63%;"><span class="doc-label">Student:</span> <span class="doc-value" style="color:#7f1d1d;">{{ $student_name }}</span></td>
            <td style="width: 37%;"><span class="doc-label">DATE:</span> <span class="doc-value">{{ $receipt_date }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Father:</span> <span class="doc-value">{{ $father_name }}</span></td>
            <td><span class="doc-label">Adm. No.</span> <span class="doc-value">{{ $admission_id }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">City / Village:</span> <span class="doc-value">{{ $city }}</span></td>
            <td><span class="doc-label">Class:</span> <span class="doc-value">{{ $class }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Fee For Month(s):</span> <span class="doc-value">{{ $fee_for_months }}</span></td>
            <td><span class="doc-label">Section:</span> <span class="doc-value">{{ $section }}</span></td>
        </tr>
    </table>

    <!-- items + totals: one outer table so the two columns' borders line up cleanly -->
    <table style="width: 100%; border-collapse: collapse;">
      <tr>
        <td style="width: 63%; vertical-align: top; padding: 0;">
            <div class="doc-block">{{ $items_table }}</div>

            <div style="border: 0.35mm solid #1e293b; border-top: none; padding: 1.5mm 2.5mm; font-size: 8.5pt;">
                <strong>Amount In Words</strong> &nbsp; {{ $amount_in_words }}
            </div>
            <div style="border: 0.35mm solid #1e293b; border-top: none; padding: 3mm 2.5mm; font-size: 8.5pt; min-height: 12mm;">
                Remarks: {{ $remarks }}
            </div>
            <table style="width: 100%; border: 0.35mm solid #1e293b; border-top: none; border-collapse: collapse;">
                <tr>
                    <td style="padding: 1.5mm 2.5mm; font-size: 8.5pt; font-weight:bold;">User : {{ $received_by }}</td>
                    <td class="doc-signature" style="padding: 1.5mm 2.5mm;">Accountant Signature</td>
                </tr>
            </table>
        </td>
        <td style="width: 37%; vertical-align: top; padding: 0;">
            <table class="doc-side-table" style="border: 0.35mm solid #1e293b; border-left: none;">
                <tr><td>Total Fees</td><td class="doc-right">{{ $total_fees }}</td></tr>
                <tr><td>+ Back Dues</td><td class="doc-right">{{ $back_dues }}</td></tr>
                <tr><td>+ Late Fee</td><td class="doc-right">{{ $late_fee }}</td></tr>
                <tr><td>- Adjustment</td><td class="doc-right">{{ $adjustment }}</td></tr>
                <tr class="doc-total"><td>GRAND TOTAL</td><td class="doc-right">{{ $grand_total }}</td></tr>
            </table>
            <table class="doc-total-hl">
                <tr><td>Paid Amount</td><td class="doc-right">{{ $paid_amount }}</td></tr>
            </table>
        </td>
      </tr>
    </table>

    <!-- bottom band: one collapsed table, mini-tables inside cells instead of flex -->
    <table class="doc-bottom-band">
        <tr>
            <td class="gray" style="width: 22%;">Mode Of Payment</td>
            <td class="italic" style="width: 33%;">{{ $payment_mode }}</td>
            <td class="gray" style="width: 25%;">Total Balance Dues</td>
            <td class="value doc-right" style="width: 20%;">{{ $balance_dues }}</td>
        </tr>
    </table>

  </div>
</div>
