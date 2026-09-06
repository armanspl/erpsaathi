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
    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function binary(Student $student, array $overrides = []): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($student, $overrides));
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function streamDownload(Student $student, string $filename, array $overrides = [])
    {
        $binary = $this->binary($student, $overrides);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Shrinks font-size for long values so a single row never breaks the
     * underline/box layout, instead of letting text overflow or wrap.
     */
    private function fitSize(string $text, float $base = 9.3, int $threshold = 16, float $min = 7.2): float
    {
        $len = mb_strlen(trim($text));
        if ($len <= $threshold) {
            return $base;
        }

        $size = $base - ($len - $threshold) * 0.13;

        return max($min, round($size, 1));
    }

    /** Inline style attr (only emitted when shrink is actually needed). */
    private function fitStyle(string $rawText, float $base = 9.3, int $threshold = 16, float $min = 7.2): string
    {
        $size = $this->fitSize($rawText, $base, $threshold, $min);

        return $size < $base ? ' style="font-size:'.$size.'pt"' : '';
    }

    /**
     * Breaks long text into two lines at the nearest word boundary near $len,
     * instead of shrinking font size indefinitely. Falls back to a hard break
     * if no suitable space is found.
     */
    private function wrapTwoLines(string $text, int $len = 16): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= $len) {
            return [$text];
        }

        $window = mb_substr($text, 0, $len + 1);
        $breakAt = mb_strrpos($window, ' ');

        // agar koi accha space nahi mila (ya bahut shuru mein hai), hard break kar do
        if ($breakAt === false || $breakAt < (int) ($len * 0.5)) {
            $breakAt = $len;
        }

        $first = trim(mb_substr($text, 0, $breakAt));
        $second = trim(mb_substr($text, $breakAt));

        return [$first, $second];
    }

    /**
     * Builds the value HTML + inline style for a field that should wrap to
     * two lines instead of shrinking when it exceeds $threshold characters.
     *
     * @return array{0: string, 1: string} [html, styleAttr]
     */
    private function wrapOrFit(callable $e, string $raw, int $threshold = 24, int $wrapLen = 16, float $base = 9.3, int $fitThreshold = 20): array
    {
        $raw = trim($raw);
        $len = mb_strlen($raw);

        if ($len > $threshold) {
            $html = implode('<br>', array_map($e, $this->wrapTwoLines($raw, $wrapLen)));
            $style = ' style="font-size:8pt; white-space:normal; overflow:visible; line-height:1.15;"';

            return [$html, $style];
        }

        return [$e($raw), $this->fitStyle($raw, $base, $fitThreshold)];
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

        $principal = $setting->findSignature('Principal');
        $principalName = trim((string) ($overrides['principal_name'] ?? ''));
        $principalDesignation = trim((string) ($overrides['principal_designation'] ?? 'PRINCIPAL'));
        $principalImg = ! empty($school['principal_signature_image'])
            ? '<img class="sig-img" src="'.$school['principal_signature_image'].'" alt="" />'
            : '<div class="sig-space"></div>';

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

        $existingRows = $this->detailRowsHtml($existing, $e);
        $updatedRows = $this->detailRowsHtml($updated, $e);

        // FOR strip Name: wraps to 2 lines instead of shrinking, same as the detail rows.
        [$forNameHtml, $forNameStyle] = $this->wrapOrFit($e, (string) $forName, 24, 16, 9.3, 12);

        $cb = fn (bool $on) => $on ? '☑' : '☐';
        $nb = fn (string $v) => $v !== '' ? $v : '&nbsp;';

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
            table.fields td.f-lbl { width: 40%; font-weight: bold; white-space: nowrap; }
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
                <colgroup>
                    <col style="width:17%"><col style="width:16%"><col style="width:15%">
                    <col style="width:15%"><col style="width:15%"><col style="width:22%">
                </colgroup>
                <tr>
                    <td class="hdr-only" colspan="2">SUBMITTED BY</td>
                    <td class="f-lbl">Academic Year:</td>
                    <td class="f-val" colspan="3"'.$this->fitStyle((string) $academicYear).'>'.$nb($e($academicYear)).'</td>
                </tr>
                <tr>
                    <td class="f-lbl">UDISE Code:</td>
                    <td class="f-val"'.$this->fitStyle((string) $udiseCode).'>'.$nb($e($udiseCode)).'</td>
                    <td class="f-lbl">School Name:</td>
                    <td class="f-val" colspan="3"'.$this->fitStyle((string) $schoolName, 9.3, 22).'>'.$nb($e($schoolName)).'</td>
                </tr>
                <tr>
                    <td class="f-lbl">State:</td>
                    <td class="f-val"'.$this->fitStyle($stateDisplay, 9.3, 12).'>'.$nb($e($stateDisplay)).'</td>
                    <td class="f-lbl">District:</td>
                    <td class="f-val"'.$this->fitStyle($districtDisplay, 9.3, 12).'>'.$nb($e($districtDisplay)).'</td>
                    <td class="f-lbl">Block:</td>
                    <td class="f-val"'.$this->fitStyle($blockDisplay, 9.3, 10).'>'.$nb($e($blockDisplay)).'</td>
                </tr>
            </table>

            <div class="sec">FOR</div>
            <table class="meta">
                <colgroup>
                    <col style="width:17%"><col style="width:16%"><col style="width:15%">
                    <col style="width:15%"><col style="width:15%"><col style="width:22%">
                </colgroup>
                <tr>
                    <td class="f-lbl">Student\'s PEN:</td>
                    <td class="f-val"'.$this->fitStyle((string) $pen).'>'.$nb($e($pen)).'</td>
                    <td class="f-lbl">Name:</td>
                    <td class="f-val"'.$forNameStyle.'>'.($forNameHtml !== '' ? $forNameHtml : '&nbsp;').'</td>
                    <td class="f-lbl">Mobile Number:</td>
                    <td class="f-val"'.$this->fitStyle((string) $mobile).'>'.$nb($e($mobile)).'</td>
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
                        <tr><td class="f-lbl">Name:</td><td class="f-val">'.$nb($e($principalNameDisplay)).'</td></tr>
                        <tr><td class="f-lbl">Designation:</td><td class="f-val">'.$nb($e($principalDesignationDisplay)).'</td></tr>
                        <tr><td class="f-lbl">Signature:</td><td style="padding:2pt;">'.$principalImg.'</td></tr>
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
    private function detailRowsHtml(array $data, callable $e): string
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
            $raw = (string) ($data[$key] ?? '');
            [$v, $style] = $this->wrapOrFit($e, $raw, 24, 16, 9.3, 20);
            $html .= '<tr><td class="f-lbl">'.$e($label).':</td><td class="f-val"'.$style.'>'.($v !== '' ? $v : '&nbsp;').'</td></tr>';
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