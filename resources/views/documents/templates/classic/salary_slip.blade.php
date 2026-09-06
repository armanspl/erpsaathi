<style>
    * { box-sizing: border-box; }
    .doc { position: relative; width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Sans', Helvetica, sans-serif; color: #1e293b; background: #ffffff; }
    .doc-border { border: 0.5mm solid #1e293b; padding: 5mm; }

    .doc-header { text-align: center; margin-bottom: 2mm; }
    .doc-logo { width: 20mm; height: 20mm; object-fit: contain; }
    .doc-logo-row { display: table; width: 100%; }
    .doc-logo-cell { display: table-cell; width: 24mm; vertical-align: middle; }
    .doc-logo-cell-right { display: table-cell; width: 24mm; vertical-align: middle; text-align: right; }
    .doc-school-name { display: table-cell; font-size: 22pt; font-weight: bold; color: #1d4ed8; vertical-align: middle; text-align: center; }
    .doc-school-sub { font-size: 8.5pt; color: #334155; margin-top: 1mm; }
    .doc-school-sub.bold { font-weight: bold; }

    .doc-title { display: inline-block; margin: 1.5mm auto; padding: 1.5mm 6mm; background: #15803d; border-top: 0.6mm solid #1e3a8a; border-bottom: 0.6mm solid #1e3a8a; color: #ffffff; font-size: 10pt; font-weight: bold; letter-spacing: 0.3pt; }

    .doc-divider { border: none; border-top: 0.4mm solid #1e293b; margin: 2mm 0; }

    .doc-grid { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .doc-grid td { border: 0.3mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; }
    .doc-label { color: #1e293b; }
    .doc-value { font-weight: bold; color: #1e293b; }

    .doc-block { border: 0.3mm solid #1e293b; border-top: none; padding: 2.5mm; font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; white-space: pre-wrap; line-height: 1.5; }

    .doc-side-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .doc-side-table td { padding: 1.5mm 2.5mm; }
    .doc-side-table .doc-right { text-align: right; }
    .doc-total td { font-weight: bold; border-top: 0.4mm solid #1e293b; }

    .doc-total-hl { width: 100%; border-collapse: collapse; background: #16a34a; }
    .doc-total-hl td { color: #ffffff; font-weight: bold; padding: 2mm 2.5mm; font-size: 9pt; }
    .doc-total-hl td.doc-right { text-align: right; }

    .doc-right { text-align: right; }
    .doc-signature { text-align: right; font-size: 9pt; font-weight: bold; color: #1e293b; }

    .doc-bottom-band { width: 100%; border-collapse: collapse; }
    .doc-bottom-band td { border: 0.35mm solid #1e293b; padding: 1.5mm 2.5mm; font-size: 9pt; vertical-align: middle; }
    .doc-bottom-band td.gray { background: #cbd5e1; font-weight: bold; }
    .doc-bottom-band td.italic { font-style: italic; text-align: center; }
    .doc-bottom-band td.value { font-weight: bold; }
</style>
<div class="doc">
  <div class="doc-border">

    <div class="doc-header">
        <div class="doc-title">{{ $slip_title }}</div>
        <div class="doc-logo-row">
            <div class="doc-logo-cell"><img class="doc-logo" src="{{ $school_logo }}" alt=""></div>
            <div class="doc-school-name">{{ $school_name }}</div>
            <div class="doc-logo-cell-right"><img class="doc-logo" src="{{ $school_logo }}" alt=""></div>
        </div>
        <div class="doc-school-sub">Address : {{ $school_address }}</div>
        <div class="doc-school-sub bold">Contact No. {{ $school_phone }}</div>
    </div>

    <hr class="doc-divider">

    <table class="doc-grid">
        <tr>
            <td style="width: 63%;"><span class="doc-label">Employee:</span> <span class="doc-value" style="color:#7f1d1d;">{{ $employee_name }}</span></td>
            <td style="width: 37%;"><span class="doc-label">Slip No:</span> <span class="doc-value">{{ $slip_no }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Employee Code:</span> <span class="doc-value">{{ $employee_code }}</span></td>
            <td><span class="doc-label">Period:</span> <span class="doc-value">{{ $period }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Employee Type:</span> <span class="doc-value">{{ $employee_type }}</span></td>
            <td><span class="doc-label">Status:</span> <span class="doc-value">{{ $status }}</span></td>
        </tr>
        <tr>
            <td><span class="doc-label">Days in Month:</span> <span class="doc-value">{{ $days_in_month }}</span></td>
            <td><span class="doc-label">Payment Mode:</span> <span class="doc-value">{{ $payment_mode }}</span></td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse;">
      <tr>
        <td style="width: 63%; vertical-align: top; padding: 0;">
            <div class="doc-block">{{ $earnings_table }}</div>
            <div class="doc-block" style="border-top: none;">{{ $deductions_table }}</div>

            <div style="border: 0.35mm solid #1e293b; border-top: none; padding: 3mm 2.5mm; font-size: 8.5pt; min-height: 12mm;">
                Remarks: {{ $remarks }}
            </div>
            <table style="width: 100%; border: 0.35mm solid #1e293b; border-top: none; border-collapse: collapse;">
                <tr>
                    <td style="padding: 1.5mm 2.5mm; font-size: 8.5pt; font-weight:bold;">Designation : {{ $employee_type }}</td>
                    <td class="doc-signature" style="padding: 1.5mm 2.5mm;">Authorized Signature</td>
                </tr>
            </table>
        </td>
        <td style="width: 37%; vertical-align: top; padding: 0;">
            <table class="doc-side-table" style="border: 0.35mm solid #1e293b; border-left: none;">
                <tr><td>Present</td><td class="doc-right">{{ $present }}</td></tr>
                <tr><td>Absent</td><td class="doc-right">{{ $absent }}</td></tr>
                <tr><td>CL</td><td class="doc-right">{{ $cl }}</td></tr>
                <tr><td>Total Days</td><td class="doc-right">{{ $total_days }}</td></tr>
                <tr><td>Per Day Rate</td><td class="doc-right">{{ $per_day_rate }}</td></tr>
                <tr><td>This Month Salary</td><td class="doc-right">{{ $this_month_salary }}</td></tr>
                <tr><td>- Advance</td><td class="doc-right">{{ $advance }}</td></tr>
                <tr class="doc-total"><td>NET SALARY</td><td class="doc-right">{{ $net_salary }}</td></tr>
            </table>
            <table class="doc-total-hl">
                <tr><td>Net Payable</td><td class="doc-right">{{ $net_salary }}</td></tr>
            </table>
        </td>
      </tr>
    </table>

    <table class="doc-bottom-band">
        <tr>
            <td class="gray" style="width: 22%;">Payment Mode</td>
            <td class="italic" style="width: 33%;">{{ $payment_mode }}</td>
            <td class="gray" style="width: 25%;">Status</td>
            <td class="value doc-right" style="width: 20%;">{{ $status }}</td>
        </tr>
    </table>

  </div>
</div>
