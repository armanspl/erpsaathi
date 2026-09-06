@include('documents.templates.partials.modern-card-style')
<div class="doc">
    <div class="doc-accent"></div>
    <div class="doc-header">
        <img class="doc-logo" src="{{ $school_logo }}" alt=""><span class="doc-school-name">{{ $school_name }}</span>
        <div class="doc-title">{{ $card_title }}</div>
    </div>
    <div class="doc-center" style="margin-top: 1mm;">
        <img class="doc-photo" src="{{ $photo }}" alt="">
    </div>
    <div class="doc-name">{{ $member_name }}</div>
    <div class="doc-id">{{ $member_id }} · {{ $member_type }}</div>
    <table class="doc-grid">
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Class</span> <span class="doc-value">{{ $class_section }}</span></td>
            <td class="doc-cell"><span class="doc-label">From</span> <span class="doc-value">{{ $valid_from }}</span></td>
        </tr>
    </table>
    <div class="doc-footer">Valid until {{ $valid_until }} · Code: {{ $barcode }}</div>
</div>
