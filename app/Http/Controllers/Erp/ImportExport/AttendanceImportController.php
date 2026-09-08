<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceMonthlySummary;
use App\Models\ImportExportLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Imports the school's per-class attendance workbook (one sheet per class: NUR, LKG, UKG,
 * 1st–8th). Sheet titles may be mixed-case (1ST vs 4th); matching is case-insensitive.
 * STD DETAILS / empty sheets are ignored.
 *
 * Each class sheet gives, per student per month, a raw "days present" COUNT — not which
 * specific calendar days. Those monthly totals are stored in attendance_monthly_summaries
 * (never expanded into daily Present/Absent rows). Blank month cells are skipped; a
 * literal 0 is stored as 0 present. Working days come from row 2 of the same column.
 * Columns K/L/S/T/U/V/W (totals, %, formulas) are ignored.
 */
class AttendanceImportController extends Controller
{
    /** Normalized sheet title => target school_classes.name. */
    private const CLASS_SHEET_MAP = [
        'NUR' => 'Nursery',
        'NURSERY' => 'Nursery',
        'LKG' => 'LKG',
        'UKG' => 'UKG',
        '1ST' => '1', '1' => '1',
        '2ND' => '2', '2' => '2',
        '3RD' => '3', '3' => '3',
        '4TH' => '4', '4' => '4',
        '5TH' => '5', '5' => '5',
        '6TH' => '6', '6' => '6',
        '7TH' => '7', '7' => '7',
        '8TH' => '8', '8' => '8',
    ];

    /**
     * Fixed column position (0-indexed, A=0) => [calendar month, year offset from session
     * start year]. Matches the sheet's own layout: D–J = MAR(start),APR..SEP(start);
     * M–R = OCT..DEC(start),JAN..MAR(start+1). K,L,S,T,U,V,W are formulas — deliberately absent.
     */
    private const MONTH_COLUMNS = [
        3 => [3, 0],   // D = Mar (session start year)
        4 => [4, 0],   // E = Apr
        5 => [5, 0],   // F = May
        6 => [6, 0],   // G = Jun
        7 => [7, 0],   // H = Jul
        8 => [8, 0],   // I = Aug
        9 => [9, 0],   // J = Sep
        12 => [10, 0], // M = Oct
        13 => [11, 0], // N = Nov
        14 => [12, 0], // O = Dec
        15 => [1, 1],  // P = Jan (next year)
        16 => [2, 1],  // Q = Feb (next year)
        17 => [3, 1],  // R = Mar (next year)
    ];

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:40960',
        ]);

        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $sheetNames = SpreadsheetImportReader::listSheetNames($path);

        $matched = [];
        $ignoredSheets = [];
        foreach ($sheetNames as $title) {
            $key = $this->normalizeSheetKey($title);
            if ($key === '' || $key === 'SHEET2' || $key === 'STD DETAILS') {
                $ignoredSheets[] = $title;
                continue;
            }
            if (isset(self::CLASS_SHEET_MAP[$key])) {
                $matched[$title] = ['class_name' => self::CLASS_SHEET_MAP[$key], 'sheet_key' => $key];
            } else {
                $ignoredSheets[] = $title;
            }
        }

        if ($matched === []) {
            return response()->json([
                'message' => 'No recognizable class sheets found (expected NUR, LKG, UKG, 1st-8th). '
                    .'Present sheets: '.implode(', ', $sheetNames),
            ], 422);
        }

        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, array_keys($matched));

        $userId = Auth::guard('erp')->id();
        $now = now()->toDateTimeString();

        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'attendance',
            'filename' => $file->getClientOriginalName(),
            'total_rows' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'ignored_columns' => [],
            'performed_by_id' => $userId,
        ]);

        $sessionStartYear = $this->resolveSessionStartYear($spreadsheet, array_keys($matched));
        $sessionLabel = $sessionStartYear !== null
            ? sprintf('%04d-%02d', $sessionStartYear, ($sessionStartYear + 1) % 100)
            : null;

        $studentsByAdm = Student::query()->pluck('id', 'admission_no')->all();
        $classIdByName = [];
        foreach (SchoolClass::query()->get(['id', 'name']) as $class) {
            $classIdByName[mb_strtolower(trim((string) $class->name))] = $class->id;
        }

        $declaredCapCache = [];

        $total = 0;
        $success = 0;
        $failed = 0;
        $summaryBuffer = [];
        $failedRowsBuffer = [];
        $failedLogsBuffer = [];
        $failedRowsResponse = [];
        $sheetStats = [];
        $skippedNoClass = [];

        foreach ($matched as $title => $meta) {
            $className = $meta['class_name'];
            $sheetKey = $meta['sheet_key'];
            $sheet = $spreadsheet->getSheetByName($title);
            if (! $sheet) {
                continue;
            }

            $classId = $classIdByName[mb_strtolower(trim($className))] ?? null;
            if (! $classId) {
                $skippedNoClass[] = "{$title} -> no Class named \"{$className}\" found";

                continue;
            }

            if ($sessionStartYear !== null && ! isset($declaredCapCache[$title])) {
                $declaredCapCache[$title] = $this->readDeclaredWorkingDays($sheet, $sessionStartYear);
            }

            $parsed = SpreadsheetImportReader::fromWorksheet($sheet, 3); // header=row3 (discarded), rows=row4+
            $rowNumber = 3;
            $sheetSuccess = 0;
            $sheetFailed = 0;
            $sheetSummaryCount = 0;

            foreach ($parsed['rows'] as $row) {
                $rowNumber++;
                $enrol = trim((string) ($row[0] ?? ''));
                if ($enrol === '') {
                    break; // ENROL blank marks the end of student data on this sheet
                }
                if (is_numeric($enrol) && str_contains($enrol, '.')) {
                    $enrol = (string) (int) round((float) $enrol);
                }

                $total++;
                $studentId = $studentsByAdm[$enrol] ?? null;

                if (! $studentId) {
                    $failed++;
                    $sheetFailed++;
                    $msg = "No student found for enrollment no. {$enrol}.";
                    $failedRowsBuffer[] = [
                        'import_export_log_id' => $log->id,
                        'row_number' => $rowNumber,
                        'row_data' => json_encode(['sheet' => $title, 'enrol' => $enrol, 'name' => $row[1] ?? null], JSON_THROW_ON_ERROR),
                        'error_message' => $msg,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $failedLogsBuffer[] = [
                        'import_export_log_id' => $log->id,
                        'row_number' => $rowNumber,
                        'status' => 'Failed',
                        'identifier' => $enrol,
                        'summary' => json_encode(['sheet' => $title], JSON_THROW_ON_ERROR),
                        'error_message' => $msg,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $failedRowsResponse[] = ['id' => $title.'-'.$rowNumber, 'sheet' => $title, 'row_number' => $rowNumber, 'error_message' => $msg];

                    continue;
                }

                if ($sessionStartYear !== null) {
                    foreach (self::MONTH_COLUMNS as $colIndex => [$month, $yearOffset]) {
                        $year = $sessionStartYear + $yearOffset;
                        $ym = sprintf('%04d-%02d', $year, $month);

                        $workingDays = $declaredCapCache[$title][$ym] ?? null;
                        if ($workingDays === null || $workingDays <= 0) {
                            continue;
                        }

                        // Blank month cells = not filled yet → skip (do not write zero).
                        $presentCount = $this->parsePresentCount($row[$colIndex] ?? null);
                        if ($presentCount === null) {
                            continue;
                        }

                        $percentage = $workingDays > 0
                            ? round(($presentCount / $workingDays) * 100, 2)
                            : 0.0;

                        $summaryBuffer[] = [
                            'student_id' => $studentId,
                            'school_class_id' => $classId,
                            'class_sheet' => $sheetKey,
                            'month' => $month,
                            'year' => $year,
                            'session_start_year' => $sessionStartYear,
                            'working_days' => $workingDays,
                            'days_present' => $presentCount,
                            'percentage' => $percentage,
                            'imported_by_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $sheetSummaryCount++;
                    }
                }

                $success++;
                $sheetSuccess++;

                if (count($summaryBuffer) >= 500) {
                    $this->flushSummaries($summaryBuffer);
                }
            }

            $sheetStats[] = [
                'sheet' => $title,
                'class' => $className,
                'students' => $sheetSuccess,
                'failed' => $sheetFailed,
                'monthly_summaries' => $sheetSummaryCount,
            ];
        }

        $this->flushSummaries($summaryBuffer);
        foreach (array_chunk($failedRowsBuffer, 250) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }
        foreach (array_chunk($failedLogsBuffer, 250) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $totalSummaries = array_sum(array_column($sheetStats, 'monthly_summaries'));

        $log->update([
            'total_rows' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);

        $messageParts = [
            'Session: '.($sessionLabel ?? 'could not be determined — no attendance was written'),
            'Sheets matched: '.count($matched),
            'Students processed: '.$total,
            'Monthly summary rows written: '.$totalSummaries,
            'Failed (student not found): '.$failed,
        ];
        if ($skippedNoClass !== []) {
            $messageParts[] = 'Skipped sheets (no matching class): '.implode('; ', $skippedNoClass);
        }
        if ($ignoredSheets !== []) {
            $messageParts[] = 'Ignored sheets: '.implode(', ', $ignoredSheets);
        }

        return response()->json([
            'log' => $log->fresh(),
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'stats' => [
                'sheets' => $sheetStats,
                'sheets_ignored' => $ignoredSheets,
                'sheets_skipped_no_class' => $skippedNoClass,
                'monthly_summaries_written' => $totalSummaries,
                'attendance_records_written' => $totalSummaries, // backward-compatible alias for ImportExport UI
                'session' => $sessionLabel,
            ],
            'message' => implode(' | ', $messageParts),
        ]);
    }

    /** @param  list<array<string, mixed>>  $buffer */
    private function flushSummaries(array &$buffer): void
    {
        if ($buffer === []) {
            return;
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            AttendanceMonthlySummary::upsert(
                $chunk,
                ['student_id', 'year', 'month'],
                [
                    'school_class_id',
                    'class_sheet',
                    'session_start_year',
                    'working_days',
                    'days_present',
                    'percentage',
                    'imported_by_id',
                    'updated_at',
                ]
            );
        }
        $buffer = [];
    }

    private function normalizeSheetKey(string $title): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim($title))) ?? '';
    }

    /**
     * Blank / empty → null (skip month). Numeric including 0 → days present.
     * "None" is treated as 0 present (explicit zero), not blank.
     */
    private function parsePresentCount(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }
        if (strcasecmp($text, 'None') === 0 || strcasecmp($text, 'null') === 0) {
            return 0;
        }
        if (! is_numeric($text)) {
            return null;
        }

        return max(0, (int) round((float) $text));
    }

    /**
     * The session start year (e.g. 2026 for "2026-27") is read from a matched sheet's row-1
     * title ("Attendence Report 2026-27"), tried across every matched sheet in case the first
     * one's title is blank/unparsable. Falls back to the school's current AcademicSession.
     *
     * @param  list<string>  $titles
     */
    private function resolveSessionStartYear(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $titles): ?int
    {
        foreach ($titles as $title) {
            $sheet = $spreadsheet->getSheetByName($title);
            if (! $sheet) {
                continue;
            }
            $text = (string) $sheet->getCell('A1')->getCalculatedValue();
            if ($text === '' || str_starts_with($text, '=')) {
                $text = (string) $sheet->getCell('A1')->getValue();
            }
            if (preg_match('/(\d{4})\s*-\s*(\d{2,4})/', $text, $m)) {
                return (int) $m[1];
            }
        }

        $current = AcademicSession::where('is_current', true)->first();
        if ($current?->start_date) {
            return (int) $current->start_date->format('Y');
        }

        return null;
    }

    /**
     * Row 2's per-month working-day totals (same columns as student data).
     * Blank/non-numeric/zero → month not started; that month is skipped on import.
     *
     * @return array<string, int>  Y-m => declared working days
     */
    private function readDeclaredWorkingDays(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $sessionStartYear): array
    {
        $caps = [];
        foreach (self::MONTH_COLUMNS as $colIndex => [$month, $yearOffset]) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $sheet->getCell($colLetter.'2');
            $raw = $cell->getCalculatedValue();
            if ($raw === null || $raw === '' || (is_string($raw) && str_starts_with($raw, '='))) {
                $raw = $cell->getValue();
            }
            if ($raw === null || trim((string) $raw) === '' || ! is_numeric($raw)) {
                continue;
            }
            $count = (int) round((float) $raw);
            if ($count <= 0) {
                continue;
            }
            $ym = sprintf('%04d-%02d', $sessionStartYear + $yearOffset, $month);
            $caps[$ym] = $count;
        }

        return $caps;
    }
}
