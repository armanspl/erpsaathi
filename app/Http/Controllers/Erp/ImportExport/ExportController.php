<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\FuelLog;
use App\Models\ImportExportLog;
use App\Models\Mark;
use App\Models\SalarySlip;
use App\Models\Student;
use App\Models\StudentSessionHistory;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportController extends Controller
{
    private const ENTITIES = ['student', 'student-udise', 'fee', 'expense', 'salary', 'bank', 'fuel-log', 'route', 'marks', 'attendance', 'global'];

    /** Header/tab accent per sheet — gives the multi-sheet Global Workbook a distinct color per data type. */
    private const SHEET_ACCENTS = [
        'student' => '4F46E5',
        'student-udise' => '0F766E',
        'fee' => '059669',
        'expense' => 'DC2626',
        'salary' => '7C3AED',
        'bank' => '2563EB',
        'fuel-log' => 'EA580C',
        'route' => '0D9488',
        'marks' => 'D97706',
        'attendance' => '0891B2',
    ];

    /**
     * Student UDISE export columns (display headers) → Master Rec field keys used to pull values.
     * Order here is the default export order.
     */
    private const STUDENT_UDISE_EXPORT_COLUMNS = [
        'Student Name' => 'Name',
        'Gender' => 'Gender',
        'Date of Birth' => 'DOB',
        'Class & Section' => 'Class & Section',
        'Admission Date in Present School' => 'ADM DATE',
        "Mother's Name" => 'Mother Name',
        "Father's Name" => 'Father Name',
        'Mobile Number' => 'MOBILE',
        'AADHAAR Number of Student' => 'AADHAAR No.',
        "Student's Name (as Per Record)" => 'Name As per AADHAAR',
    ];

    /** Header text -> logical section, for the Student sheet's color-coded header groups. */
    private const STUDENT_COLUMN_GROUPS = [
        'SESSION' => 'identity', 'Adm No.' => 'identity', 'Student Type' => 'identity', 'AADHAAR No.' => 'identity',
        'Name' => 'personal', 'Mother Name' => 'personal', 'Father Name' => 'personal', 'Guardian Name (Optional)' => 'personal',
        'ADDRESS' => 'contact', 'MOBILE' => 'contact', 'ALTERNATE MOBILE NUMBER (Optional)' => 'contact',
        'EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional)' => 'contact', 'PINCODE' => 'contact',
        'Class' => 'academic', 'Section' => 'academic', 'ROLL' => 'academic', 'DOB' => 'academic', 'Gender' => 'academic',
        'Bld Grp' => 'academic', 'ADM DATE' => 'academic', 'Class Admitted' => 'academic', 'Social Category' => 'academic',
        'Stoppage' => 'transport', 'Vehicle' => 'transport', 'Adm Type' => 'transport', 'Transport' => 'transport', 'Hostel' => 'transport',
        'Minority Group' => 'compliance', 'BPL beneficiary' => 'compliance', 'BELONGS TO EWS/DISADVANTAGED GROUP?' => 'compliance',
        'CWSN' => 'compliance', 'clsl' => 'compliance', 'Name As per AADHAAR' => 'compliance', 'CHILD IS INDIAN NATIONAL?' => 'compliance',
        'MOTHER TONGUE' => 'compliance', 'Student State Code' => 'compliance',
        'Whether Antyodaya Anna Yojana (AAY) beneficiary?' => 'compliance', 'Type of Impairments' => 'compliance',
        'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS' => 'previous_year', 'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR' => 'previous_year',
        'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)' => 'previous_year', 'Is Repeater' => 'previous_year',
        'APPEARED FOR EXAM IN PREVIOUS CLASS' => 'previous_year', 'RESULT FOR PREVIOUS EXAM' => 'previous_year',
        'MARKS % OF PREVIOUS EXAM' => 'previous_year', 'CLASS ATTENDED DAYS (PREVIOUS YEAR)' => 'previous_year', 'C%' => 'previous_year',
        'Status' => 'status', 'Student PEN' => 'status', 'TC Number' => 'status', 'TC Date' => 'status',
        'Last Class Studied' => 'status', 'STATUS' => 'status', 'UDISE' => 'status', 'Entry Status' => 'status',
    ];

    /** Section -> header fill color for the Student sheet. */
    private const STUDENT_GROUP_ACCENTS = [
        'identity' => '334155',
        'personal' => '4F46E5',
        'contact' => '2563EB',
        'academic' => '059669',
        'transport' => 'EA580C',
        'compliance' => '7C3AED',
        'previous_year' => 'B45309',
        'status' => '0D9488',
    ];

    /** Header text -> column width (character units), art-directed per field so nothing is cut off or over-wide. */
    private const STUDENT_COLUMN_WIDTHS = [
        'SESSION' => 11, 'Adm No.' => 10, 'Student Type' => 13, 'AADHAAR No.' => 15,
        'Name' => 22, 'Mother Name' => 20, 'Father Name' => 20, 'Guardian Name (Optional)' => 20,
        'ADDRESS' => 34, 'MOBILE' => 13, 'ALTERNATE MOBILE NUMBER (Optional)' => 16,
        'EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional)' => 30, 'PINCODE' => 10,
        'Class' => 9, 'Section' => 9, 'ROLL' => 7, 'DOB' => 12, 'Gender' => 9,
        'Bld Grp' => 9, 'ADM DATE' => 12, 'Class Admitted' => 14, 'Social Category' => 15,
        'Stoppage' => 16, 'Vehicle' => 12, 'Adm Type' => 10, 'Transport' => 11, 'Hostel' => 9,
        'Minority Group' => 15, 'BPL beneficiary' => 11, 'BELONGS TO EWS/DISADVANTAGED GROUP?' => 16,
        'CWSN' => 9, 'clsl' => 9, 'Name As per AADHAAR' => 20, 'CHILD IS INDIAN NATIONAL?' => 14,
        'MOTHER TONGUE' => 14, 'Student State Code' => 12,
        'Whether Antyodaya Anna Yojana (AAY) beneficiary?' => 16, 'Type of Impairments' => 16,
        'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS' => 16, 'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR' => 16,
        'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)' => 18, 'Is Repeater' => 10,
        'APPEARED FOR EXAM IN PREVIOUS CLASS' => 14, 'RESULT FOR PREVIOUS EXAM' => 14,
        'MARKS % OF PREVIOUS EXAM' => 12, 'CLASS ATTENDED DAYS (PREVIOUS YEAR)' => 14, 'C%' => 8,
        'Status' => 10, 'Student PEN' => 15, 'TC Number' => 16, 'TC Date' => 12,
        'Last Class Studied' => 16, 'STATUS' => 12, 'UDISE' => 12, 'Entry Status' => 12,
    ];

    /** Free-text fields long enough to need left-alignment + wrapping instead of the default centered short values. */
    private const STUDENT_WRAP_HEADERS = [
        'Name', 'Mother Name', 'Father Name', 'Guardian Name (Optional)',
        'ADDRESS', 'Name As per AADHAAR', 'EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional)',
    ];

    /**
     * Exact Master-Import headers (minus Age / P Due). Lowercased+trimmed they must match
     * StudentMasterImportController::HEADER_MAP keys; the two "Status"/"STATUS" columns are
     * disambiguated positionally on re-import (Active/Inactive first, promotion second).
     */
    private const STUDENT_MASTER_HEADERS = [
        'SESSION',
        'Adm No.',
        'Student Type',
        'AADHAAR No.',
        'Name',
        'Mother Name',
        'Father Name',
        'ADDRESS',
        'MOBILE',
        'Class',
        'Section',
        'ROLL',
        'DOB',
        'Gender',
        'Bld Grp',
        'ADM DATE',
        'Class Admitted',
        'Stoppage',
        'Vehicle',
        'Adm Type',
        'Transport',
        'Social Category',
        'Hostel',
        'Minority Group',
        'BPL beneficiary',
        'BELONGS TO EWS/DISADVANTAGED GROUP?',
        'CWSN',
        'clsl',
        'Name As per AADHAAR',
        'CHILD IS INDIAN NATIONAL?',
        'Guardian Name (Optional)',
        'ALTERNATE MOBILE NUMBER (Optional)',
        'EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional)',
        'MOTHER TONGUE',
        'PINCODE',
        'Student State Code',
        'Whether Antyodaya Anna Yojana (AAY) beneficiary?',
        'Type of Impairments',
        'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS',
        'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR',
        'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)',
        'Is Repeater',
        'APPEARED FOR EXAM IN PREVIOUS CLASS',
        'RESULT FOR PREVIOUS EXAM',
        'MARKS % OF PREVIOUS EXAM',
        'CLASS ATTENDED DAYS (PREVIOUS YEAR)',
        'C%',
        'Status',
        'Student PEN',
        'TC Number',
        'TC Date',
        'Last Class Studied',
        'STATUS',
        'UDISE',
        // Extended UDISE / Student Master profile columns (export-only if blank in records)
        'Class & Section',
        'Aadhaar Status',
        'Class/Section Roll No',
        'Is Child Identified as Out of School-Child',
        'When the Child is mainstreamed',
        'Whether having Disability Certificate?',
        'Disability Percentage',
        'Medium of Instruction',
        'Languages Group Studied',
        'Academic Stream opted',
        'Subjects Group Studied',
        'Amount Claimed from Government for RTE',
        'Whether Facilities provided to Student',
        'Facilities provided in case of CWSN',
        'Appeared in State/National Competitions/Olympiads',
        'NCC',
        'NSS',
        'Scouts and Guides',
        "Student's Height (in CMs)",
        "Student's Weight (in KGs)",
        'Approximate Distance of residence to school',
        'Completed Highest Education Level of Parents',
    ];

    public function download(Request $request, string $entity)
    {
        if (! in_array($entity, self::ENTITIES, true)) {
            throw new NotFoundHttpException("Unknown export entity [{$entity}].");
        }

        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            $format = 'xlsx';
        }
        // Multi-sheet global workbook only makes sense as Excel.
        if ($entity === 'global' && $format !== 'xlsx') {
            $format = 'xlsx';
        }

        $sections = $entity === 'global'
            ? collect(array_diff(self::ENTITIES, ['global', 'student-udise']))->map(fn ($e) => [$e, $this->buildSection($e, $request)])
            : collect([[$entity, $this->buildSection($entity, $request)]]);

        $rowCount = $sections->sum(fn ($pair) => count($pair[1][1]));
        $basename = ($entity === 'global' ? 'global-workbook' : $entity) . '-export-' . now()->format('Ymd-His');
        $filename = $basename . '.' . $format;

        ImportExportLog::create([
            'direction' => 'Export',
            'entity' => $entity,
            'filename' => $filename,
            'total_rows' => $rowCount,
            'success_count' => $rowCount,
            'failed_count' => 0,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        if ($format === 'pdf') {
            [$header, $rows] = $sections->first()[1];
            // Full Master Rec (50 cols × 1.5k rows) OOMs Dompdf — PDF gets a readable key-column summary.
            // Excel/CSV remain the round-trip-complete formats.
            [$header, $rows] = $this->pdfSummarySlice($header, $rows);

            return $this->streamPdf($header, $rows, $filename, self::sheetTitle($entity));
        }

        if ($format === 'csv') {
            [$header, $rows] = $sections->first()[1];

            return $this->streamCsv($header, $rows, $filename);
        }

        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        // Global workbook always opens on a Summary sheet (counts + money totals).
        if ($entity === 'global') {
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Summary');
            $this->writeSummarySheet($summarySheet, $sections, $this->sessionLabel($request));
            $this->styleDataSheet($summarySheet, ['Sheet', 'Rows', 'Key total', 'Notes'], count($sections) + 4, '1F2937');
            $sheetIndex = 1;
        }

        foreach ($sections as [$name, $section]) {
            [$header, $rows] = $section;

            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(self::sheetTitle($name));
            $sheet->fromArray($header, null, 'A1');
            if ($rows) {
                $sheet->fromArray($rows, null, 'A2');
            }
            $this->styleDataSheet($sheet, $header, count($rows), self::SHEET_ACCENTS[$name] ?? '4F46E5', $name === 'student');
            $sheetIndex++;
        }
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Professional look for one exported sheet: a colored header band with white bold text, a
     * frozen + auto-filterable header row, thin borders, comfortable row height, and light zebra
     * striping on data rows. Zebra striping is skipped past 3000 rows to stay fast.
     *
     * When $richLayout is true (the Student sheet), headers are additionally matched against
     * STUDENT_COLUMN_GROUPS/WIDTHS/WRAP_HEADERS for art-directed per-column width, a color-coded
     * header per logical section (Identity/Personal/Contact/Academic/...), and left-aligned +
     * wrapped text on long free-text columns (names, address, email) instead of the default
     * centered short-value look. Other sheets keep the simpler generic styling + autosize.
     *
     * @param  array<int, string>  $headers
     */
    private function styleDataSheet(Worksheet $sheet, array $headers, int $rowCount, string $accentHex, bool $richLayout = false): void
    {
        $colCount = count($headers);
        if ($colCount < 1) {
            return;
        }

        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $lastRow = $rowCount + 1;

        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => $richLayout ? 10 : 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accentHex]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => $richLayout ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT,
                'wrapText' => $richLayout,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $accentHex]]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight($richLayout ? 32 : 22);
        $sheet->freezePane('B2');
        $sheet->setAutoFilter($headerRange);
        $sheet->getTabColor()->setRGB($accentHex);

        foreach ($headers as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $width = $richLayout ? (self::STUDENT_COLUMN_WIDTHS[$label] ?? 14) : null;

            if ($width !== null) {
                $sheet->getColumnDimension($col)->setWidth($width);
            } elseif ($colCount <= 15) {
                // Autosize is expensive on wide sheets — only worth it for narrow, non-art-directed ones.
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            if ($richLayout && isset(self::STUDENT_COLUMN_GROUPS[$label])) {
                $groupHex = self::STUDENT_GROUP_ACCENTS[self::STUDENT_COLUMN_GROUPS[$label]];
                $headerCell = $sheet->getStyle("{$col}1");
                $headerCell->getFill()->getStartColor()->setRGB($groupHex);
                $headerCell->getBorders()->getAllBorders()->getColor()->setRGB($groupHex);
            }
        }

        if ($rowCount < 1) {
            return;
        }

        $dataRange = "A2:{$lastCol}{$lastRow}";
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '1F2937']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => $richLayout ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_GENERAL,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight($richLayout ? 20 : 18);
        }

        if ($richLayout) {
            foreach ($headers as $i => $label) {
                if (! in_array($label, self::STUDENT_WRAP_HEADERS, true)) {
                    continue;
                }
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
            }
        }

        if ($rowCount <= 3000) {
            for ($row = 3; $row <= $lastRow; $row += 2) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }
        }
    }

    /**
     * Narrow a Master Rec export to columns that fit a printable PDF without exhausting memory.
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function pdfSummarySlice(array $header, array $rows): array
    {
        $keep = [
            'SESSION', 'Adm No.', 'Name', 'Class', 'Section', 'ROLL',
            'Gender', 'DOB', 'MOBILE', 'Father Name', 'Mother Name',
            'Status', 'Student PEN', 'AADHAAR No.',
        ];

        $indexes = [];
        foreach ($keep as $label) {
            $i = array_search($label, $header, true);
            if ($i !== false) {
                $indexes[] = $i;
            }
        }

        if ($indexes === []) {
            return [$header, $rows];
        }

        $slicedHeader = array_map(fn ($i) => $header[$i], $indexes);
        $slicedRows = array_map(
            fn (array $row) => array_map(fn ($i) => $row[$i] ?? '', $indexes),
            $rows
        );

        return [$slicedHeader, $slicedRows];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamCsv(array $header, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Indian names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamPdf(array $header, array $rows, string $filename, string $title): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $title) {
            $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $thead = '<tr>' . collect($header)->map(fn ($h) => '<th>' . $escape($h) . '</th>')->implode('') . '</tr>';
            $tbody = '';
            foreach ($rows as $row) {
                $tbody .= '<tr>';
                foreach ($row as $cell) {
                    $tbody .= '<td>' . $escape($cell) . '</td>';
                }
                $tbody .= '</tr>';
            }

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                @page { margin: 8mm; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 5pt; color: #111; }
                h1 { font-size: 10pt; margin: 0 0 6pt; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 0.3pt solid #999; padding: 1pt 1.5pt; vertical-align: top; }
                th { background: #eee; font-weight: bold; }
            </style></head><body>
                <h1>' . $escape($title) . ' Export</h1>
                <table><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table>
            </body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            // Wide student sheets need headroom Dompdf's style tree doesn't get at the default 512M.
            if ((int) ini_get('memory_limit') < 1024 * 1024 * 1024) {
                ini_set('memory_limit', '1024M');
            }

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a3', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * First sheet of a global workbook — one row per data sheet with counts and totals.
     *
     * @param  \Illuminate\Support\Collection<int, array{0: string, 1: array{0: array<int, string>, 1: array<int, array<int, mixed>>}}>  $sections
     */
    private function writeSummarySheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $sections, string $sessionLabel): void
    {
        $sheet->fromArray(['Sheet', 'Rows', 'Key total', 'Notes'], null, 'A1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        $summaryRows = [];
        $grandRows = 0;

        foreach ($sections as [$name, $section]) {
            [$header, $rows] = $section;
            $count = count($rows);
            $grandRows += $count;
            [$totalLabel, $totalValue] = $this->sectionMoneyTotal($name, $header, $rows);

            $summaryRows[] = [
                self::sheetTitle($name),
                $count,
                $totalValue !== null ? $totalValue : '',
                $totalLabel ?? ($count === 0 ? 'No records' : ''),
            ];
        }

        $summaryRows[] = ['', '', '', ''];
        $summaryRows[] = ['TOTAL ROWS', $grandRows, '', 'All data sheets combined'];
        $summaryRows[] = ['Academic Session', $sessionLabel, '', ''];
        $summaryRows[] = ['Exported at', now()->format('d M Y H:i'), '', ''];

        $sheet->fromArray($summaryRows, null, 'A2');
        foreach (range(1, 4) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: ?string, 1: float|null}
     */
    private function sectionMoneyTotal(string $entity, array $header, array $rows): array
    {
        $columnHints = match ($entity) {
            'fee' => ['Amount', 'Total fee collected'],
            'expense' => ['Amount', 'Total expenses'],
            'salary' => ['Net Salary', 'Total net salary'],
            'bank' => ['Amount', 'Total bank movements'],
            'fuel-log' => ['Cost', 'Total fuel cost'],
            default => null,
        };

        if (! $columnHints || $rows === []) {
            return [null, null];
        }

        [$columnName, $label] = $columnHints;
        $index = array_search($columnName, $header, true);
        if ($index === false) {
            return [null, null];
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row[$index] ?? 0);
        }

        return [$label, round($sum, 2)];
    }

    private static function sheetTitle(string $entity): string
    {
        return match ($entity) {
            'fuel-log' => 'Fuel Log',
            'student-udise' => 'Student UDISE',
            default => ucfirst($entity),
        };
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>} */
    private function buildSection(string $entity, ?Request $request = null): array
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);
        $byDate = fn ($q) => $q->when(! $allSessions && $session && $session->start_date && $session->end_date,
            fn ($q) => $q->whereBetween('date', [$session->start_date, $session->end_date]));

        return match ($entity) {
            'student' => $this->buildStudentMasterExport($request),
            'student-udise' => $this->buildStudentUdiseExport($request),
            'fee' => [
                ['Receipt No', 'Student', 'Admission No', 'Amount', 'Discount', 'Fine', 'Mode', 'Date', 'Status'],
                FeePayment::with('student:id,name,admission_no')
                    ->when(! $allSessions && $session, fn ($q) => $q->where('academic_session_id', $session->id))
                    ->orderByDesc('payment_date')->get()->map(fn (FeePayment $p) => [
                        $p->receipt_no, $p->student->name ?? '', $p->student->admission_no ?? '', (float) $p->amount,
                        (float) $p->discount_amount, (float) $p->fine_amount, $p->payment_mode, $p->payment_date->toDateString(), $p->status,
                    ])->all(),
            ],
            'expense' => [
                ['Voucher No', 'Category', 'Title', 'Amount', 'Date', 'Mode'],
                $byDate(Expense::with('expenseCategory:id,name'))->orderByDesc('date')->get()->map(fn (Expense $e) => [
                    $e->voucher_no, $e->expenseCategory->name ?? '', $e->title, (float) $e->amount, $e->date->toDateString(), $e->payment_mode,
                ])->all(),
            ],
            'salary' => [
                ['Employee Type', 'Employee', 'Period', 'Basic', 'Allowances', 'Deductions', 'Net Salary', 'Status'],
                SalarySlip::with('employee')
                    ->when(! $allSessions && $session && $session->start_date && $session->end_date,
                        fn ($q) => $q->whereBetween('period', [$session->start_date->format('Y-m'), $session->end_date->format('Y-m')]))
                    ->orderByDesc('period')->get()->map(fn (SalarySlip $s) => [
                        $s->employee_type, $s->employee->name ?? '', $s->period, (float) $s->basic_salary,
                        (float) $s->allowances, (float) $s->deductions, (float) $s->net_salary, $s->status,
                    ])->all(),
            ],
            'bank' => [
                ['Account', 'Type', 'Amount', 'Date', 'Reference No'],
                $byDate(BankTransaction::with('bankAccount:id,account_name'))->orderByDesc('date')->get()->map(fn (BankTransaction $t) => [
                    $t->bankAccount->account_name ?? '', $t->type, (float) $t->amount, $t->date->toDateString(), $t->reference_no,
                ])->all(),
            ],
            'fuel-log' => [
                ['Vehicle', 'Date', 'Liters', 'Cost', 'Odometer'],
                $byDate(FuelLog::with('vehicle:id,vehicle_no'))->orderByDesc('date')->get()->map(fn (FuelLog $f) => [
                    $f->vehicle->vehicle_no ?? '', $f->date->toDateString(), (float) $f->liters, (float) $f->cost, $f->odometer_reading,
                ])->all(),
            ],
            // Routes aren't session-bound data (a route doesn't belong to an academic year) — never scoped.
            'route' => [
                ['Route', 'Start Point', 'End Point', 'Vehicle', 'Status'],
                TransportRoute::with('vehicle:id,vehicle_no')->orderBy('name')->get()->map(fn (TransportRoute $r) => [
                    $r->name, $r->start_point, $r->end_point, $r->vehicle->vehicle_no ?? '', $r->status,
                ])->all(),
            ],
            'marks' => [
                ['Student', 'Admission No', 'Exam', 'Subject', 'Marks Obtained', 'Max Marks'],
                Mark::with(['student:id,name,admission_no', 'examSchedule.exam:id,name', 'examSchedule.subject:id,name'])
                    ->when(! $allSessions && $session, fn ($q) => $q->whereHas('examSchedule.exam', fn ($eq) => $eq->where('academic_session_id', $session->id)))
                    ->get()->map(fn (Mark $m) => [
                        $m->student->name ?? '', $m->student->admission_no ?? '', $m->examSchedule->exam->name ?? '',
                        $m->examSchedule->subject->name ?? '', (float) $m->marks_obtained, (float) ($m->examSchedule->max_marks ?? 0),
                    ])->all(),
            ],
            'attendance' => $this->buildAttendanceExport($byDate),
            default => [[], []],
        };
    }

    /**
     * Attendance has no direct Eloquent relation to Student (polymorphic `attendable`, keyed
     * by a literal 'student' type string, not a morph map) — resolved manually here instead.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildAttendanceExport(\Closure $byDate): array
    {
        $rows = $byDate(Attendance::where('attendable_type', 'student'))
            ->orderBy('date')
            ->get(['attendable_id', 'date', 'status', 'remarks']);

        $students = Student::query()
            ->whereIn('id', $rows->pluck('attendable_id')->unique())
            ->with(['schoolClass:id,name', 'section:id,name'])
            ->get(['id', 'name', 'admission_no', 'school_class_id', 'section_id'])
            ->keyBy('id');

        return [
            ['Admission No', 'Student', 'Class', 'Section', 'Date', 'Status', 'Remarks'],
            $rows->map(function (Attendance $a) use ($students) {
                $student = $students->get($a->attendable_id);

                return [
                    $student->admission_no ?? '', $student->name ?? '',
                    $student->schoolClass->name ?? '', $student->section->name ?? '',
                    $a->date->toDateString(), $a->status, $a->remarks ?? '',
                ];
            })->all(),
        ];
    }

    /**
     * One row per student_session_history entry (Master Rec shape).
     * Optional filters (AND): students.status + academic session names (history rows).
     * Students with zero history get one fallback row for is_current — only if that session
     * is included by the session filter (or the filter is "all").
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildStudentMasterExport(?Request $request = null): array
    {
        $currentSession = AcademicSession::where('is_current', true)->first();
        $currentSessionName = $currentSession?->name ?? '';
        $currentAliases = $currentSessionName !== '' ? AcademicSession::nameAliases($currentSessionName) : [];

        $status = $request ? trim((string) $request->query('status', 'All')) : 'All';
        if (! in_array($status, ['All', 'Active', 'Inactive'], true)) {
            $status = 'All';
        }

        $sessionFilter = $this->resolveSessionFilter($request);

        $query = Student::with([
            'sessionHistories',
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
            'additionalDetail',
        ])->orderBy('admission_no');

        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $students = $query->get();
        $rows = [];

        foreach ($students as $student) {
            $allHistories = $student->sessionHistories->sortBy('session')->values();
            $histories = $sessionFilter === null
                ? $allHistories
                : $allHistories->filter(fn (StudentSessionHistory $h) => in_array($h->session, $sessionFilter, true))->values();

            if ($allHistories->isEmpty()) {
                // Zero-history fallback — only when current session is in the filter (or filter is all).
                if ($sessionFilter === null || ($currentSessionName !== '' && array_intersect($currentAliases, $sessionFilter))) {
                    $rows[] = $this->studentMasterRow($student, null, $currentSessionName);
                }
                continue;
            }

            foreach ($histories as $history) {
                $rows[] = $this->studentMasterRow($student, $history, $currentSessionName);
            }
        }

        // Oldest session first so a re-import leaves students reflecting the latest session.
        // Sort by SESSION (0) then Adm No. (1) while still on the full Master shape.
        usort($rows, function (array $a, array $b) {
            $sessionCmp = strcmp((string) $a[0], (string) $b[0]);

            return $sessionCmp !== 0 ? $sessionCmp : strcmp((string) $a[1], (string) $b[1]);
        });

        return $this->projectStudentColumns(self::STUDENT_MASTER_HEADERS, $rows, $request);
    }

    /**
     * UDISE-flagged students only (is_in_udise = true). Same status/session filters as Master
     * export; columns are the fixed UDISE list (subset selectable via ?columns[]=).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildStudentUdiseExport(?Request $request = null): array
    {
        $currentSession = AcademicSession::where('is_current', true)->first();
        $currentSessionName = $currentSession?->name ?? '';
        $currentAliases = $currentSessionName !== '' ? AcademicSession::nameAliases($currentSessionName) : [];

        $status = $request ? trim((string) $request->query('status', 'All')) : 'All';
        if (! in_array($status, ['All', 'Active', 'Inactive'], true)) {
            $status = 'All';
        }

        $sessionFilter = $this->resolveSessionFilter($request);

        $query = Student::with([
            'sessionHistories',
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name',
            'mother:id,name',
            'udiseDetail',
            'additionalDetail',
        ])
            ->whereHas('udiseDetail', fn ($q) => $q->where('is_in_udise', true))
            ->orderBy('admission_no');

        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $students = $query->get();
        $masterIndex = array_flip(self::STUDENT_MASTER_HEADERS);
        $sourceKeys = array_values(self::STUDENT_UDISE_EXPORT_COLUMNS);
        $displayHeaders = array_keys(self::STUDENT_UDISE_EXPORT_COLUMNS);

        $rows = [];
        foreach ($students as $student) {
            $allHistories = $student->sessionHistories->sortBy('session')->values();
            $histories = $sessionFilter === null
                ? $allHistories
                : $allHistories->filter(fn (StudentSessionHistory $h) => in_array($h->session, $sessionFilter, true))->values();

            if ($allHistories->isEmpty()) {
                if ($sessionFilter === null || ($currentSessionName !== '' && array_intersect($currentAliases, $sessionFilter))) {
                    $master = $this->studentMasterRow($student, null, $currentSessionName);
                    $rows[] = $this->mapUdiseExportRow($master, $masterIndex, $sourceKeys);
                }
                continue;
            }

            foreach ($histories as $history) {
                $master = $this->studentMasterRow($student, $history, $currentSessionName);
                $rows[] = $this->mapUdiseExportRow($master, $masterIndex, $sourceKeys);
            }
        }

        usort($rows, function (array $a, array $b) {
            // Sort by Class & Section (index 3) then Student Name (index 0).
            $classCmp = strcmp((string) ($a[3] ?? ''), (string) ($b[3] ?? ''));

            return $classCmp !== 0 ? $classCmp : strcmp((string) ($a[0] ?? ''), (string) ($b[0] ?? ''));
        });

        return $this->projectNamedColumns($displayHeaders, $rows, $request);
    }

    /**
     * @param  array<int, mixed>  $master
     * @param  array<string, int>  $masterIndex
     * @param  list<string>  $sourceKeys
     * @return list<mixed>
     */
    private function mapUdiseExportRow(array $master, array $masterIndex, array $sourceKeys): array
    {
        $out = [];
        foreach ($sourceKeys as $key) {
            $i = $masterIndex[$key] ?? null;
            $out[] = $i === null ? '' : ($master[$i] ?? '');
        }

        return $out;
    }

    /**
     * Project rows to a subset of $headers, preserving the order of requested columns.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function projectNamedColumns(array $headers, array $rows, ?Request $request): array
    {
        if (! $request) {
            return [$headers, $rows];
        }

        $raw = $request->query('columns', []);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw) || $raw === [] || in_array('All', $raw, true)) {
            return [$headers, $rows];
        }

        $indexes = [];
        foreach ($raw as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            $i = array_search($name, $headers, true);
            if ($i !== false && ! in_array($i, $indexes, true)) {
                $indexes[] = $i;
            }
        }

        if ($indexes === []) {
            return [$headers, $rows];
        }

        $projectedHeaders = array_values(array_map(fn (int $i) => $headers[$i], $indexes));
        $projectedRows = array_map(
            fn (array $row) => array_values(array_map(fn (int $i) => $row[$i] ?? '', $indexes)),
            $rows
        );

        return [$projectedHeaders, $projectedRows];
    }

    /**
     * Keep only requested columns (exact Master header names). Adm No. + Name always included.
     * Unchecked columns are removed entirely — not left blank.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function projectStudentColumns(array $headers, array $rows, ?Request $request): array
    {
        $indexes = $this->resolveColumnIndexes($request);
        if ($indexes === null) {
            return [$headers, $rows];
        }

        $projectedHeaders = array_values(array_map(fn (int $i) => $headers[$i], $indexes));
        $projectedRows = array_map(
            fn (array $row) => array_values(array_map(fn (int $i) => $row[$i] ?? '', $indexes)),
            $rows
        );

        return [$projectedHeaders, $projectedRows];
    }

    /**
     * @return list<int>|null  null = all columns
     */
    private function resolveColumnIndexes(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        $raw = $request->query('columns', 'All');
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw) || $raw === [] || in_array('All', $raw, true)) {
            return null;
        }

        $wanted = [];
        foreach ($raw as $name) {
            if ($name === '' || ! is_string($name)) {
                continue;
            }
            $wanted[$name] = true;
        }

        // Mandatory identifiers — always keep even if the client omitted them.
        $wanted['Adm No.'] = true;
        $wanted['Name'] = true;

        $indexes = [];
        foreach (self::STUDENT_MASTER_HEADERS as $i => $header) {
            if (isset($wanted[$header])) {
                $indexes[] = $i;
            }
        }

        // Fall back to full sheet if nothing matched (bad/stale column names).
        return $indexes === [] ? null : $indexes;
    }

    /**
     * Resolve the header session picker into [?AcademicSession $session, bool $allSessions] —
     * same convention used across the rest of the app (AcademicSession::fromRequest()/requestWantsAll()).
     *
     * @return array{0: ?AcademicSession, 1: bool}
     */
    private function resolveHeaderSession(?Request $request): array
    {
        if (! $request) {
            return [null, true];
        }

        $allSessions = AcademicSession::requestWantsAll($request);

        return [$allSessions ? null : AcademicSession::fromRequest($request, true), $allSessions];
    }

    private function sessionLabel(?Request $request): string
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);

        return $allSessions ? 'All Sessions' : ($session->name ?? 'All Sessions');
    }

    /**
     * Parse ?sessions=All or ?sessions[]=2025-2026&sessions[]=2026-2027 into alias list, or null = all.
     * When the Student Export panel hasn't sent an explicit `sessions` filter at all, this falls
     * back to whichever Academic Session is selected in the header picker (rather than always "All").
     *
     * @return list<string>|null
     */
    private function resolveSessionFilter(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        if (! $request->has('sessions')) {
            [$session, $allSessions] = $this->resolveHeaderSession($request);

            return $allSessions || ! $session ? null : AcademicSession::nameAliases($session->name);
        }

        $raw = $request->query('sessions', 'All');
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw) || $raw === [] || in_array('All', $raw, true) || in_array('All Sessions', $raw, true)) {
            return null;
        }

        $aliases = [];
        foreach ($raw as $name) {
            if ($name === '') {
                continue;
            }
            $aliases = array_merge($aliases, AcademicSession::nameAliases((string) $name));
        }

        return $aliases === [] ? null : array_values(array_unique($aliases));
    }

    /**
     * @return array<int, mixed>
     */
    private function studentMasterRow(Student $student, ?StudentSessionHistory $history, string $fallbackSession): array
    {
        $udise = $student->udiseDetail;
        $additional = $student->additionalDetail;

        $session = $history?->session ?: $fallbackSession;
        $class = $history?->class_name ?: ($student->schoolClass->name ?? '');
        $section = $history?->section_name ?: ($student->section->name ?? '');
        $roll = $history?->roll_no ?? $student->roll_no;
        $status = $history?->status ?: $student->status;
        $promotionStatus = $history?->promotion_status ?? '';

        $classSection = trim($class.($section !== '' ? ' / '.$section : ''));
        $classSectionRoll = trim($classSection.($roll ? ' · Roll '.$roll : ''), ' ·');
        $aadhaar = (string) ($student->aadhar_no ?? '');

        return [
            $this->cell($session),
            $this->cell($student->admission_no),
            $this->cell($udise?->student_type),
            $this->cell($student->aadhar_no),
            $this->cell($student->name),
            $this->cell($student->mother?->name),
            $this->cell($student->father?->name),
            $this->cell($student->address),
            $this->cell($student->mobile),
            $this->cell($class),
            $this->cell($section),
            $this->cell($roll),
            $this->cell(optional($student->dob)?->format('d-m-Y')),
            $this->cell($student->gender),
            $this->cell($student->blood_group),
            $this->cell(optional($student->admission_date)?->format('d-m-Y')),
            $this->cell($additional?->class_admitted),
            $this->cell($udise?->stoppage),
            $this->cell($udise?->vehicle),
            $this->cell($udise?->admission_type),
            $this->yesNo($udise?->uses_transport),
            $this->cell($student->category),
            $this->yesNo($udise?->hostel),
            $this->cell($udise?->minority_group),
            $this->yesNo($udise?->bpl_beneficiary),
            $this->yesNo($udise?->ews_disadvantaged),
            $this->yesNo($udise?->cwsn),
            $this->cell($udise?->clsl),
            $this->cell($udise?->name_as_per_aadhaar),
            $this->yesNo($udise?->indian_national),
            $this->cell($udise?->guardian_name),
            $this->cell($udise?->alternate_mobile),
            $this->cell($student->email),
            $this->cell($udise?->mother_tongue),
            $this->cell($student->pincode),
            $this->cell($udise?->student_state_code),
            $this->yesNo($udise?->aay_beneficiary),
            $this->cell($udise?->type_of_impairments),
            $this->cell($history?->previous_year_schooling_status),
            $this->cell($history?->previous_year_class),
            $this->cell($udise?->rte_ews_admission),
            $this->yesNo($udise?->is_repeater),
            $this->cell($history?->exam_appeared),
            $this->cell($history?->exam_result),
            $this->cell($history?->exam_marks_percent),
            $this->cell($history?->attendance_days),
            $this->cell($history?->attendance_percent),
            $this->cell($status),
            $this->cell($udise?->student_pen),
            $this->cell($additional?->tc_number),
            $this->cell(optional($additional?->tc_date)?->format('d-m-Y')),
            $this->cell($additional?->last_class_studied),
            $this->cell($promotionStatus),
            $this->cell($udise?->entry_status),
            $this->cell($classSection),
            $this->cell($aadhaar !== '' ? 'Available' : 'Not Available'),
            $this->cell($classSectionRoll),
            '', // Out of school child
            '', // Mainstreamed when
            '', // Disability certificate
            '', // Disability percentage
            '', // Medium of instruction
            '', // Languages group
            '', // Academic stream
            '', // Subjects group
            '', // RTE amount claimed
            '', // Facilities provided
            '', // CWSN facilities
            '', // Olympiads
            '', // NCC
            '', // NSS
            '', // Scouts and Guides
            $this->cell($additional?->height),
            $this->cell($additional?->weight),
            '', // Distance to school
            '', // Parents education
        ];
    }

    /** Null / empty → real blank cell; never the literal "None". */
    private function cell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = trim((string) $value);

        return strcasecmp($string, 'none') === 0 ? '' : $string;
    }

    private function yesNo(?bool $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? 'Yes' : 'No';
    }
}
