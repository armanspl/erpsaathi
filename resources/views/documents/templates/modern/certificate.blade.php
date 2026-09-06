@include('documents.templates.partials.modern-style')
<div class="doc">
    <div class="doc-accent"></div>
    <table class="doc-header">
        <tr>
            <td class="doc-logo-cell"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
            <td class="doc-header-text">
                <div class="doc-school-name">{{ $school_name }}</div>
                <div class="doc-school-sub">{{ $school_address }}</div>
            </td>
            <td class="doc-logo-cell" style="text-align: right; width: 30mm;"><img src="{{ $school_banner }}" style="max-height: 14mm; max-width: 28mm;" alt=""></td>
        </tr>
    </table>
    <div class="doc-title">{{ $certificate_title }}</div>
    <hr class="doc-divider">

    <p style="font-size: 9.5pt; color: #6b7280; margin: 3mm 0 1mm;">This is to certify that</p>
    <p style="font-size: 15pt; font-weight: bold; color: #111827; margin: 0 0 3mm;">{{ $recipient_name }}</p>
    <p class="doc-body-text">{{ $certificate_body }}</p>

    <table class="doc-grid" style="margin-top: 4mm;">
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Father</span><br><span class="doc-value">{{ $father_name }}</span></td>
            <td class="doc-cell"><span class="doc-label">Mother</span><br><span class="doc-value">{{ $mother_name }}</span></td>
            <td class="doc-cell"><span class="doc-label">Admission ID</span><br><span class="doc-value">{{ $admission_id }}</span></td>
            <td class="doc-cell"><span class="doc-label">Roll No.</span><br><span class="doc-value">{{ $roll_number }}</span></td>
        </tr>
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Class</span><br><span class="doc-value">{{ $class }}</span></td>
            <td class="doc-cell"><span class="doc-label">Section</span><br><span class="doc-value">{{ $section }}</span></td>
            <td class="doc-cell"><span class="doc-label">Branch</span><br><span class="doc-value">{{ $branch }}</span></td>
            <td class="doc-cell"><span class="doc-label">Session</span><br><span class="doc-value">{{ $session_year }}</span></td>
        </tr>
        <tr class="doc-row">
            <td class="doc-cell"><span class="doc-label">Date of Birth</span><br><span class="doc-value">{{ $dob }}</span></td>
            <td class="doc-cell" colspan="2"><span class="doc-label">DOB in words</span><br><span class="doc-value">{{ $dob_words }}</span></td>
            <td class="doc-cell"><span class="doc-label">Reference No.</span><br><span class="doc-value">{{ $reference_no }}</span></td>
        </tr>
        <tr class="doc-row">
            <td class="doc-cell" colspan="2"><span class="doc-label">Purpose</span><br><span class="doc-value">{{ $purpose }}</span></td>
            <td class="doc-cell"><span class="doc-label">Conduct</span><br><span class="doc-value">{{ $conduct }}</span></td>
            <td class="doc-cell"><span class="doc-label">Character</span><br><span class="doc-value">{{ $character }}</span></td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 8mm;">
        <tr>
            <td style="width: 33%;"><span class="doc-label">Issue date</span><br><span class="doc-value">{{ $issue_date }}</span></td>
            <td style="width: 34%; text-align: center;">
                <img src="{{ $principal_signature_image }}" style="height: 12mm;" alt="">
                <div class="doc-signature">Principal</div>
            </td>
            <td style="width: 33%; text-align: right;"><img src="{{ $school_stamp }}" style="height: 18mm;" alt=""></td>
        </tr>
    </table>
</div>
