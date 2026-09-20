<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * Official FORM S02/UDISE+ — "Format to ADD a Student (only for Class-2 to Class-12)",
 * v.04_17/08/2026 layout supplied by the client. Portrait A4, one full page per student
 * (Student Details grid, document checklist, undertaking paragraph, and a three-way
 * signature block for Head of School / Block Education Officer / District Education Officer).
 */
class UdiseS02PdfService
{
    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    /**
     * @param  Collection<int, Student>|array<int, Student>  $students
     * @param  array<string, mixed>  $options  header overrides + reasons[student_id => string]
     */
    public function binary($students, array $options = []): string
    {
        $optionsPdf = new Options();
        $optionsPdf->set('isRemoteEnabled', true);
        // Helvetica (a core PDF font, no embedding) is narrower than DejaVu Sans — needed so the
        // long "Undertaking by..." heading fits the client's reference on a single line.
        $optionsPdf->set('defaultFont', 'Helvetica');
        $optionsPdf->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($optionsPdf);
        $dompdf->loadHtml($this->html($students, $options));
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  Collection<int, Student>|array<int, Student>  $students
     * @param  array<string, mixed>  $options
     */
    public function streamDownload($students, string $filename, array $options = [])
    {
        $binary = $this->binary($students, $options);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  Collection<int, Student>|array<int, Student>  $students
     * @param  array<string, mixed>  $options
     */
    public function html($students, array $options = []): string
    {
        $students = collect($students);
        $school = $this->dataBuilder->schoolContext();
        $setting = SchoolSetting::current();
        $session = AcademicSession::fromRequest(request(), true);
        $sessionName = $session?->name ?: ($school['session_year'] ?? '');
        $academicYear = $this->formatAcademicYear($sessionName);

        $header = [
            'academic_year' => $options['academic_year'] ?? $academicYear,
            'state' => $options['state'] ?? ($setting->state ?? ''),
            'district' => $options['district'] ?? ($setting->district ?? ''),
            'block' => $options['block'] ?? ($setting->block ?? ''),
            'udise_code' => $options['udise_code'] ?? ($setting->udise_code ?? ''),
            'school_name' => $options['school_name'] ?? ($setting->school_name ?? ($school['school_name'] ?? '')),
            'phone' => $options['phone'] ?? ($setting->phone ?? ($school['school_phone'] ?? '')),
        ];

        $reasons = is_array($options['reasons'] ?? null) ? $options['reasons'] : [];
        $principalName = trim((string) ($options['principal_name'] ?? ''));
        if ($principalName === '') {
            $principalName = $this->resolveBranchPrincipal($students, $options['branch_id'] ?? null);
        }
        $principalDesignation = trim((string) ($options['principal_designation'] ?? 'Head of the School'));

        $e = fn ($v) => htmlspecialchars((string) ($v === '' || $v === null ? '' : $v), ENT_QUOTES, 'UTF-8');
        $fill = fn ($v) => '<span class="fill">'.$e($v).'</span>';

        $pagesHtml = '';
        $total = $students->count();
        $index = 0;
        foreach ($students as $student) {
            $index++;
            $student->loadMissing([
                'schoolClass:id,name',
                'section:id,name',
                'father:id,name',
                'mother:id,name',
                'udiseDetail',
            ]);
            $udise = $student->udiseDetail;
            $cwsn = $udise?->cwsn;
            $cwsnLabel = $cwsn === null ? '' : (filter_var($cwsn, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No');
            $reason = $reasons[(string) $student->id] ?? ($reasons[$student->id] ?? '');
            $pageStyle = $index < $total ? ' style="page-break-after: always;"' : '';

            $pagesHtml .= '<div class="page"'.$pageStyle.'>'
                .'<div class="titleWrap"><span class="title">FORM S02/UDISE+</span></div>'
                .'<div class="sub">FORMAT TO ADD A STUDENT (ONLY FOR CLASS-2 TO CLASS-12)</div>'
                .'<div class="sub2">for a student whose profile was not yet created/missing in the UDISE+ SDMS</div>'
                .'<div class="note">(For School records and submission to the Block/District Education Officer or Equivalent authority)</div>'

                .'<table class="hrow"><tr>'
                    .'<td class="lblu">SUBMITTED BY</td>'
                    .'<td class="right">Current Academic Year: <span class="fill" style="min-width:130px">'.$e($header['academic_year']).'</span></td>'
                .'</tr></table>'

                .'<div class="line">UDISE Code: <span class="fill" style="min-width:110px">'.$e($header['udise_code']).'</span>'
                .'&nbsp;&nbsp;&nbsp;&nbsp;School Name: <span class="fill" style="min-width:260px">'.$e($header['school_name']).'</span></div>'
                .'<div class="line">Head of the School (HoS) Contact Details: <span class="fill" style="min-width:380px">'.$e($header['phone']).'</span></div>'
                .'<div class="line">State: <span class="fill" style="min-width:110px">'.$e($header['state']).'</span>'
                .'&nbsp;&nbsp;&nbsp;&nbsp;District: <span class="fill" style="min-width:110px">'.$e($header['district']).'</span>'
                .'&nbsp;&nbsp;&nbsp;&nbsp;Block: <span class="fill" style="min-width:140px">'.$e($header['block']).'</span></div>'

                .'<div class="secTitle">Student Details</div>'

                .'<table class="grid">'
                    .'<colgroup><col style="width:15%"><col style="width:19%"><col style="width:15%"><col style="width:15%"><col style="width:15%"><col style="width:21%"></colgroup>'
                    .'<tr><td class="k">Name</td><td class="v" colspan="5">'.$e($student->name).'</td></tr>'
                    .'<tr>'
                        .'<td class="k">Gender</td><td class="v">'.$e($student->gender).'</td>'
                        .'<td class="k">Date of Birth</td><td class="v">'.$e($student->dob ? Carbon::parse($student->dob)->format('d/m/Y') : '').'</td>'
                        .'<td class="k">Is CWSN?</td><td class="v">'.$e($cwsnLabel).'</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td class="k">Class</td><td class="v">'.$e($student->schoolClass?->name).'</td>'
                        .'<td class="k">Section</td><td class="v">'.$e($student->section?->name).'</td>'
                        .'<td class="k">Admission Date</td><td class="v">'.$e($student->admission_date ? Carbon::parse($student->admission_date)->format('d/m/Y') : '').'</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td class="k">Father\'s Name</td><td class="v" colspan="2">'.$e($udise?->father_name ?: $student->father?->name).'</td>'
                        .'<td class="k">Mother\'s Name</td><td class="v" colspan="2">'.$e($udise?->mother_name ?: $student->mother?->name).'</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td class="k">Mobile Number</td><td class="v" colspan="2">'.$e($student->mobile).'</td>'
                        .'<td class="k">Guardian\'s Name<br>(if applicable)</td><td class="v" colspan="2">'.$e($udise?->guardian_name).'</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td class="k">AADHAAR<br>Number</td><td class="v" colspan="2">'.$e($student->aadhar_no).'</td>'
                        .'<td class="k">Name as per<br>AADHAAR</td><td class="v" colspan="2">'.$e($udise?->name_as_per_aadhaar ?: $student->name).'</td>'
                    .'</tr>'
                .'</table>'

                .'<div class="line">Reason of non-creation of Student profile in UDISE+ till date: <span class="fill" style="min-width:260px">'.$e($reason).'</span></div>'

                .'<div class="italicNote">(1-Student Rejoining After Dropping Out, 2-Student Admitted from Unrecognized/Open School, 3-Profile Not Created by Current '
                .'School in Previous Year, 4-Profile Not Created by Previous School in Previous Year, 5-Student from Other Country)</div>'

                .'<div class="secTitle2">Tick Document(s) Attached:</div>'

                .'<div class="chk"><span class="box"></span> Copy of School Register/ Admission Register attested by HoS (for Verification of Enrolment, Student\'s Name, Gender and Date of '
                .'Birth as per School Record) &ndash; <b><i>MANDATORY DOCUMENT</i></b></div>'
                .'<div class="chk"><span class="box"></span> Copy of Birth Certificate attested by HoS (for Date of Birth)</div>'
                .'<div class="chk"><span class="box"></span> Copy of Transfer Certificate/School Leaving Certificate (if applicable)</div>'

                .'<div class="secTitle undertakingTitle">Undertaking by Head of the School (HoS)/Block Level Education Officer or Equivalent/District level Education officer or Equivalent</div>'

                .'<div class="para">I hereby undertake that the information provided for creation of the student profile is correct and verified. I confirm that all '
                .'applicable supporting documents, including School Records, Birth Certificate, and Transfer Certificate (where applicable) have '
                .'been duly verified and attached. I further confirm that the student has been searched in the Global Search module of UDISE+ '
                .'Student Module and no existing profile was found.</div>'
                .'<div class="para">I shall be responsible for the correctness and authenticity of the information and documents submitted.</div>'

                .'<table class="sign3">'
                    .'<tr>'
                        .'<td class="colH">Head of the School Details</td>'
                        .'<td class="colH">Block Education Officer of Equivalent</td>'
                        .'<td class="colH">District Education Officer or Equivalent</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td class="chk"><span class="box"></span> I have read and agree to the above undertaking</td>'
                        .'<td class="chk"><span class="box"></span> I have read and agree to the above undertaking</td>'
                        .'<td class="chk"><span class="box"></span> I have read and agree to the above undertaking</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td>Signature:</td><td>Signature:</td><td>Signature:</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td>Name: '.$e($principalName !== '' ? strtoupper($principalName) : '').'</td><td>Name:</td><td>Name:</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td>Designation: '.$e($principalDesignation !== '' ? $principalDesignation : 'Head of the School').'</td><td>Designation:</td><td>Designation:</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td>Date:</td><td>Date:</td><td>Date:</td>'
                    .'</tr>'
                    .'<tr>'
                        .'<td>Seal:</td><td>Seal:</td><td>Seal:</td>'
                    .'</tr>'
                .'</table>'

                .'<div class="ver">v.04_17/08/2026</div>'
                .'</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 11mm 13mm; }
* { box-sizing: border-box; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; margin: 0; line-height: 1.22; }
.page { width: 100%; }
.titleWrap { text-align: center; margin: 0 0 8px; }
.title { display: inline-block; font-size: 13.5pt; font-weight: bold; padding-bottom: 6px; border-bottom: 1pt solid #000; }
.sub { text-align: center; font-size: 10.2pt; font-weight: bold; margin: 3px 0; }
.sub2 { text-align: center; font-size: 9.2pt; font-weight: bold; font-style: italic; text-decoration: underline; margin: 3px 0; }
.note { text-align: center; font-size: 8.3pt; font-style: italic; margin: 3px 0 10px; }
.hrow { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.hrow .lblu { font-weight: bold; text-decoration: underline; }
.hrow .right { text-align: right; }
.line { margin: 6px 0; }
.fill { display: inline-block; border-bottom: 0.7pt solid #000; min-width: 90px; padding: 0 4px; }
.secTitle { font-weight: bold; text-decoration: underline; margin: 12px 0 6px; }
.secTitle2 { font-weight: bold; font-style: italic; text-decoration: underline; margin: 10px 0 6px; }
.grid { width: 100%; border-collapse: collapse; margin: 8px 0 10px; table-layout: fixed; }
.grid td { border: 0.8pt solid #111; padding: 5px 7px; font-size: 8.8pt; vertical-align: top; overflow: hidden; }
.grid td.k { font-weight: normal; }
.italicNote { font-size: 7.8pt; font-style: italic; margin: 4px 0 8px; }
.chk { margin: 5px 0; }
.chk .box { display: inline-block; width: 8pt; height: 8pt; border: 0.9pt solid #111; margin-right: 6px; vertical-align: middle; }
.para { margin: 6px 0; line-height: 1.3; text-align: justify; }
.sign3 { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
.sign3 td { width: 33.33%; padding: 4px 10px 4px 0; vertical-align: top; font-size: 8.8pt; }
.sign3 td.colH { font-weight: bold; text-decoration: underline; padding-bottom: 5px; }
.sign3 tr td { padding-top: 7px; }
.sign3 tr:nth-child(2) td { padding-top: 2px; }
.ver { text-align: right; font-size: 7.6pt; color: #333; margin-top: 8px; }
</style>
</head>
<body>
{$pagesHtml}
</body>
</html>
HTML;
    }

    private function formatAcademicYear(string $sessionName): string
    {
        $sessionName = trim($sessionName);
        if (preg_match('/^(\d{4})\s*-\s*(\d{2,4})$/', $sessionName, $m)) {
            $end = strlen($m[2]) === 4 ? substr($m[2], -2) : $m[2];

            return $m[1].' - '.$end;
        }

        return $sessionName;
    }

    /**
     * Prefer Branches → Principal for Head of the School Name on FORM S02.
     *
     * @param  Collection<int, Student>|iterable<int, Student>  $students
     */
    private function resolveBranchPrincipal($students, mixed $branchId = null): string
    {
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : 0;
        if ($branchId > 0) {
            $name = trim((string) (Branch::query()->where('id', $branchId)->value('principal') ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $collection = $students instanceof Collection ? $students : collect($students);
        $first = $collection->first();
        if (! $first instanceof Student) {
            return '';
        }
        $first->loadMissing('branch:id,principal');

        return trim((string) ($first->branch?->principal ?? ''));
    }
}
