<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 6mm 8mm; size: A4 portrait; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10pt; color: #111827; margin: 0; }
    .sheet { border: 1.5pt solid {{ $accent_color }}; padding: 5pt 8pt 6pt; }
    .accent { height: 3pt; background: {{ $accent_color }}; margin: 0 0 4pt; }
    .top-meta { width: 100%; border-collapse: collapse; margin-bottom: 2pt; font-size: 8pt; color: #6b7280; }
    .top-meta td { border: none; padding: 0; }
    .brand-table { width: 100%; border-collapse: collapse; margin-bottom: 2pt; }
    .brand-table td { border: none; vertical-align: middle; padding: 0; }
    .logo { width: 40pt; height: 40pt; object-fit: contain; border-radius: 6pt; }
    .logo-fallback {
        width: 40pt; height: 40pt; border-radius: 6pt; background: {{ $accent_color }}; color: #fff;
        text-align: center; line-height: 40pt; font-size: 13pt; font-weight: bold;
    }
    .school-name { font-size: 14pt; font-weight: bold; margin: 0; text-align: center; color: #111827; }
    .school-line { font-size: 8pt; margin: 0; text-align: center; color: #6b7280; }
    .title-row { width: 100%; border-collapse: collapse; margin: 3pt 0; }
    .title-row td { border: none; vertical-align: middle; padding: 0; }
    .title-box {
        display: inline-block; background: {{ $accent_light }}; color: {{ $accent_color }}; border-radius: 3pt;
        padding: 1.5pt 12pt; font-weight: bold; font-size: 10pt;
    }
    .session { text-align: right; font-size: 9pt; font-weight: bold; color: {{ $accent_color }}; }
    .student { width: 100%; border-collapse: collapse; border: 0.8pt solid {{ $accent_border }}; margin-bottom: 3pt; background: {{ $accent_zebra }}; }
    .student > tr > td { border: none; padding: 1.5pt 7pt; vertical-align: top; width: 50%; }
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td { border: none; padding: 0.5pt 0; font-size: 9pt; vertical-align: top; }
    .info-table td.lbl { width: 76pt; white-space: nowrap; color: #6b7280; }
    .info-table td.colon { width: 6pt; color: #6b7280; }
    .info-table td.val { font-weight: bold; text-transform: uppercase; }
    table.marks { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
    table.marks th, table.marks td { border: 0.6pt solid {{ $accent_border }}; padding: 2.5pt 4pt; }
    table.marks th { background: {{ $accent_color }}; color: #fff; font-size: 8.5pt; text-align: center; }
    table.marks th.band { background: {{ $accent_dark }}; font-size: 9pt; }
    table.marks.annual th { font-size: 6.5pt; padding: 1.5pt 1.5pt; }
    table.marks.annual td { font-size: 7.5pt; padding: 1.5pt 1.5pt; }
    table.marks td.subj { font-weight: bold; text-transform: uppercase; font-size: 9pt; }
    table.marks.annual td.subj { font-size: 7.5pt; }
    table.marks td.num { text-align: center; font-size: 9pt; }
    table.marks td.grade { text-align: center; font-weight: bold; background: {{ $accent_light }}; color: {{ $accent_dark }}; }
    table.marks td.empty { text-align: center; color: #9ca3af; }
    table.marks-summary { width: 100%; border-collapse: collapse; margin: 0 0 3pt; }
    table.marks-summary th, table.marks-summary td { border: 0.6pt solid {{ $accent_border }}; padding: 1.5pt 4pt; font-size: 8pt; text-align: center; }
    table.marks-summary th { background: {{ $accent_color }}; color: #fff; }
    table.marks-summary td.subj { font-weight: bold; text-align: left; }
    .mid { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
    .mid > td { border: none; vertical-align: top; padding: 0; }
    .mid-left { width: 58%; padding-right: 6pt; }
    .mid-right { width: 42%; }
    .box { border: 0.8pt solid {{ $accent_border }}; border-radius: 2pt; }
    .box-pad { padding: 3pt 7pt; }
    .h-blue { color: {{ $accent_color }}; font-weight: bold; font-size: 9pt; margin: 0 0 1.5pt; }
    table.co-grid { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
    table.co-grid th, table.co-grid td { border: 0.6pt solid {{ $accent_border }}; padding: 1.5pt 6pt; font-size: 8pt; }
    table.co-grid th { color: {{ $accent_color }}; font-weight: bold; text-align: center; vertical-align: middle; }
    table.co-grid th.area-head { text-align: left; }
    table.co-grid td.area { font-weight: bold; text-transform: uppercase; color: #374151; }
    table.co-grid td.grade { text-align: center; font-weight: bold; color: #374151; }
    .remarks-box { text-align: center; }
    .remarks-opts { font-weight: bold; font-size: 9pt; margin-top: 1pt; color: #374151; }
    .sum-box { border: 0.8pt solid {{ $accent_border }}; border-radius: 2pt; padding: 2pt 7pt; margin-bottom: 2pt; font-size: 8.5pt; font-weight: bold; background: {{ $accent_zebra }}; }
    .sum-val { color: {{ $accent_color }}; text-decoration: underline; }
    .sum-grade { display: inline-block; background: {{ $accent_border }}; padding: 0 6pt; color: {{ $accent_dark }}; text-decoration: none; border-radius: 2pt; }
    .bottom { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
    .bottom td { border: none; vertical-align: top; padding: 0; }
    .bottom-left { width: 42%; padding-right: 6pt; }
    .bottom-right { width: 58%; }
    .grade-title {
        display: inline-block; background: {{ $accent_light }}; border: 0.6pt solid {{ $accent_border }}; border-radius: 2pt;
        padding: 1pt 10pt; color: {{ $accent_color }}; font-weight: bold; font-size: 8.5pt; margin-bottom: 2pt;
    }
    .grade-grid { width: 100%; border-collapse: collapse; font-size: 8pt; }
    .grade-grid td { border: none; padding: 1pt 2pt; }
    .g-range { color: #059669; font-weight: bold; }
    .g-letter { color: #7c3aed; font-weight: bold; }
    .chart-title { text-align: center; font-weight: bold; font-size: 9pt; margin: 1pt 0 2pt; color: {{ $accent_color }}; }
    .sig-row { width: 100%; border-collapse: collapse; border-top: 0.8pt solid {{ $accent_border }}; margin-top: 3pt; }
    .sig-row td { border: none; width: 50%; vertical-align: bottom; padding-top: 16pt; font-weight: bold; font-size: 9.5pt; color: #374151; }
    .sig-row td.right { text-align: right; }
    .sig-img { max-height: 30pt; max-width: 90pt; margin-bottom: 2pt; }
    .sig-space { height: 22pt; }
    .stamp { max-height: 34pt; max-width: 34pt; margin-bottom: 2pt; }

    /* "None" PDF colour: header bands normally fill with accent_color/accent_dark and hardcode
       white text on top, so just greying accent_color would still leave white-on-grey headers.
       These overrides drop the fill and force black text/borders instead — a plain black & white
       look, not merely a differently-coloured one. */
    .sheet.mono .logo-fallback,
    .sheet.mono .accent {
        background: #ffffff; color: #000000; border-color: #000000;
    }
    .sheet.mono table.marks th,
    .sheet.mono table.marks th.band,
    .sheet.mono table.marks-summary th {
        background: #ffffff;
        color: #000000;
    }
    .sheet.mono .h-blue,
    .sheet.mono .sum-val,
    .sheet.mono .sum-grade,
    .sheet.mono .grade-title,
    .sheet.mono table.co-grid th {
        color: #000000;
    }
    .sheet.mono table.marks td.grade,
    .sheet.mono .sum-grade,
    .sheet.mono .title-box,
    .sheet.mono .grade-title {
        background: #ffffff;
    }
    .sheet.mono .g-range,
    .sheet.mono .g-letter {
        color: #000000;
    }
</style>
</head>
<body>
<div class="sheet {{ $mono_class }}">
    <div class="accent"></div>
    <table class="top-meta"><tr>
        <td>Reg No.: {{ $registration_no }}</td>
        <td style="text-align:right">U-DISE CODE: {{ $udise_code }}</td>
    </tr></table>

    <table class="brand-table"><tr>
        <td style="width:56pt">{!! $logo_html !!}</td>
        <td>
            <div class="school-name">{{ $school_name }}</div>
            <div class="school-line">{{ $school_address }}</div>
            <div class="school-line">{{ $school_phone_line }}</div>
        </td>
        <td style="width:40pt"></td>
    </tr></table>

    <table class="title-row"><tr>
        <td style="width:18%"></td>
        <td style="text-align:center"><span class="title-box">{{ $exam_title }}</span></td>
        <td class="session" style="width:22%">Session: {{ $session_year }}</td>
    </tr></table>

    <table class="student"><tr>
        <td>
            <table class="info-table">
                <tr><td class="lbl">Student's Name</td><td class="colon">:</td><td class="val">{{ $student_name }}</td></tr>
                <tr><td class="lbl">Father's Name</td><td class="colon">:</td><td class="val">{{ $father_name }}</td></tr>
                <tr><td class="lbl">Mother's Name</td><td class="colon">:</td><td class="val">{{ $mother_name }}</td></tr>
                <tr><td class="lbl">Date of Birth</td><td class="colon">:</td><td class="val">{{ $dob }}</td></tr>
            </table>
        </td>
        <td>
            <table class="info-table">
                <tr><td class="lbl">Admission No.</td><td class="colon">:</td><td class="val">{{ $admission_id }}</td></tr>
                <tr><td class="lbl">Class</td><td class="colon">:</td><td class="val">{{ $class }}</td></tr>
                <tr><td class="lbl">Section</td><td class="colon">:</td><td class="val">{{ $section }}</td></tr>
                <tr><td class="lbl">Roll No.</td><td class="colon">:</td><td class="val">{{ $roll_number }}</td></tr>
            </table>
        </td>
    </tr></table>

    <table class="marks {{ $marks_table_class }}">
        <thead>{!! $marks_thead_html !!}</thead>
        <tbody>{!! $marks_html !!}</tbody>
    </table>
    {!! $marks_summary_html !!}

    <table class="mid"><tr>
        <td class="mid-left">
            {!! $co_scholastic_html !!}
            <div class="box box-pad remarks-box">
                <div class="h-blue" style="text-align:center">Class Teacher's Remarks:</div>
                <div class="remarks-opts">{{ $remarks }}</div>
            </div>
        </td>
        <td class="mid-right">
            <div class="sum-box">ATTENDANCE: <span class="sum-val">{{ $attendance }}</span></div>
            <div class="sum-box">OVERALL MARKS: <span class="sum-val">{{ $total_marks }}</span></div>
            <div class="sum-box">OVERALL PERCENTAGE: <span class="sum-val">{{ $percentage }}</span></div>
            <div class="sum-box">OVERALL GRADE: <span class="sum-grade">{{ $grade }}</span></div>
            <div class="sum-box">CLASS RANK: <span class="sum-val">{{ $rank }}</span></div>
        </td>
    </tr></table>

    <table class="bottom"><tr>
        <td class="bottom-left">
            <div class="box box-pad">
                <div style="text-align:center"><span class="grade-title">GRADING SYSTEM</span></div>
                {!! $grading_html !!}
            </div>
        </td>
        <td class="bottom-right">
            <div class="box box-pad">
                <div class="chart-title">Subject Wise Marks</div>
                {!! $chart_html !!}
            </div>
        </td>
    </tr></table>

    <table class="sig-row"><tr>
        <td>{{ $class_teacher_signature }}</td>
        <td class="right">
            {!! $stamp_html !!}
            {!! $principal_sign_html !!}
            <div>{{ $principal_signature }}</div>
        </td>
    </tr></table>
</div>
</body>
</html>
