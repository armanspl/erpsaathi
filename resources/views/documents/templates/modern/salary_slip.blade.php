@include('documents.templates.partials.modern-style')
<div class="doc">
    <div class="doc-accent"></div>
    <table class="doc-header">
        <tr>
            <td class="doc-logo-cell"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
            <td class="doc-header-text">
                <div class="doc-school-name">{{ $school_name }}</div>
                <div class="doc-school-sub">{{ $school_address }} · {{ $school_phone }}</div>
            </td>
            <td style="text-align: right; vertical-align: middle;"><span class="doc-title">{{ $slip_title }}</span></td>
        </tr>
    </table>
    <hr class="doc-divider">

    <table style="width: 100%;">
        <tr>
            <td style="width: 63%; vertical-align: top; padding-right: 3mm;">
                <table class="doc-grid">
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Slip No.</span> <span class="doc-value">{{ $slip_no }}</span></td>
                        <td class="doc-cell"><span class="doc-label">Period</span> <span class="doc-value">{{ $period }}</span></td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell" colspan="2"><span class="doc-label">Employee</span> <span class="doc-value">{{ $employee_name }}</span> ({{ $employee_code }})</td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Employee Type</span> <span class="doc-value">{{ $employee_type }}</span></td>
                        <td class="doc-cell"><span class="doc-label">Status</span> <span class="doc-value">{{ $status }}</span></td>
                    </tr>
                    <tr class="doc-row">
                        <td class="doc-cell"><span class="doc-label">Days in Month</span> <span class="doc-value">{{ $days_in_month }}</span></td>
                        <td class="doc-cell"><span class="doc-label">Payment Mode</span> <span class="doc-value">{{ $payment_mode }}</span></td>
                    </tr>
                </table>

                <div class="doc-block" style="margin-top: 1.5mm;">{{ $earnings_table }}</div>
                <div class="doc-block">{{ $deductions_table }}</div>
                <p style="font-size: 7.5pt; color: #6b7280; margin: 1mm 0;">Remarks: {{ $remarks }}</p>
                <table style="width: 100%; margin-top: 2mm;">
                    <tr>
                        <td style="font-size: 8pt;">Designation: <strong>{{ $employee_type }}</strong></td>
                        <td style="text-align: right; font-size: 8pt; color: #6b7280;">Authorized Signature</td>
                    </tr>
                </table>
            </td>
            <td style="width: 37%; vertical-align: top; border-left: 0.25mm solid #e5e7eb; padding-left: 3mm; font-size: 8pt;">
                <table style="width: 100%;">
                    <tr><td>Present</td><td class="doc-right">{{ $present }}</td></tr>
                    <tr><td>Absent</td><td class="doc-right">{{ $absent }}</td></tr>
                    <tr><td>CL</td><td class="doc-right">{{ $cl }}</td></tr>
                    <tr><td>Total days</td><td class="doc-right">{{ $total_days }}</td></tr>
                    <tr><td>Per day rate</td><td class="doc-right">{{ $per_day_rate }}</td></tr>
                    <tr><td>This month salary</td><td class="doc-right">{{ $this_month_salary }}</td></tr>
                    <tr><td>- Advance</td><td class="doc-right">{{ $advance }}</td></tr>
                    <tr><td colspan="2"><hr class="doc-divider" style="margin: 1mm 0;"></td></tr>
                    <tr class="doc-total"><td>Net salary</td><td class="doc-right">{{ $net_salary }}</td></tr>
                </table>
                <div class="doc-total-hl" style="width: 100%; box-sizing: border-box; margin-top: 2mm; text-align: center;">Net Payable: {{ $net_salary }}</div>
                <p style="text-align: center; font-size: 8pt; margin-top: 2mm;">Status: <strong>{{ $status }}</strong></p>
            </td>
        </tr>
    </table>
</div>
