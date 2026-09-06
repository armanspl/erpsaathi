@include('documents.templates.partials.modern-style')
<div class="doc">
    <div class="doc-accent"></div>
    @include('documents.templates.partials.modern-fee-header', ['title_bg' => '#b91c1c', 'title_fg' => '#ffffff'])
    <hr class="doc-divider" style="margin: 0.8mm 0;">

    <table class="doc-grid">
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Receipt No.</span> <span class="doc-value">{{ $receipt_no }}</span></td>
            <td class="doc-cell"><span class="doc-label">Date</span> <span class="doc-value">{{ $receipt_date }}</span></td>
            <td class="doc-cell"><span class="doc-label">Due date</span> <span class="doc-value">{{ $due_date }}</span></td>
        </tr>
        <tr class="doc-row">
            <td class="doc-cell" colspan="2"><span class="doc-label">Student</span> <span class="doc-value">{{ $student_name }}</span> ({{ $admission_id }}) · Roll {{ $roll_number }}</td>
            <td class="doc-cell"><span class="doc-label">Class / Section</span> <span class="doc-value">{{ $class }} {{ $section }}</span></td>
        </tr>
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Father</span> <span class="doc-value">{{ $father_name }}</span></td>
            <td class="doc-cell" colspan="2"><span class="doc-label">Mother</span> <span class="doc-value">{{ $mother_name }}</span></td>
        </tr>
    </table>

    <div class="doc-block">{!! $due_table !!}</div>
    <div class="doc-total-hl">TOTAL DUE {{ $total_due }}</div>

    <p class="doc-label" style="margin-top: 2mm;">Payment history</p>
    <div class="doc-block" style="border-left-color: #9ca3af;">{!! $payment_history_table !!}</div>
    <p style="font-size: 7.5pt; color: #9ca3af;">{{ $remarks }}</p>
</div>
