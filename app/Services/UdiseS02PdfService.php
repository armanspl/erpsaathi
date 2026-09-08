<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * Official FORM S02/UDISE — "Format to ADD Student (only for Class-2 to Class-12)".
 * Landscape A4 with school header, multi-student table, and dual signature blocks.
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
        $optionsPdf->set('defaultFont', 'DejaVu Sans');
        $optionsPdf->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($optionsPdf);
        $dompdf->loadHtml($this->html($students, $options));
        $dompdf->setPaper('a4', 'landscape');
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
        $principalDesignation = trim((string) ($options['principal_designation'] ?? 'Head of the School'));
        $principalImg = ! empty($school['principal_signature_image'])
            ? '<img class="sig-img" src="'.$school['principal_signature_image'].'" alt="" />'
            : '<div class="sig-space"></div>';

        $e = fn ($v) => htmlspecialchars((string) ($v === '' || $v === null ? '' : $v), ENT_QUOTES, 'UTF-8');

        $rowsHtml = '';
        $sno = 1;
        foreach ($students as $student) {
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

            $rowsHtml .= '<tr>'
                .'<td class="sno">'.$sno.'</td>'
                .'<td class="cell">'
                    .'<div class="fline"><span class="lbl">Name:</span> '.$e($student->name).'</div>'
                    .'<div class="fline"><span class="lbl">Gender:</span> '.$e($student->gender).'</div>'
                    .'<div class="fline"><span class="lbl">Date of Birth:</span> '.$e($student->dob ? Carbon::parse($student->dob)->format('d/m/Y') : '').'</div>'
                    .'<div class="fline"><span class="lbl">Is CWSN:</span> '.$e($cwsnLabel).'</div>'
                .'</td>'
                .'<td class="cell">'
                    .'<div class="fline"><span class="lbl">Class:</span> '.$e($student->schoolClass?->name).'</div>'
                    .'<div class="fline"><span class="lbl">Section:</span> '.$e($student->section?->name).'</div>'
                    .'<div class="fline"><span class="lbl">Admission Date:</span> '.$e($student->admission_date ? Carbon::parse($student->admission_date)->format('d/m/Y') : '').'</div>'
                .'</td>'
                .'<td class="cell">'
                    .'<div class="fline"><span class="lbl">Mother\'s Name:</span> '.$e($udise?->mother_name ?: $student->mother?->name).'</div>'
                    .'<div class="fline"><span class="lbl">Father\'s Name:</span> '.$e($udise?->father_name ?: $student->father?->name).'</div>'
                    .'<div class="fline"><span class="lbl">Guardian\'s Name:</span> '.$e($udise?->guardian_name).'</div>'
                .'</td>'
                .'<td class="cell">'
                    .'<div class="fline"><span class="lbl">Mobile number:</span> '.$e($student->mobile).'</div>'
                    .'<div class="fline"><span class="lbl">Alternate number:</span> '.$e($udise?->alternate_mobile).'</div>'
                .'</td>'
                .'<td class="cell">'
                    .'<div class="fline"><span class="lbl">AADHAAR number:</span> '.$e($student->aadhar_no).'</div>'
                    .'<div class="fline"><span class="lbl">Name as per AADHAAR:</span> '.$e($udise?->name_as_per_aadhaar ?: $student->name).'</div>'
                .'</td>'
                .'<td class="cell reason">'.$e($reason).'</td>'
                .'</tr>';
            $sno++;
        }

        // Keep at least 3 blank-looking rows if fewer students (matches paper form look).
        while ($sno <= 3) {
            $rowsHtml .= '<tr>'
                .'<td class="sno">'.$sno.'</td>'
                .'<td class="cell"><div class="fline"><span class="lbl">Name:</span></div><div class="fline"><span class="lbl">Gender:</span></div><div class="fline"><span class="lbl">Date of Birth:</span></div><div class="fline"><span class="lbl">Is CWSN:</span></div></td>'
                .'<td class="cell"><div class="fline"><span class="lbl">Class:</span></div><div class="fline"><span class="lbl">Section:</span></div><div class="fline"><span class="lbl">Admission Date:</span></div></td>'
                .'<td class="cell"><div class="fline"><span class="lbl">Mother\'s Name:</span></div><div class="fline"><span class="lbl">Father\'s Name:</span></div><div class="fline"><span class="lbl">Guardian\'s Name:</span></div></td>'
                .'<td class="cell"><div class="fline"><span class="lbl">Mobile number:</span></div><div class="fline"><span class="lbl">Alternate number:</span></div></td>'
                .'<td class="cell"><div class="fline"><span class="lbl">AADHAAR number:</span></div><div class="fline"><span class="lbl">Name as per AADHAAR:</span></div></td>'
                .'<td class="cell reason"></td>'
                .'</tr>';
            $sno++;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 10mm 10mm 10mm 10mm; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; margin: 0; }
.wrap { border: 1.2pt solid #111; padding: 6px 8px 8px; }
.title { text-align: center; font-size: 13pt; font-weight: bold; letter-spacing: 0.3px; }
.sub { text-align: center; font-size: 9pt; font-weight: bold; margin-top: 2px; }
.note { text-align: center; font-size: 7.5pt; margin: 3px 0 8px; }
.head-grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.head-grid td { padding: 2px 4px; vertical-align: bottom; font-size: 8pt; }
.head-grid .lbl { font-weight: bold; white-space: nowrap; }
.head-grid .val { border-bottom: 0.7pt solid #333; min-width: 80px; padding-left: 4px; }
.students { width: 100%; border-collapse: collapse; table-layout: fixed; }
.students th, .students td { border: 0.8pt solid #111; vertical-align: top; }
.students th { background: #f3f3f3; font-size: 7.2pt; font-weight: bold; text-align: center; padding: 4px 3px; }
.students td.sno { width: 28px; text-align: center; font-weight: bold; padding-top: 6px; }
.students td.cell { padding: 3px 4px; font-size: 7.4pt; }
.students td.reason { min-height: 48px; }
.fline { margin: 0 0 2px; line-height: 1.25; }
.fline .lbl { font-weight: bold; }
.footer { width: 100%; border-collapse: collapse; margin-top: 8px; }
.footer td { width: 50%; border: 0.8pt solid #111; padding: 6px 8px; vertical-align: top; font-size: 8pt; }
.footer .ftitle { font-weight: bold; margin-bottom: 6px; }
.footer .fline { margin-bottom: 5px; }
.sig-space { height: 28px; }
.sig-img { height: 28px; max-width: 120px; }
.ver { text-align: right; font-size: 7pt; color: #444; margin-top: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="title">FORM S02/UDISE</div>
  <div class="sub">Format to ADD Student (only for Class-2 to Class-12)</div>
  <div class="note">(Only for students whose profile was not created/missing in the UDISE+ SDMS)<br>
  (To be submitted to Block/District Education Officer or Equivalent)</div>

  <table class="head-grid">
    <tr>
      <td class="lbl">Submitted By (Current/Present School)</td><td class="val" colspan="3">{$e($header['school_name'])}</td>
      <td class="lbl">Academic Year</td><td class="val">{$e($header['academic_year'])}</td>
    </tr>
    <tr>
      <td class="lbl">UDISE State</td><td class="val">{$e($header['state'])}</td>
      <td class="lbl">District</td><td class="val">{$e($header['district'])}</td>
      <td class="lbl">Block</td><td class="val">{$e($header['block'])}</td>
    </tr>
    <tr>
      <td class="lbl">UDISE Code</td><td class="val">{$e($header['udise_code'])}</td>
      <td class="lbl">School Name</td><td class="val">{$e($header['school_name'])}</td>
      <td class="lbl">School Contact Number</td><td class="val">{$e($header['phone'])}</td>
    </tr>
  </table>

  <table class="students">
    <thead>
      <tr>
        <th style="width:3%">S.No</th>
        <th style="width:18%">Basic Details</th>
        <th style="width:14%">Admission Details</th>
        <th style="width:18%">Parents/Guardian details</th>
        <th style="width:13%">Contact details</th>
        <th style="width:16%">Student's AADHAAR Details</th>
        <th style="width:18%">Why was this student not added in the Previous Academic Year?</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <table class="footer">
    <tr>
      <td>
        <div class="ftitle">Head of the School Details</div>
        <div class="fline">Signature: {$principalImg}</div>
        <div class="fline">Name: {$e($principalName !== '' ? strtoupper($principalName) : '')}</div>
        <div class="fline">Designation: {$e($principalDesignation !== '' ? $principalDesignation : 'Head of the School')}</div>
        <div class="fline">Date: ____________________</div>
        <div class="fline">Seal:</div>
      </td>
      <td>
        <div class="ftitle">State/District/Block level Education Officer or Equivalent Details</div>
        <div class="fline">Signature:</div>
        <div class="fline">Name: ____________________</div>
        <div class="fline">Designation: ____________________</div>
        <div class="fline">Date: ____________________</div>
        <div class="fline">Seal:</div>
      </td>
    </tr>
  </table>
  <div class="ver">v.02_08/10/2025</div>
</div>
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
}
