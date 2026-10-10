<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Student;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Official FORM S03/UDISE — "Format to update details of Active Student".
 * Layout matches the UDISE Plus paper form: school block, FOR strip,
 * Existing vs Updated comparison columns, attachments, undertakings, signatures.
 */
class UdiseS03PdfService
{
    private const BASE_PT = 9.3;

    private const MIN_PT = 5.0;

    /** Below this single-line size, a two-line split is tried instead. */
    private const TWO_LINE_BELOW_PT = 6.5;

    /** Measured content width (pt) of each value cell, keyed by data-fit; null while measuring. */
    private ?array $fitWidths = null;

    private ?\Dompdf\FontMetrics $fontMetrics = null;

    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function binary(Student $student, array $overrides = []): string
    {
        // Pass 1: lay the form out with empty values and measure every value cell.
        $this->fitWidths = null;
        $widths = [];
        $measure = $this->makeDompdf();
        $measure->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$widths) {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && $node->hasAttribute('data-fit')) {
                $widths[$node->getAttribute('data-fit')] = (float) $frame->get_content_box()['w'];
            }
        }]]);
        $measure->loadHtml($this->html($student, $overrides));
        $measure->render();

        // Pass 2: real values, each shrunk only as far as its own cell needs.
        $this->fitWidths = $widths;
        $this->fontMetrics = $measure->getFontMetrics();
        $dompdf = $this->makeDompdf();
        $dompdf->loadHtml($this->html($student, $overrides));
        $dompdf->render();
        $this->fitWidths = null;

        return $dompdf->output();
    }

    private function makeDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');

        return $dompdf;
    }

    public function streamDownload(Student $student, string $filename, array $overrides = [])
    {
        $binary = $this->binary($student, $overrides);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Value cell whose font shrinks only when the full text is wider than the cell
     * (width measured in pass 1); short values keep the normal size. Never truncates:
     * if even MIN_PT is too wide, the text wraps inside the cell instead.
     */
    private function fitTd(string $key, string $raw, string $attrs = ' class="f-val"'): string
    {
        $raw = trim($raw);
        $attrs .= ' data-fit="'.$key.'"';

        if ($this->fitWidths === null || $raw === '') {
            return '<td'.$attrs.'>&nbsp;</td>';
        }

        $html = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        $avail = $this->fitWidths[$key] ?? 0.0;
        if ($avail <= 0 || ! $this->fontMetrics) {
            return '<td'.$attrs.'>'.$html.'</td>';
        }

        $width = $this->textWidth($raw);
        if ($width <= $avail) {
            return '<td'.$attrs.'>'.$html.'</td>';
        }

        $size = $this->sizeFor($width, $avail);
        if ($size >= self::TWO_LINE_BELOW_PT) {
            return '<td'.$attrs.' style="font-size:'.$size.'pt;">'.$html.'</td>';
        }

        // Very long: two balanced lines at a larger size reads better than one tiny line.
        $words = preg_split('/\s+/u', $raw);
        $best = null;
        for ($i = 1; $i < count($words); $i++) {
            $lines = [implode(' ', array_slice($words, 0, $i)), implode(' ', array_slice($words, $i))];
            $w = max($this->textWidth($lines[0]), $this->textWidth($lines[1]));
            if ($best === null || $w < $best[0]) {
                $best = [$w, $lines];
            }
        }
        if ($best) {
            $two = $this->sizeFor($best[0], $avail);
            if ($two > $size && $two >= self::MIN_PT) {
                $two = min($two, 7.5);
                $lines = array_map(fn ($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $best[1]);

                return '<td'.$this->twoLineAttrs($attrs).' style="font-size:'.$two.'pt;">'.implode('<br>', $lines).'</td>';
            }
        }

        // Still too wide even at the minimum: wrap inside the cell rather than cut text.
        return $size >= self::MIN_PT
            ? '<td'.$attrs.' style="font-size:'.$size.'pt;">'.$html.'</td>'
            : '<td'.$this->twoLineAttrs($attrs).' style="font-size:'.self::MIN_PT.'pt; word-wrap:break-word;">'.$html.'</td>';
    }

    private function twoLineAttrs(string $attrs): string
    {
        return str_replace('class="f-val"', 'class="f-val two"', $attrs);
    }

    /** Invisible first row that fixes the column widths (Dompdf ignores <col> widths). */
    private function sizerRow(array $widthsPt): string
    {
        return '<tr class="sz">'.implode('', array_map(fn ($w) => '<td style="width:'.$w.'pt"></td>', $widthsPt)).'</tr>';
    }

    private function textWidth(string $text): float
    {
        return (float) $this->fontMetrics->getTextWidth($text, $this->fontMetrics->getFont('DejaVu Sans', 'normal'), self::BASE_PT);
    }

    /** Largest size (0.1pt steps, ≤ base) at which text $widthAtBase pt wide at BASE_PT fits $avail. */
    private function sizeFor(float $widthAtBase, float $avail): float
    {
        return min(self::BASE_PT, floor(self::BASE_PT * ($avail * 0.97) / $widthAtBase * 10) / 10);
    }

    /** @param  array<string, mixed>  $overrides */
    public function html(Student $student, array $overrides = []): string
    {
        $student->loadMissing([
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
        ]);

        $school = $this->dataBuilder->schoolContext();
        $setting = \App\Models\SchoolSetting::current();
        $session = AcademicSession::fromRequest(request(), true);
        $sessionName = $session?->name ?: ($school['session_year'] ?? '');

        // Academic year like "2025 - 26"
        $academicYear = $this->formatAcademicYear($sessionName);

        $udise = $student->udiseDetail;
        $classSection = trim(
            ($student->schoolClass?->name ?? '')
            .($student->section?->name ? ' - '.$student->section->name : '')
        );

        $updated = [
            'name' => $overrides['updated_name'] ?? ($student->name ?? ''),
            'dob' => $overrides['updated_dob'] ?? ($student->dob ? Carbon::parse($student->dob)->format('d/m/Y') : ''),
            'gender' => $overrides['updated_gender'] ?? ($student->gender ?? ''),
            'aadhaar' => $overrides['updated_aadhaar'] ?? ($student->aadhar_no ?? ''),
            'name_as_per_aadhaar' => $overrides['updated_name_as_per_aadhaar']
                ?? ($udise?->name_as_per_aadhaar ?: ($student->name ?? '')),
            'class_section' => $overrides['updated_class_section'] ?? $classSection,
            'mother_name' => $overrides['updated_mother_name']
                ?? ($udise?->mother_name ?: ($student->mother?->name ?? '')),
            'father_name' => $overrides['updated_father_name']
                ?? ($udise?->father_name ?: ($student->father?->name ?? '')),
        ];

        // Existing column: form overrides, else same ERP snapshot (so Prepare never prints blank).
        $existingDefaults = [
            'name' => $student->name ?? '',
            'dob' => $student->dob ? Carbon::parse($student->dob)->format('d/m/Y') : '',
            'gender' => $student->gender ?? '',
            'aadhaar' => $student->aadhar_no ?? '',
            'name_as_per_aadhaar' => $udise?->name_as_per_aadhaar ?: ($student->name ?? ''),
            'class_section' => $classSection,
            'mother_name' => $udise?->mother_name ?: ($student->mother?->name ?? ''),
            'father_name' => $udise?->father_name ?: ($student->father?->name ?? ''),
        ];
        $existing = [
            'name' => $overrides['existing_name'] ?? $existingDefaults['name'],
            'dob' => $overrides['existing_dob'] ?? $existingDefaults['dob'],
            'gender' => $overrides['existing_gender'] ?? $existingDefaults['gender'],
            'aadhaar' => $overrides['existing_aadhaar'] ?? $existingDefaults['aadhaar'],
            'name_as_per_aadhaar' => $overrides['existing_name_as_per_aadhaar'] ?? $existingDefaults['name_as_per_aadhaar'],
            'class_section' => $overrides['existing_class_section'] ?? $existingDefaults['class_section'],
            'mother_name' => $overrides['existing_mother_name'] ?? $existingDefaults['mother_name'],
            'father_name' => $overrides['existing_father_name'] ?? $existingDefaults['father_name'],
        ];

        $pen = $overrides['student_pen'] ?? ($udise?->student_pen ?? '');
        $mobile = $overrides['mobile'] ?? ($student->mobile ?? '');
        $forName = $overrides['for_name'] ?? ($student->name ?? '');

        $attachRegister = filter_var($overrides['attach_school_register'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $attachBirth = filter_var($overrides['attach_birth_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $undertakingSchool = filter_var($overrides['undertaking_school'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $undertakingOfficer = filter_var($overrides['undertaking_officer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $principalName = trim((string) ($overrides['principal_name'] ?? ''));
        if ($principalName === '') {
            $student->loadMissing('branch:id,principal');
            $principalName = trim((string) ($student->branch?->principal ?? ''));
        }
        $principalDesignation = trim((string) ($overrides['principal_designation'] ?? 'PRINCIPAL'));

        $e = fn ($v) => htmlspecialchars((string) ($v === '' || $v === null ? '' : $v), ENT_QUOTES, 'UTF-8');
        $principalNameDisplay = $principalName !== '' ? strtoupper($principalName) : '';
        $principalDesignationDisplay = $principalDesignation !== '' ? strtoupper($principalDesignation) : 'PRINCIPAL';

        $district = $overrides['district'] ?? ($setting->district ?? '');
        $block = $overrides['block'] ?? ($setting->block ?? '');
        $state = $overrides['state'] ?? ($setting->state ?? '');
        $udiseCode = $overrides['udise_code'] ?? ($setting->udise_code ?? '');
        $schoolName = strtoupper((string) ($overrides['school_name'] ?? ($setting->school_name ?? 'SCHOOL')));
        $stateDisplay = strtoupper((string) $state);
        $districtDisplay = strtoupper((string) $district);
        $blockDisplay = strtoupper((string) $block);

        $existingRows = $this->detailRowsHtml('ex', $existing, $e);
        $updatedRows = $this->detailRowsHtml('up', $updated, $e);

        $cb = fn (bool $on) => $on ? '☑' : '☐';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { margin: 15mm 15mm 12mm; size: A4 portrait; }
            * { box-sizing: border-box; }
            body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #000; margin: 0; }
            .title { text-align: center; font-size: 13pt; font-weight: bold; margin: 0; }
            .subtitle { text-align: center; font-size: 11.5pt; font-weight: bold; margin: 3pt 0 2pt; }
            .note { text-align: center; font-size: 7.8pt; font-weight: bold; margin: 0 0 10pt; }
            .sec { font-weight: bold; font-size: 9.5pt; margin: 9pt 0 4pt; }
            .sec.underline { text-decoration: underline; }
            table.meta { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
            table.meta td { border: none; padding: 3pt 2pt 5pt; font-size: 9.3pt; vertical-align: bottom; }
            .hdr-only { font-weight: bold; white-space: nowrap; }
            table.meta td.f-lbl { font-weight: bold; white-space: nowrap; padding-right: 4pt; }
            table.meta td.f-val, table.fields td.f-val {
                border-bottom: 0.6pt solid #000;
                padding-left: 8pt;
                white-space: nowrap;
                overflow: hidden;
            }
            table.cols { width: 100%; border-collapse: separate; border-spacing: 5pt 0; margin-top: 6pt; }
            table.cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; border: none; }
            .box-h { text-align: center; font-style: italic; font-weight: bold; font-size: 9.3pt; margin-bottom: 4pt; }
            .box { border: 0.8pt solid #000; padding: 3pt 8pt; }
            table.fields { width: 100%; border-collapse: collapse; }
            table.fields td { border: none; padding: 5pt 2pt 3pt; font-size: 9.3pt; vertical-align: bottom; }
            table.fields td.f-lbl { width: 130pt; font-weight: bold; white-space: nowrap; }
            /* Two-line / wrapped values stay within the normal single-line row height. */
            table.meta td.f-val.two, table.fields td.f-val.two { line-height: 1.05; padding-top: 1pt; padding-bottom: 1pt; white-space: normal; }
            tr.sz td { padding: 0 !important; height: 0; border: none !important; }
            .checks-row { margin-top: 2pt; }
            .chk-item { display: inline-block; margin-right: 26pt; font-size: 9.3pt; font-weight: bold; }
            .undertaking-body { font-size: 9pt; line-height: 1.3; margin: 3pt 0 0; }
            table.sign { width: 100%; border-collapse: collapse; margin-top: 22pt; }
            table.sign > tbody > tr > td { width: 50%; vertical-align: top; border: none; padding: 0 6pt 0 0; font-size: 9.3pt; }
            .sign-h { font-weight: bold; margin-bottom: 8pt; line-height: 1.3; }
            .sign-h.nowrap { font-size: 8.3pt; white-space: nowrap; }
            table.signfields { width: 100%; border-collapse: collapse; }
            table.signfields td { border: none; padding: 10pt 2pt 2pt; font-size: 9.3pt; vertical-align: bottom; }
            table.signfields .f-lbl { width: 34%; }
            .sig-img { max-height: 30pt; max-width: 80pt; }
            .sig-space { height: 22pt; }
            .footer-version { text-align: right; font-size: 7.5pt; color: #555; margin-top: 10pt; }
        </style></head><body>
            <div class="title">FORM S03/UDISE</div>
            <div class="subtitle">Format to update details of Active Student</div>
            <div class="note">(To be filled by the Current School of the Student &amp; Submitted to Block/District Education Officer or Equivalent)</div>

            <table class="meta">
                '.$this->sizerRow([87, 85, 88, 105, 40, 105]).'
                <tr>
                    <td class="hdr-only" colspan="2">SUBMITTED BY</td>
                    <td class="f-lbl">Academic Year:</td>
                    '.$this->fitTd('academic_year', (string) $academicYear, ' class="f-val" colspan="3"').'
                </tr>
                <tr>
                    <td class="f-lbl">UDISE Code:</td>
                    '.$this->fitTd('udise_code', (string) $udiseCode).'
                    <td class="f-lbl">School Name:</td>
                    '.$this->fitTd('school_name', $schoolName, ' class="f-val" colspan="3"').'
                </tr>
                <tr>
                    <td class="f-lbl">State:</td>
                    '.$this->fitTd('state', $stateDisplay).'
                    <td class="f-lbl">District:</td>
                    '.$this->fitTd('district', $districtDisplay).'
                    <td class="f-lbl">Block:</td>
                    '.$this->fitTd('block', $blockDisplay).'
                </tr>
            </table>

            <div class="sec">FOR</div>
            <table class="meta">
                '.$this->sizerRow([87, 80, 40, 141, 90, 72]).'
                <tr>
                    <td class="f-lbl">Student\'s PEN:</td>
                    '.$this->fitTd('pen', (string) $pen).'
                    <td class="f-lbl">Name:</td>
                    '.$this->fitTd('for_name', (string) $forName).'
                    <td class="f-lbl">Mobile Number:</td>
                    '.$this->fitTd('mobile', (string) $mobile).'
                </tr>
            </table>

            <div class="sec underline">DEMOGRAPHIC DETAILS UPDATE</div>

            <table class="cols"><tr>
                <td>
                    <div class="box">
                        <div class="box-h">Existing Details in UDISE Plus</div>
                        <table class="fields">'.$existingRows.'</table>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <div class="box-h">Details to be Updated in UDISE Plus</div>
                        <table class="fields">'.$updatedRows.'</table>
                    </div>
                </td>
            </tr></table>

            <div class="sec">Document Attached:</div>
            <div class="checks-row">
                <span class="chk-item">'.$cb($attachRegister).' Copy of School Register</span>
                <span class="chk-item">'.$cb($attachBirth).' Copy of Birth Certificate</span>
            </div>

            <div class="sec">Undertaking by School</div>
            <div class="undertaking-body">'.$cb($undertakingSchool).' I hereby declare that the information filled and documents provided are correct to the best of my knowledge and belief.</div>

            <div class="sec">Undertaking by Block/ District level Officer</div>
            <div class="undertaking-body">'.$cb($undertakingOfficer).' I hereby confirm that a copy of required documents for the above-mentioned changes is kept in the office file for record.</div>

            <table class="sign"><tr>
                <td>
                    <div class="sign-h">Head of the School</div>
                    <table class="signfields">
                        <tr><td class="f-lbl">Name:</td>'.$this->fitTd('principal_name', $principalNameDisplay).'</tr>
                        <tr><td class="f-lbl">Designation:</td>'.$this->fitTd('principal_designation', $principalDesignationDisplay).'</tr>
                        <tr><td class="f-lbl">Signature:</td><td>&nbsp;</td></tr>
                        <tr><td class="f-lbl">Seal:</td><td>&nbsp;</td></tr>
                    </table>
                </td>
                <td>
                    <div class="sign-h nowrap">State/District/Block Education Officer or Equivalent</div>
                    <table class="signfields">
                        <tr><td class="f-lbl">Name:</td><td>&nbsp;</td></tr>
                        <tr><td class="f-lbl">Designation:</td><td>&nbsp;</td></tr>
                        <tr><td class="f-lbl">Signature:</td><td>&nbsp;</td></tr>
                        <tr><td class="f-lbl">Seal:</td><td>&nbsp;</td></tr>
                    </table>
                </td>
            </tr></table>

            <div class="footer-version">v.02_09.10.2025</div>
        </body></html>';
    }

    /** @param  array<string, string>  $data */
    private function detailRowsHtml(string $prefix, array $data, callable $e): string
    {
        $fields = [
            'name' => 'Name',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'aadhaar' => 'AADHAAR Number',
            'name_as_per_aadhaar' => 'Name as per AADHAAR',
            'class_section' => 'Class & Section',
            'mother_name' => "Mother's Name",
            'father_name' => "Father's Name",
        ];

        $html = '';
        foreach ($fields as $key => $label) {
            $html .= '<tr><td class="f-lbl">'.$e($label).':</td>'.$this->fitTd($prefix.'_'.$key, (string) ($data[$key] ?? '')).'</tr>';
        }

        return $html;
    }

    private function formatAcademicYear(string $sessionName): string
    {
        if (preg_match('/(\d{4})\s*[-–]\s*(\d{2,4})/', $sessionName, $m)) {
            $end = strlen($m[2]) === 4 ? substr($m[2], -2) : $m[2];

            return $m[1].' - '.$end;
        }

        return $sessionName !== '' ? $sessionName : date('Y').' - '.substr((string) (date('Y') + 1), -2);
    }
}