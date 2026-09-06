<?php

namespace App\Http\Controllers\Erp\Documents;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class CertificateController extends Controller
{
    /** Fields the "Prepare" drawer can override for a single download — nothing here is persisted. */
    private const OVERRIDABLE_FIELDS = [
        'recipient_name', 'father_name', 'mother_name', 'admission_id', 'roll_number', 'class', 'section',
        'branch', 'session_year', 'dob', 'purpose', 'issue_date', 'conduct', 'character', 'nationality',
        'category', 'admission_date', 'pen_no', 'aadhar_no', 'remarks', 'book_no', 'last_exam_result', 'failed_status',
        'subjects_studied', 'promotion_status', 'promoted_class', 'working_days', 'presence_days',
        'fee_paid_upto', 'fee_concession', 'ncc_activities', 'games_activities', 'general_conduct',
        'application_date',
    ];

    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    /** Every field the PDF can show for this student + certificate type, pre-computed — powers the "Prepare" drawer. */
    public function prepareData(CertificateType $certificateType, Student $student)
    {
        $certificate = $this->certificateFor($certificateType, $student);

        return response()->json($this->dataBuilder->certificate($certificateType, $certificate, $student));
    }

    /** Recipient roster for a role. Only "student" has a wired-up roster in this release. */
    public function recipients(Request $request)
    {
        if ($request->input('role') !== 'student') {
            return response()->json([]);
        }

        return response()->json($this->scopedStudents($request)->map(fn (Student $s) => $this->presentStudentRow($s))->values());
    }

    public function downloadStudentPdf(Request $request, CertificateType $certificateType, Student $student): StreamedResponse
    {
        $certificate = $this->certificateFor($certificateType, $student);
        $overrides = $request->only(self::OVERRIDABLE_FIELDS);
        $data = $this->dataBuilder->certificate($certificateType, $certificate, $student, $overrides);

        return $this->renderer->streamPdf('certificate', $data, $this->certificateFilename($certificate), $certificateType->template_id);
    }

    public function downloadZip(Request $request, CertificateType $certificateType): StreamedResponse
    {
        abort_unless($request->input('role') === 'student', 404, 'This role is not supported yet.');

        if ($request->filled('student_ids')) {
            // An explicit selection is honored as-is, regardless of status — the user already
            // chose these students by hand.
            $students = Student::whereIn('id', $request->input('student_ids'))->get();
        } else {
            $students = $this->scopedStudents($request);
        }

        abort_if($students->isEmpty(), 404, 'No recipients found for this selection.');

        $zipPath = tempnam(sys_get_temp_dir(), 'certificates-zip-');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($students as $student) {
            $certificate = $this->certificateFor($certificateType, $student);
            $binary = $this->renderer->pdfBinary(
                'certificate',
                $this->dataBuilder->certificate($certificateType, $certificate, $student),
                $certificateType->template_id
            );
            $zip->addFromString($this->certificateFilename($certificate), $binary);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, "certificates-{$certificateType->id}.zip", ['Content-Type' => 'application/zip']);
    }

    /** Students in scope for the recipient roster — any status, unless the caller filters. */
    private function scopedStudents(?Request $request = null)
    {
        $query = Student::query()->with(['schoolClass:id,name', 'section:id,name']);

        if ($request?->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        AcademicSession::applyStudentSessionFilter($query);

        return $query->orderBy('roll_no')->orderBy('name')->get();
    }

    private function presentStudentRow(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'roll_no' => $student->roll_no,
            'admission_no' => $student->admission_no,
            'name' => $student->name,
            'status' => $student->status,
            'school_class_name' => $student->schoolClass->name ?? null,
            'section_name' => $student->section->name ?? null,
        ];
    }

    /** Issuing is implicit: the first download for a (type, student) pair mints the certificate; later downloads reuse the same certificate_no. */
    private function certificateFor(CertificateType $type, Student $student): Certificate
    {
        $certificate = Certificate::firstOrCreate(
            ['certificate_type_id' => $type->id, 'student_id' => $student->id],
            [
                'certificate_no' => $this->nextCertificateNo($type),
                'type' => $type->label,
                'issue_date' => now()->toDateString(),
                'issued_by_id' => Auth::guard('erp')->id(),
            ]
        );

        $upgraded = $this->upgradeLegacyCertificateNo($certificate);
        if ($upgraded !== null) {
            $certificate->update(['certificate_no' => $upgraded]);
            $certificate->refresh();
        }

        return $certificate;
    }

    /** Convert legacy "TC-2026-0004" to "GAS/TC/2026/0004" when needed. */
    private function upgradeLegacyCertificateNo(Certificate $certificate): ?string
    {
        $no = (string) $certificate->certificate_no;
        if (! preg_match('/^(BON|TC|CC|MC)-(\d{4})-(\d+)$/', $no, $m)) {
            return null;
        }

        $initials = $this->schoolInitials();
        $candidate = sprintf('%s/%s/%s/%04d', $initials, $m[1], $m[2], (int) $m[3]);

        if (Certificate::where('certificate_no', $candidate)->where('id', '!=', $certificate->id)->exists()) {
            return $this->nextCertificateNo(
                CertificateType::query()->findOrFail($certificate->certificate_type_id)
            );
        }

        return $candidate;
    }

    /** e.g. "GAS/TC/2026/0004" — school initials / type prefix / year / next sequence. */
    private function nextCertificateNo(CertificateType $type): string
    {
        $initials = $this->schoolInitials();
        $year = now()->format('Y');
        $prefix = $type->prefix;

        $max = 0;
        Certificate::query()
            ->where(function ($q) use ($initials, $prefix, $year) {
                $q->where('certificate_no', 'like', "{$initials}/{$prefix}/{$year}/%")
                    ->orWhere('certificate_no', 'like', "{$prefix}-{$year}-%");
            })
            ->pluck('certificate_no')
            ->each(function (string $no) use (&$max) {
                if (preg_match('/(\d+)$/', $no, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            });

        return sprintf('%s/%s/%s/%04d', $initials, $prefix, $year, $max + 1);
    }

    /** First letter of every word in the school name, e.g. "Global Access School" -> "GAS". */
    private function schoolInitials(): string
    {
        $name = trim(SchoolSetting::current()->school_name ?? '');
        if ($name === '') {
            return 'SCH';
        }

        $initials = collect(preg_split('/\s+/', $name))
            ->filter()
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'SCH';
    }

    /** Safe download name: "GAS/TC/2026/0004" -> "GAS-TC-2026-0004.pdf" (slashes are illegal in filenames). */
    private function certificateFilename(Certificate $certificate): string
    {
        $safe = preg_replace('/[\/\\\\]+/', '-', (string) $certificate->certificate_no) ?: 'certificate';
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $safe) ?: 'certificate';

        return Str::endsWith(strtolower($safe), '.pdf') ? $safe : "{$safe}.pdf";
    }
}
