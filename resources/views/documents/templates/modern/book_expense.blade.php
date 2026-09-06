<style>
    * { box-sizing: border-box; }
    .doc { width: 100%; margin: 0; padding: 0 0 4mm; font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #111827; background: #fff; }
    .accent { height: 3.5mm; margin: 0 0 4mm; background: #4f46e5; }
    .hdr { width: 100%; border-collapse: collapse; }
    .hdr td { border: none; vertical-align: middle; padding: 0; }
    .logo { width: 13mm; height: 13mm; object-fit: contain; border-radius: 2mm; }
    .school { font-size: 12.5pt; font-weight: bold; }
    .addr { font-size: 7.5pt; color: #6b7280; margin-top: 0.5mm; }
    .badge {
        display: inline-block; margin-top: 1.5mm; padding: 1mm 3.5mm; border-radius: 1mm;
        background: #eef2ff; color: #4f46e5; font-size: 8pt; font-weight: bold; text-transform: uppercase;
    }
    .meta { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    .meta td { border: 0.3mm solid #c7d2fe; padding: 1.8mm 2.2mm; font-size: 8.5pt; background: #f8fafc; }
    .lbl { color: #9ca3af; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.2pt; }
    .val { font-weight: bold; color: #111827; }
    .block {
        margin-top: 2.5mm; border: 0.3mm solid #e5e7eb; border-left: 1mm solid #4f46e5; background: #f9fafb; padding: 2.5mm;
        font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; white-space: pre-wrap; line-height: 1.45;
    }
    .total {
        margin-top: 2.5mm; background: #4f46e5; color: #fff; padding: 2mm 3mm; border-radius: 1.5mm;
        font-size: 10pt; font-weight: bold;
    }
</style>
<div class="doc">
    <div class="accent"></div>
    <table class="hdr"><tr>
        <td style="width:16mm"><img class="logo" src="{{ $school_logo }}" alt=""></td>
        <td>
            <div class="school">{{ $school_name }}</div>
            <div class="addr">{{ $school_address }}</div>
            <div class="badge">{{ $receipt_title }}</div>
        </td>
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
