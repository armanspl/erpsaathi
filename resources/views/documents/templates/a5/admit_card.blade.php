<style>
    * { box-sizing: border-box; }
    .doc {
        width: 100%; margin: 0; padding: 0;
        font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #1f2937; background: #fff;
        page-break-inside: avoid; page-break-after: avoid;
    }

    .hdr { background: {{ $accent_bar_bg }}; color: {{ $accent_bar_text }}; text-align: center; padding: 2mm 3mm 1.6mm; border-bottom: 0.3mm solid {{ $accent_border }}; }
    .hdr .school-name { font-size: 10pt; font-weight: bold; letter-spacing: 0.15pt; text-transform: uppercase; line-height: 1.15; }
    .hdr .line2 { font-size: 5.4pt; margin-top: 0.6mm; color: {{ $accent_bar_text_soft }}; line-height: 1.2; }
    .hdr .line3 { font-size: 5pt; margin-top: 0.3mm; color: {{ $accent_bar_text_softer }}; line-height: 1.2; }

    .body { padding: 0 2.5mm 1.5mm; }

    .title-bar { text-align: center; padding: 1.2mm 0 1mm; }
    .title-bar .t { font-size: 8pt; font-weight: bold; color: {{ $accent_color }}; letter-spacing: 0.2pt; text-transform: uppercase; }

    .sec-bar {
        background: {{ $accent_bar_bg }}; color: {{ $accent_bar_text }}; font-size: 6.2pt; font-weight: bold;
        letter-spacing: 0.2pt; text-transform: uppercase; padding: 0.9mm 1.8mm;
        border: 0.2mm solid {{ $accent_border }};
    }
    .sec-gap { margin-top: 1.4mm; }

    .details-box { border: 0.2mm solid {{ $accent_border }}; border-top: none; padding: 1.2mm 1.6mm; }
    .details-table { width: 100%; border-collapse: collapse; }
    .details-table td { border: none; padding: 0.35mm 0; font-size: 6.5pt; vertical-align: top; line-height: 1.2; }
    .details-table .lbl { width: 24mm; font-weight: bold; color: {{ $accent_color }}; }
    .details-table .val { color: #111827; }
    .photo-cell { width: 18mm; text-align: center; vertical-align: top; padding-left: 1.5mm !important; }
    .photo-box {
        width: 15mm; height: 18mm; margin: 0 auto;
        border: 0.2mm solid {{ $accent_border }}; overflow: hidden; background: #fff;
        text-align: center;
    }
    .photo-box img { width: 15mm; height: 18mm; object-fit: cover; display: block; }
    .sig-hint { font-size: 5pt; color: #4b5563; text-align: center; margin-top: 0.6mm; }
    .sig-hint .ln { display: inline-block; width: 10mm; border-bottom: 0.2mm solid {{ $accent_border }}; margin-left: 0.5mm; vertical-align: middle; }

    table.sched { width: 100%; border-collapse: collapse; }
    table.sched th, table.sched td { border: 0.15mm solid {{ $accent_border }}; padding: 0.55mm 0.9mm; font-size: 5.8pt; text-align: left; vertical-align: middle; line-height: 1.15; }
    table.sched th { background: {{ $accent_light }}; color: {{ $accent_color }}; font-size: 5.4pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1pt; }
    table.sched td.dt { color: {{ $accent_color }}; font-weight: bold; white-space: nowrap; }
    table.sched td.day { white-space: nowrap; }
    table.sched td.sit { font-weight: bold; color: {{ $accent_color }}; }
    table.sched td.sub { white-space: nowrap; }
    table.sched .center { text-align: center; }
    table.sched .empty { text-align: center; color: #9ca3af; }
    table.sched tbody tr:nth-child(even) td { background: {{ $accent_zebra }}; }

    .notes-block { padding: 0.8mm 0.3mm; font-size: 5.5pt; line-height: 1.3; max-height: 28mm; overflow: hidden; }
    .notes-block ol, .notes-block ul { margin: 0; padding-left: 3mm; }
    .notes-block li { margin: 0 0 0.4mm; }
    .notes-block p { margin: 0 0 0.4mm; }
    .notes-block p:last-child { margin-bottom: 0; }

    .sig-wrap { width: 100%; border-collapse: collapse; margin-top: 2.5mm; }
    .sig-wrap td.sig-col { border: none; width: 33.33%; vertical-align: bottom; text-align: center; padding: 0 1mm; }
    .sig-img-slot { height: 8mm; text-align: center; margin: 0 auto 0.8mm; }
    .sig-img { max-height: 7.5mm; max-width: 26mm; display: inline-block; }
    .sig-label {
        display: inline-block; min-width: 28mm; border-top: 0.2mm solid #6b7280;
        padding-top: 0.5mm; font-size: 5.4pt; color: #374151; text-align: center;
    }
</style>
<div class="doc {{ $mono_class }}">
    <div class="hdr">
        <div class="school-name">{{ $school_name }}</div>
        @if($school_contact_line)<div class="line2">{{ $school_contact_line }}</div>@endif
        @if($school_meta_line)<div class="line3">{{ $school_meta_line }}</div>@endif
    </div>

    <div class="body">
        <div class="title-bar"><div class="t">Admit Card — {{ $exam_title }}</div></div>

        <div class="sec-bar">Student Details</div>
        <div class="details-box">
            <table class="details-table">
                <tr>
                    <td style="width:78%">
                        <table class="details-table" style="margin:0">
                            <tr><td class="lbl">Student Name</td><td class="val">{{ $student_name }}</td></tr>
                            <tr><td class="lbl">Roll Number</td><td class="val">{{ $roll_number }}</td></tr>
                            <tr><td class="lbl">Class</td><td class="val">{{ $class }}</td></tr>
                            <tr><td class="lbl">Father's Name</td><td class="val">{{ $father_name }}</td></tr>
                            <tr><td class="lbl">Mother's Name</td><td class="val">{{ $mother_name }}</td></tr>
                            <tr><td class="lbl">Date of Birth</td><td class="val">{{ $dob }}</td></tr>
                            <tr><td class="lbl">Registration No.</td><td class="val">{{ $admission_id }}</td></tr>
                        </table>
                    </td>
                    <td class="photo-cell">
                        <div class="photo-box">{!! $photo_html !!}</div>
                        <div class="sig-hint">Sign:<span class="ln"></span></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sec-bar sec-gap">Examination Schedule</div>
        {!! $schedule_table_html !!}

        <div class="sec-bar sec-gap">General Instructions</div>
        <div class="notes-block">{!! $general_instructions !!}</div>

        <table class="sig-wrap">
            <tr>
                <td class="sig-col">
                    <div class="sig-img-slot">{!! $class_teacher_sign_html !!}</div>
                    <div class="sig-label">Class Teacher</div>
                </td>
                <td class="sig-col">
                    <div class="sig-img-slot">{!! $exam_controller_sign_html !!}</div>
                    <div class="sig-label">{{ $signature_line }}</div>
                </td>
                <td class="sig-col">
                    <div class="sig-img-slot">{!! $principal_sign_html !!}</div>
                    <div class="sig-label">{{ $principal_signature }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
