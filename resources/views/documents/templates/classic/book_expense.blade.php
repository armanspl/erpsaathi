<style>
    * { box-sizing: border-box; }
    .doc { width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Serif', Georgia, serif; color: #1f2937; background: #fff; }
    .frame { border: 0.6mm solid #92400e; padding: 4mm; }
    .hdr { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
    .hdr td { border: none; vertical-align: middle; padding: 0; }
    .logo { width: 14mm; height: 14mm; object-fit: contain; }
    .school { font-size: 13pt; font-weight: bold; color: #78350f; text-align: center; }
    .addr { font-size: 7.5pt; color: #78716c; text-align: center; margin-top: 0.6mm; }
    .badge {
        display: inline-block; margin: 2mm auto 0; padding: 1.2mm 5mm;
        background: #92400e; color: #fff; font-size: 8.5pt; font-weight: bold;
        letter-spacing: 0.3pt; text-transform: uppercase;
    }
    .center { text-align: center; }
    .meta { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    .meta td { border: 0.3mm solid #d6d3d1; padding: 1.8mm 2.2mm; font-size: 8.5pt; }
    .lbl { color: #78716c; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.2pt; }
    .val { font-weight: bold; color: #1c1917; }
    .block {
        margin-top: 2.5mm; border: 0.3mm solid #d6d3d1; background: #fffbeb; padding: 2.5mm;
        font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; white-space: pre-wrap; line-height: 1.45;
    }
    .total {
        margin-top: 2.5mm; background: #92400e; color: #fff; padding: 2mm 3mm;
        font-size: 10pt; font-weight: bold;
    }
</style>
<div class="doc">
    <div class="frame">
        <table class="hdr"><tr>
            <td style="width:18mm"><img class="logo" src="{{ $school_logo }}" alt=""></td>
            <td class="center">
                <div class="school">{{ $school_name }}</div>
                <div class="addr">{{ $school_address }}</div>
                <div class="badge">{{ $receipt_title }}</div>
            </td>
            <td style="width:18mm"></td>
        </tr></table>

        <table class="meta">
            <tr>
                <td style="width:50%"><span class="lbl">Expense No.</span> <span class="val">{{ $expense_no }}</span></td>
                <td style="width:50%"><span class="lbl">Date</span> <span class="val">{{ $expense_date }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="lbl">Student</span> <span class="val">{{ $student_name }}</span> ({{ $admission_id }}) · {{ $class_section }}</td>
            </tr>
        </table>

        <div class="block">{{ $items_table }}</div>
        <div class="total">Total: {{ $total_amount }}</div>
    </div>
</div>
