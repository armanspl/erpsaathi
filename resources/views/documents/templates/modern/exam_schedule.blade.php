<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: 297mm 210mm; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8pt; color: #111827;
        padding: 7mm 12mm;
    }

    table.header { width: 100%; border-collapse: collapse; margin: 0; }
    table.header td { vertical-align: middle; border: none; padding: 0 4pt 3pt 0; }
    td.logo-cell { width: 48pt; }
    .logo { width: 40pt; height: 40pt; object-fit: contain; border-radius: 4pt; }
    .logo-fallback {
        width: 40pt; height: 40pt; border-radius: 4pt; background: {{ $accent_bar_bg }}; color: {{ $accent_bar_text }};
        border: 0.7pt solid {{ $accent_border }};
        text-align: center; line-height: 40pt; font-size: 12pt; font-weight: bold;
    }
    .school-name { font-size: 13.5pt; font-weight: bold; color: #111827; margin: 0 0 1.5pt; }
    .school-address { font-size: 7pt; color: #6b7280; margin: 0 0 1.5pt; line-height: 1.25; }
    .school-meta, .school-contact { font-size: 6.5pt; color: #4b5563; margin: 0 0 1pt; }
    td.side-meta { width: 78pt; vertical-align: middle; }
    .meta-box { border: 0.7pt solid {{ $accent_border }}; border-radius: 3pt; padding: 3pt 6pt; text-align: center; background: {{ $accent_light }}; }
    .meta-box .lbl { font-size: 5.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: {{ $accent_color }}; font-weight: bold; margin: 0 0 1pt; }
    .meta-box .val { font-size: 8.5pt; color: {{ $accent_dark }}; font-weight: bold; margin: 0; }

    .header-accent { height: 2.2pt; background: {{ $accent_bar_bg }}; border-top: 0.9pt solid {{ $accent_border }}; border-bottom: 0.45pt solid {{ $accent_border }}; margin-top: 1pt; }
    .header-accent-thin { height: 0.8pt; background: {{ $accent_border }}; margin-bottom: 4pt; }

    .doc-title { text-align: center; margin: 0 0 4pt; }
    .doc-title h1 {
        font-size: 11pt; margin: 0; padding: 3.5pt 8pt; color: {{ $accent_bar_text }}; background: {{ $accent_bar_bg }};
        border: 0.7pt solid {{ $accent_border }};
        text-transform: uppercase; letter-spacing: 0.8pt; font-weight: bold; border-radius: 2pt 2pt 0 0;
    }
    .doc-title .sub {
        font-size: 8.5pt; margin: 0; padding: 3pt 8pt; color: {{ $accent_dark }}; background: {{ $accent_light }};
        border: 0.55pt solid {{ $accent_border }}; border-top: none; font-weight: bold; border-radius: 0 0 2pt 2pt;
    }

    table.schedule { width: 100%; border-collapse: collapse; margin-top: 1pt; page-break-inside: auto; }
    table.schedule th, table.schedule td {
        border: 0.55pt solid {{ $accent_border }}; padding: 2pt 2.5pt; text-align: center; vertical-align: middle;
    }
    table.schedule th {
        background: {{ $accent_bar_bg }}; color: {{ $accent_bar_text }}; font-size: 6.8pt; font-weight: bold;
        text-transform: uppercase; letter-spacing: 0.15pt;
    }
    table.schedule th.col-date, table.schedule td.col-date { width: 58pt; white-space: nowrap; text-align: left; }
    table.schedule th.col-sitting, table.schedule td.col-sitting { width: 22pt; }
    table.schedule td.col-date { font-weight: bold; background: {{ $accent_zebra }}; font-size: 7.2pt; }
    .dow { font-weight: 600; color: #6b7280; font-size: 6.5pt; margin-top: 0.5pt; }
    td.col-sitting { font-weight: bold; color: {{ $accent_color }}; background: {{ $accent_zebra }}; font-size: 7.5pt; }
    .subject-cell { font-size: 7pt; font-weight: 600; line-height: 1.15; }
    .holiday { color: #b45309; background: #fffbeb; font-style: italic; font-size: 7pt; }
    .empty { color: #9ca3af; }

    table.notes { width: 100%; border-collapse: collapse; margin-top: 6pt; page-break-inside: avoid; }
    table.notes td { border: none; padding: 0; vertical-align: top; font-size: 7pt; color: #111827; line-height: 1.25; }
    td.notes-label { width: 42pt; font-weight: bold; white-space: nowrap; }
    td.notes-content p { margin: 0 0 1.5pt; }
</style>
</head>
<body class="{{ $mono_class }}">
    <table class="header"><tr>
        <td class="logo-cell">{!! $logo_html !!}</td>
        <td>
            <p class="school-name">{{ $school_name }}</p>
            <p class="school-address">{{ $school_address }}</p>
            <p class="school-meta">{{ $school_meta_line }}</p>
            <p class="school-contact">{{ $school_contact_line }}</p>
        </td>
        <td class="side-meta">
            <div class="meta-box">
                <p class="lbl">Session</p>
                <p class="val">{{ $session_year }}</p>
            </div>
        </td>
    </tr></table>
    <div class="header-accent"></div>
    <div class="header-accent-thin"></div>

    <div class="doc-title">
        <h1>{{ $schedule_title }}</h1>
        <p class="sub">{{ $exam_title }}</p>
    </div>

    <table class="schedule">
        <thead><tr>{!! $schedule_header_html !!}</tr></thead>
        <tbody>{!! $schedule_body_html !!}</tbody>
    </table>

    <table class="notes"><tr>
        <td class="notes-label">Notes:-</td>
        <td class="notes-content">
            <p>* Re-examination will not be taken on any circumstance.</p>
            <p><strong>* All fees must be cleared before commencement of Exam.</strong></p>
            <p>* Without admit card no student will be allowed for Exam.</p>
            <p>* For any assistance and clarification please contact office.</p>
        </td>
    </tr></table>
</body>
</html>
