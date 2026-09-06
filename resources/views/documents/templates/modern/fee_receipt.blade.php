@include('documents.templates.partials.modern-style')
<div class="doc">
    <div class="doc-accent"></div>
    @include('documents.templates.partials.modern-fee-header', ['title_bg' => '#4f46e5', 'title_fg' => '#ffffff'])
    <hr class="doc-divider" style="margin: 0.8mm 0;">

    <table style="width: 100%;">
        <tr>
            <td style="width: 63%; vertical-align: top; padding-right: 3mm;">
                <table class="doc-grid">
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Receipt No.</span> <span class="doc-value">{{ $receipt_no }}</span></td>
                        <td class="doc-cell"><span class="doc-label">Date</span> <span class="doc-value">{{ $receipt_date }}</span></td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell" colspan="2"><span class="doc-label">Student</span> <span class="doc-value">{{ $student_name }}</span> ({{ $admission_id }})</td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Father</span> <span class="doc-value">{{ $father_name }}</span></td>
                        <td class="doc-cell"><span class="doc-label">City</span> <span class="doc-value">{{ $city }}</span></td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Class / Section</span> <span class="doc-value">{{ $class }} {{ $section }}</span></td>
                        <td class="doc-cell"><span class="doc-label">Fee for month(s)</span> <span class="doc-value">{{ $fee_for_months }}</span></td>
                    </tr>
                </table>

                <div class="doc-block" style="margin-top: 1.5mm;">{{ $items_table }}</div>
                <p style="font-size: 7.5pt; color: #6b7280; margin: 1mm 0;">Amount in words: {{ $amount_in_words }}</p>
                <p style="font-size: 7.5pt; color: #6b7280; margin: 0;">Remarks: {{ $remarks }}</p>
                <table style="width: 100%; margin-top: 2mm;">
                    <tr>
                        <td style="font-size: 8pt;">Payment mode: <strong>{{ $payment_mode }}</strong></td>
                        <td style="text-align: right; font-size: 8pt; color: #6b7280;">Received by: {{ $received_by }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 37%; vertical-align: top; border-left: 0.25mm solid #e5e7eb; padding-left: 3mm; font-size: 8pt;">
                <table style="width: 100%;">
                    <tr><td>Total fees</td><td class="doc-right">{{ $total_fees }}</td></tr>
                    <tr><td>+ Back dues</td><td class="doc-right">{{ $back_dues }}</td></tr>
                    <tr><td>+ Late fee</td><td class="doc-right">{{ $late_fee }}</td></tr>
                    <tr><td>- Adjustment</td><td class="doc-right">{{ $adjustment }}</td></tr>
                    <tr><td colspan="2"><hr class="doc-divider" style="margin: 1mm 0;"></td></tr>
                    <tr class="doc-total"><td>Grand total</td><td class="doc-right">{{ $grand_total }}</td></tr>
                </table>
                <div class="doc-total-hl" style="width: 100%; box-sizing: border-box; margin-top: 2mm; text-align: center;">Paid: {{ $paid_amount }}</div>
                <p style="text-align: center; font-size: 8pt; margin-top: 2mm;">Balance dues: <strong>{{ $balance_dues }}</strong></p>
            </td>
        </tr>
    </table>
</div>
