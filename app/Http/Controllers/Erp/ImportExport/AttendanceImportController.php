<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\ImportExportLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\WorkingDayConfig;
use App\Services\SpreadsheetImportReader;
use Carbon\Carbon;
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
 * specific calendar days. Months are only imported when row-2 declared working days (W DAY)
 * for that month is filled and > 0; blank/future months are skipped. Blank/None present
 * counts are treated as 0 (all Absent for that month's working days).
 *
 * Conversion: take that many of the month's working days (WorkingDayConfig + Holiday,
 * capped by the sheet's declared total) in chronological order as Present, the rest as
 * Absent — an explicit approximation (see REMARKS_TEXT). Columns K/L/S/T/U/V/W (totals,
 * %, formulas) are ignored.
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

    private const REMARKS_TEXT = 'Imported (approximate) — exact day not tracked';

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
                $matched[$title] = self::CLASS_SHEET_MAP[$key];
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
        // Case-insensitive class lookup: "nursery" / "Nursery" / "1" all resolve.
        $classIdByName = [];
        foreach (SchoolClass::query()->get(['id', 'name']) as $class) {
            $classIdByName[mb_strtolower(trim((string) $class->name))] = $class->id;
        }
        $workingDayConfig = WorkingDayConfig::current();

        $holidaysCache = [];
        $declaredCapCache = [];
        $workingDatesCache = [];

        $total = 0;
        $success = 0;
        $failed = 0;
        $attendanceBuffer = [];
        $failedRowsBuffer = [];
        $failedLogsBuffer = [];
        $failedRowsResponse = [];
        $sheetStats = [];
        $skippedNoClass = [];

        foreach ($matched as $title => $className) {
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
            $sheetAttendanceCount = 0;

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

                        // Only months with a declared W DAY count on row 2 are imported.
                        // Blank/future months (Aug/Sep / term 2 currently empty) are skipped.
                        $cap = $declaredCapCache[$title][$ym] ?? null;
                        if ($cap === null || $cap <= 0) {
                            continue;
                        }

                        if (! isset($workingDatesCache[$title][$ym])) {
                            $workingDatesCache[$title][$ym] = $this->computeWorkingDates(
                                $ym,
                                $workingDayConfig,
                                $holidaysCache,
                                $cap
                            );
                        }
                        $workingDates = $workingDatesCache[$title][$ym];
                        if ($workingDates === []) {
                            continue;
                        }

                        // Blank / "None" present count = 0 (all Absent), not "skip month".
                        $presentCount = $this->parsePresentCount($row[$colIndex] ?? null);
                        $presentCount = min($presentCount, count($workingDates));

                        foreach ($workingDates as $i => $date) {
                            $attendanceBuffer[] = [
                                'attendable_type' => 'student',
                                'attendable_id' => $studentId,
                                'date' => $date,
                                'status' => $i < $presentCount ? 'Present' : 'Absent',
                                'remarks' => self::REMARKS_TEXT,
                                'marked_by_id' => $userId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            $sheetAttendanceCount++;
                        }
                    }
                }

                $success++;
                $sheetSuccess++;

                if (count($attendanceBuffer) >= 800) {
                    $this->flushAttendance($attendanceBuffer);
                }
            }

            $sheetStats[] = [
                'sheet' => $title,
                'class' => $className,
                'students' => $sheetSuccess,
                'failed' => $sheetFailed,
                'attendance_records' => $sheetAttendanceCount,
            ];
        }

        $this->flushAttendance($attendanceBuffer);
        foreach (array_chunk($failedRowsBuffer, 250) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }
        foreach (array_chunk($failedLogsBuffer, 250) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $totalAttendanceRecords = array_sum(array_column($sheetStats, 'attendance_records'));

        $log->update([
            'total_rows' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);

        $messageParts = [
            'Session: '.($sessionLabel ?? 'could not be determined — no attendance was written'),
            'Sheets matched: '.count($matched),
            'Students processed: '.$total,
            'Attendance day-records written: '.$totalAttendanceRecords,
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
                'attendance_records_written' => $totalAttendanceRecords,
                'session' => $sessionLabel,
            ],
            'message' => implode(' | ', $messageParts),
        ]);
    }

    /** @param  list<array<string, mixed>>  $buffer */
    private function flushAttendance(array &$buffer): void
    {
        if ($buffer === []) {
            return;
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            Attendance::upsert($chunk, ['attendable_type', 'attendable_id', 'date'], ['status', 'remarks', 'marked_by_id', 'updated_at']);
        }
        $buffer = [];
    }

    private function normalizeSheetKey(string $title): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim($title))) ?? '';
    }

    /** Blank / "None" / non-numeric → 0 present days. */
    private function parsePresentCount(mixed $raw): int
    {
        if ($raw === null) {
            return 0;
        }
        $text = trim((string) $raw);
        if ($text === '' || strcasecmp($text, 'None') === 0 || strcasecmp($text, 'null') === 0) {
            return 0;
        }
        if (! is_numeric($text)) {
            return 0;
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
     * Row 2's own per-month working-day totals (D2, E2, F2... same column positions as the
     * student data), where filled in — used as a cap on the computed working-day list, since
     * the school's real calendar (mid-term breaks etc.) may not be fully reflected in
     * WorkingDayConfig/Holiday yet. A blank/non-numeric/zero cell means "month not started"
     * — that month is skipped entirely on import.
     *
     * @return array<string, int>  Y-m => declared working days
     */
    private function readDeclaredWorkingDays(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $sessionStartYear): array
    {
        $caps = [];
        foreach (self::MONTH_COLUMNS as $colIndex => [$month, $yearOffset]) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1); // 0-indexed -> 1-indexed
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

    /** @return list<string> chronological working dates (Y-m-d) in this Y-m month, capped if given. */
    private function computeWorkingDates(string $ym, WorkingDayConfig $config, array &$holidaysCache, ?int $cap): array
    {
        $start = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        $daysInMonth = $start->daysInMonth;

        if (! isset($holidaysCache[$ym])) {
            $holidaysCache[$ym] = Holiday::whereYear('date', $start->year)->whereMonth('date', $start->month)
                ->pluck('date')->map(fn ($d) => $d->toDateString())->all();
        }
        $holidayDates = $holidaysCache[$ym];

        $offDays = is_array($config->weekly_off_days ?? null) ? $config->weekly_off_days : [];

        $dates = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $start->copy()->day($day);
            if (in_array($date->format('l'), $offDays, true) || in_array($date->toDateString(), $holidayDates, true)) {
                continue;
            }
            $dates[] = $date->toDateString();
        }

        if ($cap !== null && $cap >= 0 && $cap < count($dates)) {
            $dates = array_slice($dates, 0, $cap);
        }

        return $dates;
    }
}
