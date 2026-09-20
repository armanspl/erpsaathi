<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceMonthlySummary;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\ImportExportLog;
use App\Models\Income;
use App\Models\SalarySlip;
use App\Models\Student;
use App\Models\StudentSessionHistory;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Services\FeeCalculator;
use App\Services\SalaryBankWorkbookService;
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
    private const ENTITIES = ['student', 'student-udise', 'stud-rec-sum', 'fee', 'expense', 'salary', 'bank', 'route', 'attendance', 'global'];

    /** Sheet order for Global Workbook export (Stud_Rec_Sum sits after Student Master, matching GAS INC_EXP). */
    private const GLOBAL_SHEETS = ['student', 'stud-rec-sum', 'fee', 'expense', 'salary', 'bank', 'route', 'attendance'];

    /** Header/tab accent per sheet — gives the multi-sheet Global Workbook a distinct color per data type. */
    private const SHEET_ACCENTS = [
        'student' => '4F46E5',
        'student-udise' => '0F766E',
        'stud-rec-sum' => 'CA8A04',
        'fee' => '059669',
        'expense' => 'DC2626',
        'salary' => '7C3AED',
        'bank' => '2563EB',
        'route' => '0D9488',
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
        'CWSN' => 'compliance', 'clsl' => 'compliance', 'Name As per AADHAAR' => 'compliance', 'INDIAN NATIONALity' => 'compliance',
        'MOTHER TONGUE' => 'compliance', 'Student State Code' => 'compliance',
        'Whether Antyodaya Anna Yojana (AAY) beneficiary?' => 'compliance', 'Type of Impairments' => 'compliance',
        'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS' => 'previous_year', 'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR' => 'previous_year',
        'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)' => 'previous_year', 'Is Repeater' => 'previous_year',
        'APPEARED FOR EXAM IN PREVIOUS CLASS' => 'previous_year', 'RESULT FOR PREVIOUS EXAM' => 'previous_year',
        'MARKS % OF PREVIOUS EXAM' => 'previous_year', 'CLASS ATTENDED DAYS (PREVIOUS YEAR)' => 'previous_year', 'ATTENDENCE %' => 'previous_year',
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
        'CWSN' => 9, 'clsl' => 9, 'Name As per AADHAAR' => 20, 'INDIAN NATIONALity' => 16,
        'MOTHER TONGUE' => 14, 'Student State Code' => 12,
        'Whether Antyodaya Anna Yojana (AAY) beneficiary?' => 16, 'Type of Impairments' => 16,
        'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS' => 16, 'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR' => 16,
        'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)' => 18, 'Is Repeater' => 10,
        'APPEARED FOR EXAM IN PREVIOUS CLASS' => 14, 'RESULT FOR PREVIOUS EXAM' => 14,
        'MARKS % OF PREVIOUS EXAM' => 12, 'CLASS ATTENDED DAYS (PREVIOUS YEAR)' => 14, 'ATTENDENCE %' => 12,
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
        'INDIAN NATIONALity',
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
        'ATTENDENCE %',
        'Status',
        'Student PEN',
        'TC Number',
        'TC Date',
        'Last Class Studied',
        'STATUS',
        'UDISE',
        // Extended UDISE / Student Master profile columns (export-only if blank in records)
        'Aadhaar Status',
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

        // Global workbook builds many sheets — avoid PHP's default 120s kill on large schools.
        if ($entity === 'global' || $entity === 'stud-rec-sum' || $entity === 'student') {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');
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
            ? collect(self::GLOBAL_SHEETS)->map(fn ($e) => [$e, $this->buildSection($e, $request)])
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
            $customTitle = $section[2] ?? null;

            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $title = $customTitle ?: self::sheetTitle($name);
            $sheet->setTitle(mb_substr($title, 0, 31));
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
    private function styleDataSheet(Worksheet $sheet, array $headers, int $rowCount, string $accentHex, bool $richLayout = false, int $headerRow = 1): void
    {
        $colCount = count($headers);
        if ($colCount < 1 || $headerRow < 1) {
            return;
        }

        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $firstDataRow = $headerRow + 1;
        $lastRow = $rowCount > 0 ? ($headerRow + $rowCount) : $headerRow;

        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
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
        $sheet->getRowDimension($headerRow)->setRowHeight($richLayout ? 32 : 22);
        $sheet->freezePane('B'.$firstDataRow);
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
                $headerCell = $sheet->getStyle("{$col}{$headerRow}");
                $headerCell->getFill()->getStartColor()->setRGB($groupHex);
                $headerCell->getBorders()->getAllBorders()->getColor()->setRGB($groupHex);
            }
        }

        if ($rowCount < 1) {
            return;
        }

        $dataRange = "A{$firstDataRow}:{$lastCol}{$lastRow}";
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '1F2937']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => $richLayout ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_GENERAL,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        if ($rowCount <= 500) {
            for ($row = $firstDataRow; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight($richLayout ? 20 : 18);
            }
        }

        if ($richLayout) {
            foreach ($headers as $i => $label) {
                if (! in_array($label, self::STUDENT_WRAP_HEADERS, true)) {
                    continue;
                }
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
            }
        }

        if ($rowCount <= 500) {
            for ($row = $firstDataRow + 1; $row <= $lastRow; $row += 2) {
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
            'fee' => ['TOTAL', 'Total fee collected'],
            'stud-rec-sum' => ['TOT_PMNT', 'Total student payments'],
            'expense' => ['AMOUNT', 'Total expenses'],
            'salary' => ['TOTAL', 'Total salary paid (Bank sheet)'],
            // No 'bank' entry: its TOTAL column is a running balance, not a summable amount —
            // summing it across rows would add together many point-in-time snapshots.
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
            'student-udise' => 'Student UDISE',
            'stud-rec-sum' => 'Stud_Rec_Sum',
            'fee' => 'INCOME',
            'expense' => 'EXPENSES',
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
            'stud-rec-sum' => $this->buildStudRecSumExport($request),
            'fee' => $this->buildIncomeExport($request),
            // Same column layout as the legacy workbook's EXPENSES sheet / Global Workbook import.
            'expense' => [
                ['RCPT', 'YEAR', 'MONTH', 'DATE', 'DESCRIPTION', 'PART 1', 'PART 2', 'AMOUNT', 'PART 3', 'REMARKS'],
                $byDate(Expense::with('expenseCategory:id,name'))->orderByDesc('date')->get()->map(fn (Expense $e) => [
                    preg_replace('/^EXP-/', '', (string) $e->voucher_no) ?: $e->voucher_no,
                    $this->fiscalYearLabel($e->date),
                    $e->date->copy()->startOfMonth()->toDateString(),
                    $e->date->toDateString(),
                    $e->title,
                    $e->expenseCategory->name ?? '',
                    $e->part2 ?? '',
                    (float) $e->amount,
                    $e->part3 ?? '',
                    $e->remarks ?? '',
                ])->all(),
            ],
            'salary' => $this->buildSalaryBankExport($request),
            'bank' => $this->buildBankExport($request),
            // Routes aren't session-bound data (a route doesn't belong to an academic year) — never scoped.
            'route' => [
                ['Route', 'Start Point', 'End Point', 'Vehicle', 'Status'],
                TransportRoute::with('vehicle:id,vehicle_no')->orderBy('name')->get()->map(fn (TransportRoute $r) => [
                    $r->name, $r->start_point, $r->end_point, $r->vehicle->vehicle_no ?? '', $r->status,
                ])->all(),
            ],
            'attendance' => $this->buildAttendanceExport($request),
            default => [[], []],
        };
    }

    /**
     * Fee / income export — one row per student per receipt DATE. Same-day payments for the
     * same student are merged into a single line (a student never shows more than one receipt
     * line for a given date), with REG/ADM/ANN/TUI/TRA/FINE/DIARY broken out into their own
     * amount columns (anything else lands in OTHER) and a TOTAL column replacing the old
     * generic 'FEE' figure with the full amount the student paid that day.
     *
     * This is a reporting layout only — Global Workbook import still expects (and produces via
     * re-export of a legacy workbook) the older long-format HEAD/FEE-per-row sheet; this wide
     * layout is not read back in by GlobalWorkbookImportController.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildIncomeExport(?Request $request = null): array
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);

        $headCodes = ['REG', 'ADM', 'ANN', 'TUI', 'TRA', 'FINE', 'DIARY'];
        $headers = array_merge(
            ['YEAR', 'MONTH', 'DATE', 'ADM NO', 'NAME', 'ADDRESS', 'CLASS'],
            $headCodes,
            ['OTHER', 'TOTAL', 'SESSION', 'RECEIPT', 'REMARKS', 'PMNT MODE']
        );
        $knownCodes = array_flip($headCodes);

        $payments = FeePayment::query()
            ->with([
                'student:id,name,admission_no,address,address_line_2,school_class_id',
                'student.schoolClass:id,name',
                'academicSession:id,name',
            ])
            ->when(! $allSessions && $session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->where('status', '!=', 'Rolled Back')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $receiptNos = $payments->pluck('receipt_no')->filter()->unique()->values()->all();
        $modeByReceipt = [];
        if ($receiptNos !== []) {
            $txs = BankTransaction::query()
                ->with('bankAccount:id,account_name')
                ->whereIn('reference_no', $receiptNos)
                ->where('type', 'Deposit')
                ->orderByDesc('id')
                ->get();
            foreach ($txs as $tx) {
                $ref = (string) $tx->reference_no;
                if ($ref === '' || isset($modeByReceipt[$ref])) {
                    continue;
                }
                $account = trim((string) ($tx->bankAccount->account_name ?? ''));
                if ($account !== '') {
                    $modeByReceipt[$ref] = $account;
                }
            }
        }

        // Group by student + calendar day — merges multiple same-day payments into one row.
        $groups = [];
        foreach ($payments as $payment) {
            $paymentDate = $payment->payment_date?->toDateString() ?? '';
            $key = $payment->student_id.'|'.$paymentDate;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'student' => $payment->student,
                    'date' => $paymentDate,
                    'session_label' => $payment->academicSession->name ?? '',
                    'amounts' => array_fill_keys(array_merge($headCodes, ['OTHER']), 0.0),
                    'receipts' => [],
                    'remarks' => [],
                    'modes' => [],
                ];
            }

            $items = is_array($payment->items) ? $payment->items : [];
            if ($items === [] && (float) $payment->amount > 0 && (float) $payment->fine_amount <= 0) {
                $items[] = ['fee_head_name' => 'Fee', 'amount' => (float) $payment->amount, 'months' => []];
            }

            foreach ($items as $item) {
                $headName = trim((string) ($item['fee_head_name'] ?? 'Fee')) ?: 'Fee';
                $amount = (float) ($item['amount'] ?? 0);
                if ($amount == 0.0) {
                    continue;
                }
                $code = $this->incomeHeadCode($headName);
                $bucket = isset($knownCodes[$code]) ? $code : 'OTHER';
                $groups[$key]['amounts'][$bucket] += $amount;

                $remark = $this->incomeRemarksForItem($item, $payment->remarks);
                if ($remark !== '' && ! in_array($remark, $groups[$key]['remarks'], true)) {
                    $groups[$key]['remarks'][] = $remark;
                }
            }

            $fine = (float) $payment->fine_amount;
            if ($fine > 0) {
                $groups[$key]['amounts']['FINE'] += $fine;
            }

            $receipt = $payment->receipt_no ?? '';
            if ($receipt !== '' && ! in_array($receipt, $groups[$key]['receipts'], true)) {
                $groups[$key]['receipts'][] = $receipt;
            }

            $mode = $modeByReceipt[$receipt] ?? ($payment->payment_mode ?: 'Cash');
            if (! in_array($mode, $groups[$key]['modes'], true)) {
                $groups[$key]['modes'][] = $mode;
            }

            if ($groups[$key]['session_label'] === '' && $payment->academicSession) {
                $groups[$key]['session_label'] = $payment->academicSession->name ?? '';
            }
        }

        $rows = [];
        foreach ($groups as $group) {
            $total = array_sum($group['amounts']);
            if ($total == 0.0) {
                continue;
            }

            $student = $group['student'];
            $date = $group['date'];
            $yearLabel = $date !== '' ? $this->fiscalYearLabel(\Carbon\Carbon::parse($date)) : '';
            $monthStart = $date !== '' ? \Carbon\Carbon::parse($date)->startOfMonth()->toDateString() : '';
            $address = trim(implode(' ', array_filter([
                $student->address ?? null,
                $student->address_line_2 ?? null,
            ])));

            $row = [
                $yearLabel, $monthStart, $date,
                $student->admission_no ?? '', $student->name ?? '', $address,
                $student->schoolClass->name ?? '',
            ];
            foreach ($headCodes as $code) {
                $row[] = ($group['amounts'][$code] != 0.0) ? round($group['amounts'][$code], 2) : '';
            }
            $row[] = ($group['amounts']['OTHER'] != 0.0) ? round($group['amounts']['OTHER'], 2) : '';
            $row[] = round($total, 2);
            $row[] = $group['session_label'];
            $row[] = implode(', ', $group['receipts']);
            $row[] = implode('; ', $group['remarks']);
            $row[] = implode('/', $group['modes']);

            $rows[] = $row;
        }

        // Misc income rows (no admission no) — same wide columns, one row per entry.
        $incomes = Income::query()
            ->with('bankAccount:id,account_name')
            ->when(! $allSessions && $session && $session->start_date && $session->end_date,
                fn ($q) => $q->whereBetween('date', [$session->start_date, $session->end_date]))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($incomes as $income) {
            $date = $income->date?->toDateString() ?? '';
            $monthStart = $income->date?->copy()->startOfMonth()->toDateString() ?? '';
            $mode = trim((string) ($income->bankAccount->account_name ?? ''))
                ?: ($income->payment_mode ?: 'Cash');
            $code = $this->incomeHeadCode((string) ($income->source ?: 'Income'));
            $bucket = isset($knownCodes[$code]) ? $code : 'OTHER';
            $amount = round((float) $income->amount, 2);

            $row = [
                $income->date ? $this->fiscalYearLabel($income->date) : '',
                $monthStart, $date, '', '', '', '',
            ];
            foreach ($headCodes as $c) {
                $row[] = ($c === $bucket) ? $amount : '';
            }
            $row[] = ($bucket === 'OTHER') ? $amount : '';
            $row[] = $amount;
            $row[] = (! $allSessions && $session) ? ($session->name ?? '') : '';
            $row[] = preg_replace('/^INC-/', '', (string) $income->voucher_no) ?: $income->voucher_no;
            $row[] = $income->remarks;
            $row[] = $mode;

            $rows[] = $row;
        }

        return [$headers, $rows];
    }

    /**
     * Bank export — same column layout as the legacy workbook's BANK <account> ledger sheets /
     * Global Workbook import: YEAR | MONTH | DATE | ITEM | CHQ NO | DR/CR | AMOUNT | TOTAL |
     * CATEGORY, with an ACCOUNT column up front since every account's ledger is combined into
     * one sheet here instead of one sheet per account (matching how the Salary sheet already
     * combines everyone into one Bank-layout sheet). Each account's own OPENING BALANCE row is
     * synthesized first, then its transactions follow in date order with a running TOTAL —
     * signed the same way the source ledger is (deposits positive, withdrawals negative).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildBankExport(?Request $request = null): array
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);

        $headers = ['ACCOUNT', 'YEAR', 'MONTH', 'DATE', 'ITEM', 'CHQ NO', 'DR/CR', 'AMOUNT', 'TOTAL', 'CATEGORY'];

        $accounts = BankAccount::query()->orderBy('account_name')->get();
        if ($accounts->isEmpty()) {
            return [$headers, []];
        }

        $transactionsByAccount = BankTransaction::query()
            ->whereIn('bank_account_id', $accounts->pluck('id'))
            ->when(! $allSessions && $session && $session->start_date && $session->end_date,
                fn ($q) => $q->whereBetween('date', [$session->start_date, $session->end_date]))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->groupBy('bank_account_id');

        $rows = [];
        foreach ($accounts as $account) {
            $running = (float) $account->opening_balance;

            $rows[] = [
                $account->account_name, '', '', '',
                'OPENING BALANCE', '', '',
                round($running, 2), round($running, 2), '',
            ];

            foreach ($transactionsByAccount->get($account->id, collect()) as $t) {
                $signedAmount = $t->type === 'Deposit' ? (float) $t->amount : -(float) $t->amount;
                $running += $signedAmount;

                $rows[] = [
                    $account->account_name,
                    $this->fiscalYearLabel($t->date),
                    $t->date->copy()->startOfMonth()->toDateString(),
                    $t->date->toDateString(),
                    $t->item ?: ($t->remarks ?: $t->type),
                    $t->reference_no ?: '',
                    $t->type === 'Deposit' ? 'CR' : 'DR',
                    round($signedAmount, 2),
                    round($running, 2),
                    $t->category ?: '',
                ];
            }
        }

        return [$headers, $rows];
    }

    /**
     * Stud_Rec_Sum — per-student fee ledger matching GAS INC_EXP columns:
     * ADM NO. | NAME | FATHER | ADDRESS | MOBILE | CLASS | VEHICLE |
     * TOT_PMNT | REG | ADM | ANN_PMNT | ANN_DUES | TUI_PMNT | TUI CALC | TUI_DUES |
     * TRA_PMNT | TRA CALC | TRA_DUES | DUES
     *
     * Payments mirror INCOME sheet SUMIFS; CALC/DUES use fee structure + transport fare
     * (Excel style: DUES columns = paid − expected).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildStudRecSumExport(?Request $request = null): array
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);
        if ($allSessions || ! $session) {
            $session = AcademicSession::where('is_current', true)->first()
                ?? AcademicSession::query()->orderByDesc('start_date')->first();
        }

        $headers = [
            'ADM NO.', 'NAME OF STUDENT', 'FATHER NAME', 'ADDRESS', 'MOBILE', 'CLASS', 'VEHICLE',
            'TOT_PMNT', 'REG', 'ADM', 'ANN_PMNT', 'ANN_DUES', 'TUI_PMNT', 'TUI CALC', 'TUI_DUES',
            'TRA_PMNT', 'TRA CALC', 'TRA_DUES', 'DUES',
        ];

        if (! $session) {
            return [$headers, []];
        }

        $students = Student::query()
            ->with([
                'father:id,name',
                'schoolClass:id,name',
                'udiseDetail:id,student_id,vehicle,stoppage',
            ])
            ->orderBy('admission_no')
            ->get();

        if ($students->isEmpty()) {
            return [$headers, []];
        }

        $studentIds = $students->pluck('id')->all();

        $vehicleByStudent = StudentTransport::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'Active')
            ->with(['route:id,vehicle_id', 'route.vehicle:id,vehicle_no', 'routeStop:id,fare'])
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        $headCodeById = FeeHead::query()->get(['id', 'name'])
            ->mapWithKeys(fn (FeeHead $h) => [(string) $h->id => $this->incomeHeadCode((string) $h->name)])
            ->all();

        $paidByStudent = [];
        foreach ($studentIds as $id) {
            $paidByStudent[$id] = [
                'TOT' => 0.0, 'REG' => 0.0, 'ADM' => 0.0, 'ANN' => 0.0, 'TUI' => 0.0, 'TRA' => 0.0,
            ];
        }

        $payments = FeePayment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_session_id', $session->id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
            })
            ->orderBy('id')
            ->get(['id', 'student_id', 'items', 'amount', 'refunded_amount', 'fine_amount', 'status']);

        foreach ($payments as $payment) {
            $sid = (int) $payment->student_id;
            if (! isset($paidByStudent[$sid])) {
                continue;
            }

            $gross = (float) $payment->amount;
            $net = $gross - (float) $payment->refunded_amount;
            if ($net <= 0 || $gross <= 0) {
                continue;
            }
            $scale = $net / $gross;

            $items = is_array($payment->items) ? $payment->items : [];
            if ($items === [] && $net > 0 && (float) $payment->fine_amount <= 0) {
                $items[] = ['fee_head_name' => 'Fee', 'amount' => $gross];
            }

            foreach ($items as $item) {
                $amount = (float) ($item['amount'] ?? 0) * $scale;
                if ($amount == 0.0) {
                    continue;
                }
                $code = '';
                $feeHeadId = (int) ($item['fee_head_id'] ?? 0);
                if ($feeHeadId && isset($headCodeById[(string) $feeHeadId])) {
                    $code = $headCodeById[(string) $feeHeadId];
                } else {
                    $code = $this->incomeHeadCode(trim((string) ($item['fee_head_name'] ?? 'Fee')) ?: 'Fee');
                }
                $paidByStudent[$sid]['TOT'] += $amount;
                if (isset($paidByStudent[$sid][$code])) {
                    $paidByStudent[$sid][$code] += $amount;
                }
            }

            $fine = (float) $payment->fine_amount * $scale;
            if ($fine > 0) {
                $paidByStudent[$sid]['TOT'] += $fine;
            }
        }

        FeeCalculator::flushRuntimeCache();
        FeeCalculator::warmForStudents($students, $session);

        $monthKeys = collect($session->months())->map(fn ($m) => $m['key'] ?? null)->filter()->values()->all();
        if ($monthKeys === [] && $session->start_date && $session->end_date) {
            $cursor = $session->start_date->copy()->startOfMonth();
            $end = $session->end_date->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $monthKeys[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        }
        $monthCount = count($monthKeys);

        $rows = [];
        foreach ($students as $student) {
            $paid = $paidByStudent[$student->id] ?? [
                'TOT' => 0.0, 'REG' => 0.0, 'ADM' => 0.0, 'ANN' => 0.0, 'TUI' => 0.0, 'TRA' => 0.0,
            ];

            // Structure charges only — no per-student payment re-query (was the 120s bottleneck).
            $charge = $this->studRecSumCharges($student, $session, $monthKeys, $monthCount, $vehicleByStudent->get($student->id));

            $annPmnt = round($paid['ANN'], 2);
            $tuiPmnt = round($paid['TUI'], 2);
            $traPmnt = round($paid['TRA'], 2);
            $annCalc = round($charge['ANN'], 2);
            $tuiCalc = round($charge['TUI'], 2);
            $traCalc = round($charge['TRA'], 2);
            $annDues = round($annPmnt - $annCalc, 2);
            $tuiDues = round($tuiPmnt - $tuiCalc, 2);
            $traDues = round($traPmnt - $traCalc, 2);

            $vehicle = trim((string) ($student->udiseDetail?->vehicle ?? ''));
            if ($vehicle === '') {
                $vehicle = trim((string) ($vehicleByStudent->get($student->id)?->route?->vehicle?->vehicle_no ?? ''));
            }
            if ($vehicle === '') {
                $vehicle = 'None';
            }

            $address = trim(implode(' ', array_filter([
                $student->address ?? null,
                $student->address_line_2 ?? null,
            ])));

            $rows[] = [
                $student->admission_no ?? '',
                $student->name ?? '',
                $student->father?->name ?? '',
                $address,
                $student->mobile ?? '',
                $student->schoolClass->name ?? '',
                $vehicle,
                round($paid['TOT'], 2),
                round($paid['REG'], 2),
                round($paid['ADM'], 2),
                $annPmnt,
                $annDues,
                $tuiPmnt,
                $tuiCalc,
                $tuiDues,
                $traPmnt,
                $traCalc,
                $traDues,
                round($annDues + $tuiDues + $traDues, 2),
            ];
        }

        return [$headers, $rows];
    }

    /**
     * Expected REG/ADM/ANN/TUI/TRA charges for Stud_Rec_Sum (no DB hits beyond FeeCalculator cache).
     *
     * @param  list<string>  $monthKeys
     * @return array{REG: float, ADM: float, ANN: float, TUI: float, TRA: float}
     */
    private function studRecSumCharges(Student $student, AcademicSession $session, array $monthKeys, int $monthCount, ?StudentTransport $transport): array
    {
        $charge = ['REG' => 0.0, 'ADM' => 0.0, 'ANN' => 0.0, 'TUI' => 0.0, 'TRA' => 0.0];

        foreach (FeeCalculator::breakdownOnly($student, $session) as $plan) {
            $code = $this->incomeHeadCode((string) ($plan['fee_head_name'] ?? ''));
            if (! isset($charge[$code])) {
                continue;
            }
            $unit = (float) ($plan['amount'] ?? 0);
            if ($unit <= 0) {
                continue;
            }
            $freq = strtolower(str_replace(' ', '_', (string) ($plan['frequency'] ?? 'one_time')));
            if (in_array($freq, ['annual', 'one_time'], true)) {
                $charge[$code] += $unit;
            } elseif ($freq === 'quarterly') {
                $n = 0;
                foreach ($monthKeys as $monthKey) {
                    $monthNum = (int) substr((string) $monthKey, 5, 2);
                    if (in_array($monthNum, [4, 7, 10, 1], true)) {
                        $n++;
                    }
                }
                $charge[$code] += $unit * $n;
            } else {
                $charge[$code] += $unit * $monthCount;
            }
        }

        $fare = (float) ($transport?->routeStop?->fare ?? 0);
        if ($fare > 0 && $monthCount > 0) {
            $feeStart = $transport?->feeStartMonthKey();
            $billable = 0;
            foreach ($monthKeys as $monthKey) {
                if ($feeStart && (string) $monthKey < (string) $feeStart) {
                    continue;
                }
                $billable++;
            }
            $charge['TRA'] += $fare * $billable;
        }

        return $charge;
    }

    /** Reverse of GlobalWorkbookImportController::INCOME_HEAD_CANONICAL_MAP. */
    private function incomeHeadCode(string $headName): string
    {
        $map = [
            'registration fee' => 'REG',
            'admission fee' => 'ADM',
            'session fee' => 'ANN',
            'tution fee' => 'TUI',
            'tuition fee' => 'TUI',
            'transport' => 'TRA',
            'fine' => 'FINE',
        ];

        $key = strtolower(trim($headName));

        return $map[$key] ?? (strlen($headName) <= 12 ? strtoupper($headName) : $headName);
    }

    /**
     * Prefer coverage month tokens (e.g. DEC) from fee line items — matches school REMARKS style.
     *
     * @param  array<string, mixed>  $item
     */
    private function incomeRemarksForItem(array $item, ?string $paymentRemarks): string
    {
        $months = $item['months'] ?? [];
        if (! is_array($months) || $months === []) {
            $single = $item['month'] ?? null;
            $months = $single ? [$single] : [];
        }

        $labels = [];
        foreach ($months as $ym) {
            $ym = trim((string) $ym);
            if (preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
                $labels[] = strtoupper(\Carbon\Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->format('M'));
            } elseif ($ym !== '') {
                $labels[] = strtoupper($ym);
            }
        }
        $labels = array_values(array_unique($labels));
        if ($labels !== []) {
            return implode('-', $labels);
        }

        $remarks = trim((string) $paymentRemarks);
        if ($remarks === '') {
            return $this->incomeHeadCode((string) ($item['fee_head_name'] ?? 'Fee'));
        }

        // Imported remarks often look like "REG | Class: NUR | Name: …" — keep the first segment.
        $first = trim(explode('|', $remarks)[0]);

        return $first !== '' ? $first : $remarks;
    }

    private function fiscalYearLabel(\Carbon\CarbonInterface $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        if ($month >= 4) {
            return sprintf('%d-%02d', $year, ($year + 1) % 100);
        }

        return sprintf('%d-%02d', $year - 1, $year % 100);
    }

    /**
     * Monthly attendance summaries (same source as Attendance Import / Student Attendance UI).
     * One wide row per student+session — month cells are days present (Mar→Feb school year).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildAttendanceExport(?Request $request = null): array
    {
        [$session, $allSessions] = $this->resolveHeaderSession($request);
        $sessionStartYear = (! $allSessions && $session?->start_date)
            ? (int) $session->start_date->format('Y')
            : null;

        $query = AttendanceMonthlySummary::query()
            ->with(['student:id,name,admission_no,school_class_id,section_id,roll_no', 'student.schoolClass:id,name', 'student.section:id,name']);
        if ($sessionStartYear !== null) {
            $query->where('session_start_year', $sessionStartYear);
        }

        $grouped = $query->get()->groupBy(fn (AttendanceMonthlySummary $r) => $r->student_id.'|'.$r->session_start_year);

        // Month slots matching Attendance Import: Mar–Sep (start year), Oct–Mar (next year).
        $monthSlots = [
            [3, 0], [4, 0], [5, 0], [6, 0], [7, 0], [8, 0], [9, 0],
            [10, 0], [11, 0], [12, 0], [1, 1], [2, 1], [3, 1],
        ];

        $headers = [
            'ENROL', 'NAME', 'CLASS', 'SECTION', 'SESSION',
            'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'TOT', '%',
            'OCT', 'NOV', 'DEC', 'JAN', 'FEB', 'MAR2', 'TOT2', '%2',
            'G TOT', 'G %',
        ];

        $rows = [];
        foreach ($grouped as $key => $summaries) {
            /** @var \Illuminate\Support\Collection<int, AttendanceMonthlySummary> $summaries */
            $first = $summaries->first();
            $student = $first?->student;
            $startYear = (int) $first->session_start_year;
            $byYm = [];
            foreach ($summaries as $s) {
                $byYm[sprintf('%04d-%02d', $s->year, $s->month)] = $s;
            }

            $presentCells = [];
            $half1Wd = 0;
            $half1Dp = 0;
            $half2Wd = 0;
            $half2Dp = 0;

            foreach ($monthSlots as $index => [$month, $yearOffset]) {
                $year = $startYear + $yearOffset;
                $ym = sprintf('%04d-%02d', $year, $month);
                $s = $byYm[$ym] ?? null;
                $presentCells[] = $s !== null ? (int) $s->days_present : '';
                if ($s) {
                    if ($index < 7) {
                        $half1Wd += (int) $s->working_days;
                        $half1Dp += (int) $s->days_present;
                    } else {
                        $half2Wd += (int) $s->working_days;
                        $half2Dp += (int) $s->days_present;
                    }
                }
            }

            $gWd = $half1Wd + $half2Wd;
            $gDp = $half1Dp + $half2Dp;

            $rows[] = array_merge(
                [
                    $student->admission_no ?? '',
                    $student->name ?? '',
                    $student->schoolClass->name ?? ($first->class_sheet ?? ''),
                    $student->section->name ?? '',
                    sprintf('%04d-%02d', $startYear, ($startYear + 1) % 100),
                ],
                array_slice($presentCells, 0, 7),
                [
                    $half1Wd > 0 || $half1Dp > 0 ? $half1Dp : '',
                    $half1Wd > 0 ? round(($half1Dp / $half1Wd) * 100, 2) : '',
                ],
                array_slice($presentCells, 7, 6),
                [
                    $half2Wd > 0 || $half2Dp > 0 ? $half2Dp : '',
                    $half2Wd > 0 ? round(($half2Dp / $half2Wd) * 100, 2) : '',
                    $gWd > 0 || $gDp > 0 ? $gDp : '',
                    $gWd > 0 ? round(($gDp / $gWd) * 100, 2) : '',
                ]
            );
        }

        usort($rows, function (array $a, array $b) {
            $sessionCmp = strcmp((string) $a[4], (string) $b[4]);
            if ($sessionCmp !== 0) {
                return $sessionCmp;
            }
            $classCmp = strcmp((string) $a[2], (string) $b[2]);

            return $classCmp !== 0 ? $classCmp : strcmp((string) $a[0], (string) $b[0]);
        });

        return [$headers, $rows];
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

        $monthlyByKey = AttendanceMonthlySummary::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy(fn (AttendanceMonthlySummary $r) => $r->student_id.'|'.$r->session_start_year);

        $rows = [];

        foreach ($students as $student) {
            $allHistories = $student->sessionHistories->sortBy('session')->values();
            $histories = $sessionFilter === null
                ? $allHistories
                : $allHistories->filter(fn (StudentSessionHistory $h) => in_array($h->session, $sessionFilter, true))->values();

            if ($allHistories->isEmpty()) {
                // Zero-history fallback — only when current session is in the filter (or filter is all).
                if ($sessionFilter === null || ($currentSessionName !== '' && array_intersect($currentAliases, $sessionFilter))) {
                    $startYear = $this->sessionStartYearFromLabel($currentSessionName)
                        ?? ($currentSession?->start_date ? (int) $currentSession->start_date->format('Y') : null);
                    $monthlyStats = $this->monthlyStatsFromGrouped($monthlyByKey, $student->id, $startYear);
                    $rows[] = $this->studentMasterRow($student, null, $currentSessionName, $monthlyStats);
                }
                continue;
            }

            foreach ($histories as $history) {
                $startYear = $this->sessionStartYearFromLabel($history->session);
                $monthlyStats = $this->monthlyStatsFromGrouped($monthlyByKey, $student->id, $startYear);
                $rows[] = $this->studentMasterRow($student, $history, $currentSessionName, $monthlyStats);
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
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AttendanceMonthlySummary>>  $monthlyByKey
     * @return array{days_present: int|null, percentage: float|null}|null
     */
    private function monthlyStatsFromGrouped($monthlyByKey, int $studentId, ?int $sessionStartYear): ?array
    {
        if (! $sessionStartYear) {
            return null;
        }
        $rows = $monthlyByKey->get($studentId.'|'.$sessionStartYear);
        if (! $rows || $rows->isEmpty()) {
            return null;
        }

        $workingDays = 0;
        $daysPresent = 0;
        foreach ($rows as $row) {
            $workingDays += (int) $row->working_days;
            $daysPresent += (int) $row->days_present;
        }

        return [
            'days_present' => $daysPresent,
            'percentage' => $workingDays > 0 ? round(($daysPresent / $workingDays) * 100, 2) : null,
        ];
    }

    private function sessionStartYearFromLabel(?string $label): ?int
    {
        if (! $label) {
            return null;
        }
        if (preg_match('/(\d{4})\s*-\s*\d{2,4}/', $label, $m)) {
            return (int) $m[1];
        }

        return null;
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
     * Global / salary export in the school's "SALARY 26-27 Bank" layout:
     * SL NO | NAME | DESIG | BASIC SALARY | APR…MAR | TOTAL
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    private function buildSalaryBankExport(?Request $request = null): array
    {
        [$session] = $this->resolveHeaderSession($request);
        $startYear = $session?->start_date
            ? (int) $session->start_date->format('Y')
            : (int) now()->year;

        return app(SalaryBankWorkbookService::class)->buildExport($startYear);
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
     * @param  array{days_present: int|null, percentage: float|null}|null  $monthlyStats
     * @return array<int, mixed>
     */
    private function studentMasterRow(Student $student, ?StudentSessionHistory $history, string $fallbackSession, ?array $monthlyStats = null): array
    {
        $udise = $student->udiseDetail;
        $additional = $student->additionalDetail;

        $session = $history?->session ?: $fallbackSession;
        $class = $history?->class_name ?: ($student->schoolClass->name ?? '');
        $section = $history?->section_name ?: ($student->section->name ?? '');
        $roll = $history?->roll_no ?? $student->roll_no;
        $status = $history?->status ?: $student->status;
        $promotionStatus = $history?->promotion_status ?? '';

        $aadhaar = (string) ($student->aadhar_no ?? '');

        // Prefer monthly summary totals when present; fall back to stored Master history values.
        $attendanceDays = $monthlyStats['days_present'] ?? $history?->attendance_days;
        $attendancePercent = array_key_exists('percentage', $monthlyStats ?? []) && $monthlyStats['percentage'] !== null
            ? $monthlyStats['percentage']
            : $history?->attendance_percent;

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
            $this->cell($attendanceDays),
            $this->cell($attendancePercent),
            $this->cell($status),
            $this->cell($udise?->student_pen),
            $this->cell($additional?->tc_number),
            $this->cell(optional($additional?->tc_date)?->format('d-m-Y')),
            $this->cell($additional?->last_class_studied),
            $this->cell($promotionStatus),
            $this->cell($udise?->entry_status),
            $this->cell($aadhaar !== '' ? 'Available' : 'Not Available'),
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
