<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\CoScholasticGrade;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ImportExportLog;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports exam-marks workbooks in two shapes:
 *
 * 1. Current client format — a single "MARKLIST" workbook covering every class at once,
 *    with a per-subject block of PT1/NB1/SEA1/TOT(20)/HY or ANNU(80)/TOT(100) columns
 *    (e.g. sheet "NUR-8 (2)"). Class is read per-row from a "Class" column; PT1/NB1/SEA1/
 *    HY(or ANNU) each become their own exam, matching the same kinds as the legacy format
 *    below. Sibling summary sheets in the same workbook (plain Oral/Written/Total layouts,
 *    or a non-subject-wise PT1+NB1+SEA1 totals sheet) are duplicate views and are ignored.
 * 2. Legacy CLASS_*_TERM-1 / TERM-2 workbooks (one class per file, one sheet per exam:
 *    PT-1, NB-1, SEA-1, HY (Half Yearly) / PT-2, NB-2, SEA-2, ANNU (Annual)) — still
 *    supported for any workbook that doesn't contain the new wide-format sheet.
 *
 * GRADE is never stored as an exam. TEST-1/TEST-2 are calculated later (PT+NB+SEA).
 * "Ab" cells are stored as absent (not zero). Blank cells are skipped.
 * Re-import upserts by session+exam+class+student+subject.
 */
class ClassTermMarksImportController extends Controller
{
    private const CLASS_MAP = [
        'NUR' => 'Nursery',
        'NURSERY' => 'Nursery',
        'LKG' => 'LKG',
        'UKG' => 'UKG',
        '1' => '1', '1ST' => '1', '1STH' => '1',
        '2' => '2', '2ND' => '2',
        '3' => '3', '3RD' => '3',
        '4' => '4', '4TH' => '4',
        '5' => '5', '5TH' => '5',
        '6' => '6', '6TH' => '6',
        '7' => '7', '7TH' => '7',
        '8' => '8', '8TH' => '8',
    ];

    private const SUBJECT_ALIASES = [
        'english' => ['ENG', 'English'],
        'eng' => ['ENG', 'English'],
        'hindi' => ['HINDI', 'Hindi'],
        'hin' => ['HINDI', 'Hindi'],
        'urdu' => ['URDU', 'Urdu'],
        'arab-deeniyat' => ['Deeniyat', 'Deeniayat', 'Islamic Studies'],
        'gk-comp' => ['GK/COM', 'GK/EVS', 'GK'],
        's st' => ['S.ST', 'SST', 'Social Studies', 'Social Science'],
        'maths' => ['MATH', 'Maths', 'Mathematics', 'Math'],
        'math' => ['MATH', 'Maths', 'Mathematics', 'Math'],
        'mathematics' => ['MATH', 'Maths', 'Mathematics', 'Math'],
        'evs' => ['EVS'],
        'science' => ['SCI', 'Science'],
        'sci' => ['SCI', 'Science'],
        's.st' => ['S.ST', 'SST', 'Social Studies', 'Social Science'],
        'sst' => ['S.ST', 'SST', 'Social Studies', 'Social Science'],
        'social studies' => ['S.ST', 'SST', 'Social Studies'],
        'social science' => ['S.ST', 'SST', 'Social Science'],
        'deeniyat' => ['Deeniyat', 'Deeniayat', 'Islamic Studies'],
        'deeniayat' => ['Deeniyat', 'Deeniayat'],
        'gk/evs' => ['GK/EVS', 'GK/COM', 'GK', 'EVS'],
        'gk/com' => ['GK/COM', 'GK/EVS', 'GK'],
        'gk' => ['GK/COM', 'GK/EVS', 'GK'],
        'gk com' => ['GK/COM', 'GK'],
        'work education' => ['Work Education'],
        'art education' => ['Art Education', 'Drawing & Art', 'Drawing'],
        'drawing & art' => ['Drawing & Art', 'Art Education'],
        'drawing' => ['Drawing & Art', 'Art Education'],
        'sports/yoga/ncc' => ['Sports/Yoga/NCC', 'Sports', 'Sports & Yoga'],
        'sports yoga ncc' => ['Sports/Yoga/NCC', 'Sports'],
        'sports' => ['Sports/Yoga/NCC', 'Sports'],
        'yoga' => ['Sports/Yoga/NCC', 'Sports'],
        'ncc' => ['Sports/Yoga/NCC'],
    ];

    private const SKIP_HEADER_KEYS = [
        'roll no', 'roll no.', 'rollno', 'roll',
        'adm no', 'adm. no', 'adm. no.', 'admission no', 'admission no.', 'admission number', 'enrl', 'enrol',
        'name', 'student name', 'student',
        'class', 'class name',
        'm.o', 'm.o.', 'mo', 'marks obtained', 'obtained',
        'm.m', 'm.m.', 'mm', 'marks max', 'max marks', 'maximum',
        'term 1', 'term 2', 'term-1', 'term-2', 'overall',
        'father name', 'father', 'f name',
    ];

    private const GRADE_AREA_MAP = [
        'work education' => 'work_education',
        'art education' => 'drawing_art',
        'drawing & art' => 'drawing_art',
        'drawing' => 'drawing_art',
        'sports/yoga/ncc' => 'sports',
        'sports yoga ncc' => 'sports',
        'sports' => 'sports',
        'yoga' => 'sports',
        'ncc' => 'sports',
    ];

    private const DEFAULT_MAX_MARKS = 100.0;

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'default_max_marks' => 'nullable|numeric|min:1|max:9999',
        ]);

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $file = $request->file('file');
        $fallbackMax = (float) ($request->input('default_max_marks') ?: self::DEFAULT_MAX_MARKS);
        $path = $file->getRealPath();
        $filename = $file->getClientOriginalName();
        $sheetNames = SpreadsheetImportReader::listSheetNames($path);
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $sheetNames);

        // New client format: one workbook, every class, a wide per-subject block of
        // PT1/NB1/SEA1/TOT(20)/HY-or-ANNU(80)/TOT(100) columns. Detected from actual header
        // content (not sheet title), since these sheets are titled e.g. "NUR-8 (2)".
        $wideSheetInfo = [];
        foreach ($sheetNames as $title) {
            if ($this->sheetKind($title)['kind'] === 'grade') {
                continue;
            }
            $sheet = $spreadsheet->getSheetByName($title);
            if (! $sheet) {
                continue;
            }
            $headerRow = $this->findHeaderRow($sheet);
            if ($headerRow === null) {
                continue;
            }
            $subjectRow = $this->readHeaderRow($sheet, $headerRow);
            $componentRow = $this->readHeaderRow($sheet, $headerRow + 1);
            $blocks = $this->buildWideSubjectBlocks($subjectRow, $componentRow);
            if ($blocks === []) {
                continue;
            }
            $admCol = $this->findColumn($subjectRow, ['adm. no', 'adm. no.', 'adm no', 'admission no', 'admission no.', 'enrl', 'enrol', 'adm']);
            $classCol = $this->findColumn($subjectRow, ['class']);
            if ($admCol === null || $classCol === null) {
                continue;
            }
            $wideSheetInfo[$title] = [
                'header_row' => $headerRow,
                'blocks' => $blocks,
                'adm_col' => $admCol,
                'class_col' => $classCol,
            ];
        }
        $hasWideSheets = $wideSheetInfo !== [];

        if ($hasWideSheets) {
            $gradeSheetTitles = array_values(array_filter($sheetNames, fn ($t) => $this->sheetKind($t)['kind'] === 'grade'));
            $dataSheets = array_values(array_unique(array_merge(array_keys($wideSheetInfo), $gradeSheetTitles)));
            $ignoredSheets = array_values(array_diff($sheetNames, $dataSheets));
        } else {
            [$dataSheets, $ignoredSheets] = $this->partitionSheets($sheetNames);
            if ($dataSheets === []) {
                $spreadsheet->disconnectWorksheets();

                return response()->json([
                    'message' => 'No marks sheets found (expected PT-1/2, NB-1/2, SEA-1/2, HY, ANNU, GRADE, or a per-subject '
                        .'PT1/NB1/SEA1/HY marksheet). Present sheets: '.implode(', ', $sheetNames),
                ], 422);
            }
        }

        $class = null;
        $session = null;

        if ($hasWideSheets) {
            // Class is read per-row inside each wide sheet; only the session needs resolving here.
            $sessionLabel = '';
            if (preg_match('/(\d{4})\s*[-–]\s*(\d{2,4})/', pathinfo($filename, PATHINFO_FILENAME), $m)) {
                $sessionLabel = $m[0];
            }
            $session = $this->resolveSession($sessionLabel);
        } else {
            foreach ($dataSheets as $title) {
                $sheet = $spreadsheet->getSheetByName($title);
                if (! $sheet) {
                    continue;
                }
                $meta = $this->readSheetMeta($sheet, $filename, $title);
                $class ??= $this->resolveClass($meta['class_token']);
                $session ??= $this->resolveSession($meta['session_label']);
                if ($class && $session) {
                    break;
                }
            }

            if (! $class) {
                $spreadsheet->disconnectWorksheets();

                return response()->json([
                    'message' => 'Could not resolve class from filename/sheet (expected NUR, LKG, UKG, 1st–8th).',
                ], 422);
            }
        }

        if (! $session) {
            $spreadsheet->disconnectWorksheets();

            return response()->json([
                'message' => 'Could not resolve academic session from row 3 / filename (e.g. 2026-27).',
            ], 422);
        }

        $workbookTerm = $this->detectWorkbookTerm($filename, $dataSheets);
        $term = $this->ensureTerm($session, $workbookTerm);

        // Guarantee board exams exist for this workbook term (even if HY/ANNU sheet headers fail).
        $boardKind = $workbookTerm === 2 ? 'annual' : 'hy';
        $boardExam = $this->resolveOrCreateExam(
            $session,
            $boardKind,
            $workbookTerm,
            $this->canonicalExamName($boardKind, $workbookTerm, ''),
            $this->defaultMaxForKind($boardKind, $fallbackMax)
        );
        $this->attachExamToTerm($boardExam, $term, $boardKind);

        $userId = Auth::guard('erp')->id();
        $now = now()->toDateTimeString();

        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'class-term-marks',
            'filename' => $filename,
            'total_rows' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'ignored_columns' => [],
            'performed_by_id' => $userId,
        ]);

        $studentsByAdm = Student::query()->pluck('id', 'admission_no')->all();

        $total = 0;
        $success = 0;
        $failed = 0;
        $marksWritten = 0;
        $gradesWritten = 0;
        $failedRowsResponse = [];
        $failedRowsBuffer = [];
        $failedLogsBuffer = [];
        $marksBuffer = [];
        $sheetStats = [];
        $examsTouched = [];

        DB::transaction(function () use (
            $spreadsheet, $dataSheets, $filename, $class, $session, $term, $workbookTerm, $fallbackMax, $studentsByAdm,
            $wideSheetInfo, $hasWideSheets,
            &$total, &$success, &$failed, &$marksWritten, &$gradesWritten, &$failedRowsResponse, &$failedRowsBuffer,
            &$failedLogsBuffer, &$marksBuffer, &$sheetStats, &$examsTouched, $log, $now
        ) {
            foreach ($dataSheets as $title) {
                $sheet = $spreadsheet->getSheetByName($title);
                if (! $sheet) {
                    // Case-insensitive fallback (Excel sheet titles can differ by case/spaces).
                    foreach ($spreadsheet->getAllSheets() as $candidate) {
                        if (strcasecmp(trim($candidate->getTitle()), trim($title)) === 0) {
                            $sheet = $candidate;
                            break;
                        }
                    }
                }
                if (! $sheet) {
                    $sheetStats[] = ['sheet' => $title, 'skipped' => 'sheet not found in workbook after load'];
                    continue;
                }

                $parsed = $this->sheetKind($title);
                $kind = $parsed['kind'];
                $sheetTermNo = $parsed['term'] ?? $workbookTerm;
                $sheetTerm = $sheetTermNo === $workbookTerm
                    ? $term
                    : $this->ensureTerm($session, $sheetTermNo);

                if ($kind === 'grade') {
                    $stats = $this->importGradeSheet(
                        $sheet, $title, $sheetTerm, $studentsByAdm, $log, $now,
                        $total, $success, $failed, $gradesWritten, $failedRowsResponse, $failedRowsBuffer, $failedLogsBuffer
                    );
                    $sheetStats[] = $stats;
                    continue;
                }

                if (isset($wideSheetInfo[$title])) {
                    $stats = $this->importWideSubjectSheet(
                        $sheet, $title, $wideSheetInfo[$title], $session, $fallbackMax, $studentsByAdm,
                        $total, $success, $failed, $marksWritten, $failedRowsResponse, $failedRowsBuffer,
                        $failedLogsBuffer, $marksBuffer, $examsTouched, $log, $now
                    );
                    $sheetStats[] = $stats;
                    continue;
                }

                if ($hasWideSheets) {
                    // New-format workbook: any non-wide, non-grade sheet is a duplicate summary view — skip it.
                    $sheetStats[] = [
                        'sheet' => $title,
                        'skipped' => 'ignored — new-format workbook; only the detailed PT1/NB1/SEA1/HY-or-ANNU sheet is imported',
                    ];
                    continue;
                }

                $meta = $this->readSheetMeta($sheet, $filename, $title);
                $examName = $this->canonicalExamName($kind, $sheetTermNo, $meta['exam_name']);
                $defaultMax = $this->defaultMaxForKind($kind, $fallbackMax);

                // Create/link the exam FIRST so HY/ANNU always appear under Exams,
                // even when header/subject parsing needs retries.
                $exam = $this->resolveOrCreateExam($session, $kind, $sheetTermNo, $examName, $defaultMax);
                $this->attachExamToTerm($exam, $sheetTerm, $kind);
                $examsTouched[$exam->id] = $exam->name;

                $headerRow = $this->findHeaderRow($sheet);
                if ($headerRow === null) {
                    $sheetStats[] = [
                        'sheet' => $title,
                        'kind' => $kind,
                        'exam' => $exam->name,
                        'exam_id' => $exam->id,
                        'skipped' => 'exam created but no header row found',
                        'marks_written' => 0,
                    ];
                    continue;
                }

                $headers = $this->readHeaderRow($sheet, $headerRow);
                $admCol = $this->findColumn($headers, ['adm. no', 'adm. no.', 'adm no', 'admission no', 'admission no.', 'enrl', 'enrol', 'adm']);
                // HY/ANNU often put Adm No on a different row than subject titles — search nearby rows.
                if ($admCol === null) {
                    foreach ([$headerRow - 1, $headerRow + 1, $headerRow + 2, $headerRow - 2] as $tryRow) {
                        if ($tryRow < 1) {
                            continue;
                        }
                        $tryHeaders = $this->readHeaderRow($sheet, $tryRow);
                        $admCol = $this->findColumn($tryHeaders, ['adm. no', 'adm. no.', 'adm no', 'admission no', 'admission no.', 'enrl', 'enrol', 'adm']);
                        if ($admCol !== null) {
                            $headerRow = $tryRow;
                            $headers = $tryHeaders;
                            break;
                        }
                    }
                }
                if ($admCol === null) {
                    $sheetStats[] = [
                        'sheet' => $title,
                        'kind' => $kind,
                        'exam' => $exam->name,
                        'exam_id' => $exam->id,
                        'skipped' => 'exam created but no Adm. No. column',
                        'headers' => array_values(array_filter($headers)),
                        'marks_written' => 0,
                    ];
                    continue;
                }

                $subjectColumns = in_array($kind, ['hy', 'annual'], true)
                    ? $this->resolveBoardSubjectColumns($sheet, $headerRow, $headers)
                    : $this->resolveSubjectColumns($headers);

                if ($subjectColumns === [] && in_array($kind, ['hy', 'annual'], true)) {
                    $subjectColumns = $this->resolveBoardSubjectColumnsFallback($sheet, $headerRow);
                }

                if ($subjectColumns === []) {
                    $sheetStats[] = [
                        'sheet' => $title,
                        'kind' => $kind,
                        'term' => $sheetTerm->name,
                        'exam' => $exam->name,
                        'exam_id' => $exam->id,
                        'skipped' => 'exam created but no subject columns matched — check HY/ANNU header rows',
                        'headers' => array_values(array_filter($headers)),
                        'marks_written' => 0,
                    ];
                    continue;
                }

                $schedulesBySubjectId = [];
                foreach ($subjectColumns as $info) {
                    /** @var Subject $subject */
                    $subject = $info['subject'];
                    $schedule = ExamSchedule::query()->firstOrNew([
                        'exam_id' => $exam->id,
                        'school_class_id' => $class->id,
                        'subject_id' => $subject->id,
                    ]);
                    $policyMax = $defaultMax;
                    if (! $schedule->exists || ! $schedule->max_marks || ((float) $schedule->max_marks === 100.0 && $policyMax < 100)) {
                        $schedule->max_marks = $policyMax;
                    } else {
                        $schedule->max_marks = $policyMax;
                    }
                    if (! $schedule->exists) {
                        $schedule->date = now()->toDateString();
                    }
                    $schedule->save();
                    $schedulesBySubjectId[$subject->id] = $schedule;
                }

                $sheetMarks = 0;
                $sheetStudents = 0;
                $highestRow = (int) $sheet->getHighestDataRow();

                for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
                    // Skip oral/written sub-header rows.
                    if ($this->looksLikeSubHeaderRow($sheet, $r, $colCount = count($headers) ?: 20)) {
                        continue;
                    }

                    $admLetter = Coordinate::stringFromColumnIndex($admCol);
                    $enrol = trim((string) $sheet->getCell($admLetter.$r)->getCalculatedValue());
                    if ($enrol === '') {
                        $any = false;
                        for ($c = 1; $c <= min(3, max(array_keys($headers) ?: [3])); $c++) {
                            $letter = Coordinate::stringFromColumnIndex($c);
                            if (trim((string) $sheet->getCell($letter.$r)->getCalculatedValue()) !== '') {
                                $any = true;
                                break;
                            }
                        }
                        if (! $any) {
                            break;
                        }
                        continue;
                    }
                    if (is_numeric($enrol) && str_contains((string) $enrol, '.')) {
                        $enrol = (string) (int) round((float) $enrol);
                    }

                    $total++;
                    $sheetStudents++;
                    $studentId = $studentsByAdm[$enrol] ?? null;
                    if (! $studentId) {
                        $failed++;
                        $this->pushStudentNotFound(
                            $log, $now, $title, $r, $enrol, $class->name, $examName,
                            $failedRowsBuffer, $failedLogsBuffer, $failedRowsResponse
                        );
                        continue;
                    }

                    foreach ($subjectColumns as $info) {
                        $parsedMark = $this->readSubjectMarkCell($sheet, $r, $info);
                        if ($parsedMark['skip']) {
                            continue;
                        }

                        /** @var ExamSchedule $schedule */
                        $schedule = $schedulesBySubjectId[$info['subject']->id];
                        if (! $parsedMark['absent'] && $parsedMark['value'] !== null && $parsedMark['value'] > (float) $schedule->max_marks) {
                            $schedule->max_marks = max((float) $schedule->max_marks, $parsedMark['value'], $defaultMax);
                            $schedule->save();
                        }

                        $marksBuffer[] = [
                            'exam_schedule_id' => $schedule->id,
                            'student_id' => $studentId,
                            'marks_obtained' => $parsedMark['absent'] ? null : $parsedMark['value'],
                            'is_absent' => $parsedMark['absent'] ? 1 : 0,
                            'remarks' => $parsedMark['absent']
                                ? 'Absent (Ab) — imported from '.$title
                                : 'Imported from class term workbook ('.$title.')',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $sheetMarks++;
                        $marksWritten++;
                    }

                    $success++;

                    if (count($marksBuffer) >= 400) {
                        $this->flushMarks($marksBuffer);
                    }
                }

                $sheetStats[] = [
                    'sheet' => $title,
                    'kind' => $kind,
                    'term' => $sheetTerm->name,
                    'exam' => $examName,
                    'exam_id' => $exam->id,
                    'default_max_marks' => $defaultMax,
                    'subjects' => array_values(array_unique(array_map(fn ($s) => $s['subject']->name, $subjectColumns))),
                    'students' => $sheetStudents,
                    'marks_written' => $sheetMarks,
                ];
            }

            $this->flushMarks($marksBuffer);
        });

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        foreach (array_chunk($failedRowsBuffer, 250) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }
        foreach (array_chunk($failedLogsBuffer, 250) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }

        $log->update([
            'total_rows' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);

        $message = implode(' | ', array_filter([
            'Session: '.$session->name,
            'Term: '.$term->name,
            'Class: '.($class?->name ?? 'All classes (from sheet rows)'),
            'Sheets processed: '.count($sheetStats),
            'Student-rows seen: '.$total,
            'Marks cells written: '.$marksWritten,
            'Co-scholastic grades written: '.$gradesWritten,
            'Failed (student not found): '.$failed,
            $ignoredSheets !== [] ? 'Ignored sheets: '.implode(', ', $ignoredSheets) : null,
        ]));

        return response()->json([
            'log' => $log->fresh(),
            'failed_rows' => array_slice($failedRowsResponse, 0, 200),
            'stats' => [
                'session' => $session->name,
                'term' => $term->name,
                'class' => $class?->name ?? 'All classes (from sheet rows)',
                'sheets' => $sheetStats,
                'sheets_ignored' => $ignoredSheets,
                'exams' => array_values(array_unique(array_merge(
                array_values($examsTouched),
                [$boardExam->name]
            ))),
                'marks_written' => $marksWritten,
                'grades_written' => $gradesWritten,
                'board_exam_ensured' => $boardExam->name,
                'note' => 'GRADE sheets save as co-scholastic. Ab = absent (not zero). HY/ANNU exams are always created for the workbook term. TEST totals are calculated on the report card.',
            ],
            'message' => $message,
        ]);
    }

    /** @param  list<array<string, mixed>>  $buffer */
    private function flushMarks(array &$buffer): void
    {
        if ($buffer === []) {
            return;
        }
        foreach (array_chunk($buffer, 200) as $chunk) {
            Mark::upsert(
                $chunk,
                ['exam_schedule_id', 'student_id'],
                ['marks_obtained', 'is_absent', 'remarks', 'updated_at']
            );
        }
        $buffer = [];
    }

    /**
     * Detects/parses the new client "wide" marksheet layout: a header row with subject
     * names (row N) followed immediately by a component row (row N+1) of PT1/NB1/SEA1/
     * TOT(20)/HY-or-ANNU(80)/TOT(100) per subject. TOT columns are computed sums and are
     * intentionally skipped — only PT1/NB1/SEA1/HY(or ANNU) carry real marks.
     *
     * @param  array<int, string>  $subjectRow
     * @param  array<int, string>  $componentRow
     * @return list<array{subject: Subject, pt_col: ?int, pt_term: ?int, nb_col: ?int, nb_term: ?int, sea_col: ?int, sea_term: ?int, board_col: ?int, board_kind: ?string, board_term: ?int}>
     */
    private function buildWideSubjectBlocks(array $subjectRow, array $componentRow): array
    {
        $allSubjects = Subject::query()->get(['id', 'name']);
        $byLower = [];
        foreach ($allSubjects as $subject) {
            $byLower[$this->normalizeHeader($subject->name)] = $subject;
        }

        $maxCol = max(array_keys($subjectRow + $componentRow) ?: [1]);
        $blocks = [];
        $current = null;

        for ($c = 1; $c <= $maxCol; $c++) {
            $top = $subjectRow[$c] ?? '';
            $sub = $this->stripHeaderNoise($componentRow[$c] ?? '');

            $isIdentity = $top !== '' && (
                in_array($top, self::SKIP_HEADER_KEYS, true)
                || $top === 'class'
                || str_starts_with($top, 'roll')
                || str_starts_with($top, 'adm')
            );
            if ($isIdentity) {
                continue;
            }

            if ($top !== '') {
                $subject = $this->matchSubject($top, $byLower);
                if ($subject) {
                    if ($current) {
                        $blocks[] = $current;
                    }
                    $current = [
                        'subject' => $subject,
                        'pt_col' => null, 'pt_term' => null,
                        'nb_col' => null, 'nb_term' => null,
                        'sea_col' => null, 'sea_term' => null,
                        'board_col' => null, 'board_kind' => null, 'board_term' => null,
                    ];
                }
            }

            if (! $current) {
                continue;
            }

            if ($sub === 'pt1') {
                $current['pt_col'] = $c;
                $current['pt_term'] = 1;
            } elseif ($sub === 'pt2') {
                $current['pt_col'] = $c;
                $current['pt_term'] = 2;
            } elseif ($sub === 'nb1') {
                $current['nb_col'] = $c;
                $current['nb_term'] = 1;
            } elseif ($sub === 'nb2') {
                $current['nb_col'] = $c;
                $current['nb_term'] = 2;
            } elseif ($sub === 'sea1') {
                $current['sea_col'] = $c;
                $current['sea_term'] = 1;
            } elseif ($sub === 'sea2') {
                $current['sea_col'] = $c;
                $current['sea_term'] = 2;
            } elseif ($sub === 'hy') {
                $current['board_col'] = $c;
                $current['board_kind'] = 'hy';
                $current['board_term'] = 1;
            } elseif (str_starts_with($sub, 'annu')) {
                $current['board_col'] = $c;
                $current['board_kind'] = 'annual';
                $current['board_term'] = 2;
            }
            // 'tot' columns (TOT(20)/TOT(100)) are computed sums — intentionally not matched above.
        }
        if ($current) {
            $blocks[] = $current;
        }

        $subjectCount = count($blocks);
        $hasBoard = false;
        foreach ($blocks as $b) {
            if ($b['board_col']) {
                $hasBoard = true;
                break;
            }
        }

        return ($subjectCount >= 2 && $hasBoard) ? $blocks : [];
    }

    /** Strips "(10)"/"(05)" style noise and whitespace from an already-normalized header label. */
    private function stripHeaderNoise(string $normalized): string
    {
        $s = preg_replace('/\(.*?\)/', '', $normalized) ?? $normalized;
        $s = preg_replace('/\s+/', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * Imports one "wide" marksheet (see buildWideSubjectBlocks docblock): PT1/NB1/SEA1 and
     * HY-or-ANNU each become their own exam; class is read per-row so one sheet can cover
     * every class in the school.
     *
     * @param  array{header_row: int, blocks: array, adm_col: int, class_col: int}  $info
     * @param  array<string, int>  $studentsByAdm
     * @param  list<array<string, mixed>>  $failedRowsResponse
     * @param  list<array<string, mixed>>  $failedRowsBuffer
     * @param  list<array<string, mixed>>  $failedLogsBuffer
     * @param  list<array<string, mixed>>  $marksBuffer
     * @param  array<int, string>  $examsTouched
     * @return array<string, mixed>
     */
    private function importWideSubjectSheet(
        Worksheet $sheet,
        string $title,
        array $info,
        AcademicSession $session,
        float $fallbackMax,
        array $studentsByAdm,
        int &$total,
        int &$success,
        int &$failed,
        int &$marksWritten,
        array &$failedRowsResponse,
        array &$failedRowsBuffer,
        array &$failedLogsBuffer,
        array &$marksBuffer,
        array &$examsTouched,
        ImportExportLog $log,
        string $now
    ): array {
        $headerRow = $info['header_row'];
        $blocks = $info['blocks'];
        $admCol = $info['adm_col'];
        $classCol = $info['class_col'];

        $kindTerms = [];
        foreach ($blocks as $b) {
            if ($b['pt_col']) {
                $kindTerms['pt'] = $b['pt_term'];
            }
            if ($b['nb_col']) {
                $kindTerms['nb'] = $b['nb_term'];
            }
            if ($b['sea_col']) {
                $kindTerms['sea'] = $b['sea_term'];
            }
            if ($b['board_col']) {
                $kindTerms[$b['board_kind']] = $b['board_term'];
            }
        }

        $exams = [];
        foreach ($kindTerms as $kind => $termNo) {
            $sheetTerm = $this->ensureTerm($session, $termNo);
            $examName = $this->canonicalExamName($kind, $termNo, '');
            $defaultMax = $this->defaultMaxForKind($kind, $fallbackMax);
            $exam = $this->resolveOrCreateExam($session, $kind, $termNo, $examName, $defaultMax);
            $this->attachExamToTerm($exam, $sheetTerm, $kind);
            $examsTouched[$exam->id] = $exam->name;
            $exams[$kind] = ['exam' => $exam, 'default_max' => $defaultMax];
        }

        $scheduleCache = [];
        $sheetMarks = 0;
        $sheetStudents = 0;
        $highestRow = (int) $sheet->getHighestDataRow();
        $admLetter = Coordinate::stringFromColumnIndex($admCol);
        $classLetter = Coordinate::stringFromColumnIndex($classCol);

        for ($r = $headerRow + 2; $r <= $highestRow; $r++) {
            $enrol = trim((string) $sheet->getCell($admLetter.$r)->getCalculatedValue());
            if ($enrol === '') {
                $any = false;
                for ($c = 1; $c <= 4; $c++) {
                    $letter = Coordinate::stringFromColumnIndex($c);
                    if (trim((string) $sheet->getCell($letter.$r)->getCalculatedValue()) !== '') {
                        $any = true;
                        break;
                    }
                }
                if (! $any) {
                    break;
                }
                continue;
            }
            if (is_numeric($enrol) && str_contains($enrol, '.')) {
                $enrol = (string) (int) round((float) $enrol);
            }

            $total++;
            $sheetStudents++;
            $studentId = $studentsByAdm[$enrol] ?? null;
            if (! $studentId) {
                $failed++;
                $this->pushStudentNotFound(
                    $log, $now, $title, $r, $enrol, '', 'Marksheet',
                    $failedRowsBuffer, $failedLogsBuffer, $failedRowsResponse
                );
                continue;
            }

            $classRaw = trim((string) $sheet->getCell($classLetter.$r)->getCalculatedValue());
            if (is_numeric($classRaw) && str_contains($classRaw, '.')) {
                $classRaw = (string) (int) round((float) $classRaw);
            }
            $class = $this->resolveClass($this->normalizeClassToken($classRaw));
            if (! $class) {
                $failed++;
                $this->pushRowError(
                    $log, $now, $title, $r, $enrol, "Unrecognized class '{$classRaw}' for admission no. {$enrol}.",
                    $failedRowsBuffer, $failedLogsBuffer, $failedRowsResponse
                );
                continue;
            }

            foreach ($blocks as $block) {
                $subject = $block['subject'];
                $cols = [
                    'pt' => $block['pt_col'],
                    'nb' => $block['nb_col'],
                    'sea' => $block['sea_col'],
                ];
                if ($block['board_col']) {
                    $cols[$block['board_kind']] = $block['board_col'];
                }

                foreach ($cols as $kind => $col) {
                    if (! $col || ! isset($exams[$kind])) {
                        continue;
                    }
                    $examInfo = $exams[$kind];
                    $cacheKey = $examInfo['exam']->id.'-'.$class->id.'-'.$subject->id;
                    if (! isset($scheduleCache[$cacheKey])) {
                        $schedule = ExamSchedule::query()->firstOrNew([
                            'exam_id' => $examInfo['exam']->id,
                            'school_class_id' => $class->id,
                            'subject_id' => $subject->id,
                        ]);
                        $schedule->max_marks = $examInfo['default_max'];
                        if (! $schedule->exists) {
                            $schedule->date = now()->toDateString();
                        }
                        $schedule->save();
                        $scheduleCache[$cacheKey] = $schedule;
                    }
                    /** @var ExamSchedule $schedule */
                    $schedule = $scheduleCache[$cacheKey];

                    $parsedMark = $this->parseMarkCell($sheet, $col, $r);
                    if ($parsedMark['skip']) {
                        continue;
                    }
                    if (! $parsedMark['absent'] && $parsedMark['value'] !== null && $parsedMark['value'] > (float) $schedule->max_marks) {
                        $schedule->max_marks = max((float) $schedule->max_marks, $parsedMark['value'], $examInfo['default_max']);
                        $schedule->save();
                    }

                    $marksBuffer[] = [
                        'exam_schedule_id' => $schedule->id,
                        'student_id' => $studentId,
                        'marks_obtained' => $parsedMark['absent'] ? null : $parsedMark['value'],
                        'is_absent' => $parsedMark['absent'] ? 1 : 0,
                        'remarks' => $parsedMark['absent']
                            ? 'Absent (Ab) — imported from '.$title
                            : 'Imported from class term workbook ('.$title.')',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $sheetMarks++;
                    $marksWritten++;
                }
            }

            $success++;

            if (count($marksBuffer) >= 400) {
                $this->flushMarks($marksBuffer);
            }
        }

        return [
            'sheet' => $title,
            'kind' => 'marksheet',
            'exams' => array_values(array_map(fn ($e) => $e['exam']->name, $exams)),
            'subjects' => array_values(array_unique(array_map(fn ($b) => $b['subject']->name, $blocks))),
            'students' => $sheetStudents,
            'marks_written' => $sheetMarks,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $failedRowsBuffer
     * @param  list<array<string, mixed>>  $failedLogsBuffer
     * @param  list<array<string, mixed>>  $failedRowsResponse
     */
    private function pushRowError(
        ImportExportLog $log,
        string $now,
        string $title,
        int $row,
        string $identifier,
        string $msg,
        array &$failedRowsBuffer,
        array &$failedLogsBuffer,
        array &$failedRowsResponse
    ): void {
        $failedRowsBuffer[] = [
            'import_export_log_id' => $log->id,
            'row_number' => $row,
            'row_data' => json_encode(['adm' => $identifier, 'sheet' => $title], JSON_THROW_ON_ERROR),
            'error_message' => $msg,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedLogsBuffer[] = [
            'import_export_log_id' => $log->id,
            'row_number' => $row,
            'status' => 'Failed',
            'identifier' => $identifier,
            'summary' => json_encode(['sheet' => $title], JSON_THROW_ON_ERROR),
            'error_message' => $msg,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowsResponse[] = [
            'id' => $title.'-'.$row,
            'sheet' => $title,
            'row_number' => $row,
            'error_message' => $msg,
        ];
    }

    /**
     * @param  list<string>  $sheetNames
     * @return array{0: list<string>, 1: list<string>}
     */
    private function partitionSheets(array $sheetNames): array
    {
        $data = [];
        $ignored = [];
        foreach ($sheetNames as $title) {
            $kind = $this->sheetKind($title)['kind'];
            if ($kind === 'ignore') {
                $ignored[] = $title;
            } else {
                $data[] = $title;
            }
        }

        return [$data, $ignored];
    }

    /** @return array{kind: string, term: int|null} */
    private function sheetKind(string $title): array
    {
        $upper = strtoupper(trim($title));
        $compact = preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';

        if ($upper === '' || $upper === 'SHEET2' || str_contains($upper, 'STD DETAIL')) {
            return ['kind' => 'ignore', 'term' => null];
        }
        if (str_contains($upper, 'ATTD') || str_contains($upper, 'ATTEND')) {
            return ['kind' => 'ignore', 'term' => null];
        }
        if (str_contains($upper, 'GRADE') || str_ends_with($compact, 'GRADE')) {
            return ['kind' => 'grade', 'term' => null];
        }
        if (preg_match('/PT2$/', $compact) || preg_match('/\bPT-?\s*2\b/', $upper)) {
            return ['kind' => 'pt', 'term' => 2];
        }
        if (preg_match('/PT1$/', $compact) || preg_match('/\bPT-?\s*1\b/', $upper)) {
            return ['kind' => 'pt', 'term' => 1];
        }
        if (preg_match('/NB2$/', $compact) || preg_match('/\bNB-?\s*2\b/', $upper)) {
            return ['kind' => 'nb', 'term' => 2];
        }
        if (preg_match('/NB1$/', $compact) || preg_match('/\bNB-?\s*1\b/', $upper)) {
            return ['kind' => 'nb', 'term' => 1];
        }
        if (preg_match('/SEA2$/', $compact) || preg_match('/\bSEA-?\s*2\b/', $upper)) {
            return ['kind' => 'sea', 'term' => 2];
        }
        if (preg_match('/SEA1$/', $compact) || preg_match('/\bSEA-?\s*1\b/', $upper)) {
            return ['kind' => 'sea', 'term' => 1];
        }
        // HY / Half Yearly — compact form catches "CLASS1HY", "CLASS_1_HY", "H.Y."
        if (
            preg_match('/HY$/', $compact)
            || str_contains($compact, 'HALFYEAR')
            || preg_match('/(^|[^A-Z0-9])H\.?Y\.?([^A-Z0-9]|$)/', $upper)
            || str_contains($upper, 'HALF YEAR')
            || str_contains($upper, 'HALF-YEAR')
        ) {
            return ['kind' => 'hy', 'term' => 1];
        }
        if (
            str_contains($compact, 'ANNU')
            || str_contains($compact, 'ANNUAL')
            || str_contains($upper, 'ANNU')
            || str_contains($upper, 'ANNUAL')
        ) {
            return ['kind' => 'annual', 'term' => 2];
        }
        if (str_contains($upper, 'UNIT TEST') || preg_match('/\bUNIT\s*[12]\b/', $upper) || preg_match('/UNIT[12]$/', $compact)) {
            return ['kind' => 'unit', 'term' => null];
        }

        return ['kind' => 'ignore', 'term' => null];
    }

    /** @param  list<string>  $sheetTitles */
    private function detectWorkbookTerm(string $filename, array $sheetTitles): int
    {
        $hay = strtoupper($filename.' '.implode(' ', $sheetTitles));
        if (preg_match('/TERM[_\s-]*2/', $hay) || preg_match('/\bPT-?\s*2\b/', $hay) || preg_match('/\bANNU/', $hay)) {
            return 2;
        }
        if (preg_match('/TERM[_\s-]*1/', $hay) || preg_match('/\bPT-?\s*1\b/', $hay) || preg_match('/\bHY\b/', $hay)) {
            return 1;
        }

        return 1;
    }

    private function ensureTerm(AcademicSession $session, int $termNumber): AcademicTerm
    {
        $name = 'Term-'.$termNumber;

        return AcademicTerm::query()->firstOrCreate(
            ['academic_session_id' => $session->id, 'name' => $name],
            ['sort_order' => $termNumber, 'max_marks' => 100]
        );
    }

    private function canonicalExamName(string $kind, int $termNumber, string $metaName): string
    {
        return match ($kind) {
            'pt' => 'PT-'.$termNumber,
            'nb' => 'NB-'.$termNumber,
            'sea' => 'SEA-'.$termNumber,
            'hy' => 'Half Yearly',
            'annual' => 'ANNUAL EXAM',
            'unit' => $metaName !== '' ? $metaName : 'UNIT TEST '.$termNumber,
            default => $metaName !== '' ? $metaName : strtoupper($kind),
        };
    }

    private function resolveOrCreateExam(
        AcademicSession $session,
        string $kind,
        int $termNumber,
        string $examName,
        float $defaultMax
    ): Exam {
        $query = Exam::query()->where('academic_session_id', $session->id);

        if ($kind === 'hy') {
            $existing = (clone $query)->where(function ($q) {
                $q->where('name', 'like', '%Half Year%')
                    ->orWhere('name', 'like', '%Half-Year%')
                    ->orWhere('name', 'like', '%Mid Term%');
            })->orderBy('id')->first();
        } elseif ($kind === 'annual') {
            $existing = (clone $query)->where(function ($q) {
                $q->where('name', 'like', '%Annual%')
                    ->orWhere('name', 'like', '%ANNU%');
            })->orderBy('id')->first();
        } else {
            $existing = (clone $query)->where('name', $examName)->first();
        }

        if ($existing) {
            $existing->update([
                'max_marks' => $defaultMax,
                'total_marks' => $existing->total_marks ?: $defaultMax,
                'type' => $existing->type ?: $this->examTypeForKind($kind),
            ]);

            return $existing->fresh();
        }

        return Exam::query()->create([
            'name' => $kind === 'hy' ? 'Half Yearly Exam '.$session->name : $examName,
            'academic_session_id' => $session->id,
            'type' => $this->examTypeForKind($kind),
            'status' => 'Scheduled',
            'max_marks' => $defaultMax,
            'total_marks' => $defaultMax,
            'passing_marks' => round($defaultMax * 0.33, 2),
        ]);
    }

    private function examTypeForKind(string $kind): string
    {
        return match ($kind) {
            'pt' => 'Periodic Test',
            'nb' => 'Notebook',
            'sea' => 'SEA',
            'hy' => 'Half Yearly',
            'annual' => 'Annual',
            'unit' => 'Unit Test',
            default => 'Exam',
        };
    }

    private function defaultMaxForKind(string $kind, float $fallback): float
    {
        return match ($kind) {
            'pt' => 10.0,
            'nb' => 5.0,
            'sea' => 5.0,
            'hy', 'annual' => 80.0,
            default => $fallback,
        };
    }

    private function attachExamToTerm(Exam $exam, AcademicTerm $term, string $kind): void
    {
        if ($kind === 'unit') {
            return;
        }

        $internal = in_array($kind, ['pt', 'nb', 'sea'], true);
        $sort = match ($kind) {
            'pt' => 10,
            'nb' => 20,
            'sea' => 30,
            'hy', 'annual' => 40,
            default => 50,
        };

        $exam->update([
            'academic_term_id' => $term->id,
            'is_internal_component' => $internal,
            'counts_toward_term' => true,
            'sort_order' => $sort,
        ]);
    }

    /**
     * @param  array<string, int>  $studentsByAdm
     * @param  list<array<string, mixed>>  $failedRowsBuffer
     * @param  list<array<string, mixed>>  $failedLogsBuffer
     * @param  list<array<string, mixed>>  $failedRowsResponse
     * @return array<string, mixed>
     */
    private function importGradeSheet(
        Worksheet $sheet,
        string $title,
        AcademicTerm $term,
        array $studentsByAdm,
        ImportExportLog $log,
        string $now,
        int &$total,
        int &$success,
        int &$failed,
        int &$gradesWritten,
        array &$failedRowsResponse,
        array &$failedRowsBuffer,
        array &$failedLogsBuffer
    ): array {
        $headerRow = $this->findHeaderRow($sheet);
        if ($headerRow === null) {
            return ['sheet' => $title, 'kind' => 'grade', 'skipped' => 'no header row', 'grades_written' => 0];
        }

        $headers = $this->readHeaderRow($sheet, $headerRow);
        $admCol = $this->findColumn($headers, ['adm. no', 'adm. no.', 'adm no', 'admission no', 'admission no.', 'enrl', 'enrol']);
        if ($admCol === null) {
            return ['sheet' => $title, 'kind' => 'grade', 'skipped' => 'no Adm. No. column', 'grades_written' => 0];
        }

        $areaColumns = [];
        foreach ($headers as $col => $label) {
            $key = $this->normalizeHeader($label);
            $key = str_replace(['\\'], ['/'], $key);
            $area = self::GRADE_AREA_MAP[$key] ?? null;
            if ($area) {
                $areaColumns[(int) $col] = $area;
            }
        }
        if ($areaColumns === []) {
            return [
                'sheet' => $title,
                'kind' => 'grade',
                'term' => $term->name,
                'skipped' => 'no co-scholastic columns matched',
                'headers' => array_values(array_filter($headers)),
                'grades_written' => 0,
            ];
        }

        $sheetGrades = 0;
        $sheetStudents = 0;
        $highestRow = (int) $sheet->getHighestDataRow();

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $admLetter = Coordinate::stringFromColumnIndex($admCol);
            $enrol = trim((string) $sheet->getCell($admLetter.$r)->getCalculatedValue());
            if ($enrol === '') {
                $any = false;
                for ($c = 1; $c <= 3; $c++) {
                    $letter = Coordinate::stringFromColumnIndex($c);
                    if (trim((string) $sheet->getCell($letter.$r)->getCalculatedValue()) !== '') {
                        $any = true;
                        break;
                    }
                }
                if (! $any) {
                    break;
                }
                continue;
            }
            if (is_numeric($enrol) && str_contains((string) $enrol, '.')) {
                $enrol = (string) (int) round((float) $enrol);
            }

            $total++;
            $sheetStudents++;
            $studentId = $studentsByAdm[$enrol] ?? null;
            if (! $studentId) {
                $failed++;
                $this->pushStudentNotFound(
                    $log, $now, $title, $r, $enrol, '', 'GRADE',
                    $failedRowsBuffer, $failedLogsBuffer, $failedRowsResponse
                );
                continue;
            }

            foreach ($areaColumns as $col => $area) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $raw = trim((string) $sheet->getCell($letter.$r)->getCalculatedValue());
                if ($raw === '' || strcasecmp($raw, 'None') === 0) {
                    continue;
                }
                CoScholasticGrade::query()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_term_id' => $term->id,
                        'area' => $area,
                    ],
                    ['grade' => mb_substr($raw, 0, 16)]
                );
                $sheetGrades++;
                $gradesWritten++;
            }
            $success++;
        }

        return [
            'sheet' => $title,
            'kind' => 'grade',
            'term' => $term->name,
            'exam' => null,
            'students' => $sheetStudents,
            'grades_written' => $sheetGrades,
            'note' => 'Saved as co-scholastic (not an exam)',
        ];
    }

    /**
     * @return array{skip: bool, absent: bool, value: float|null}
     */
    private function readSubjectMarkCell(Worksheet $sheet, int $row, array $info): array
    {
        // Board sheets may provide oral + written (+ optional total).
        if (! empty($info['total_col']) || ! empty($info['oral_col']) || ! empty($info['written_col'])) {
            if (! empty($info['total_col'])) {
                $parsed = $this->parseMarkCell($sheet, $info['total_col'], $row);
                if (! $parsed['skip']) {
                    return $parsed;
                }
            }
            $oral = ! empty($info['oral_col']) ? $this->parseMarkCell($sheet, $info['oral_col'], $row) : ['skip' => true, 'absent' => false, 'value' => null];
            $written = ! empty($info['written_col']) ? $this->parseMarkCell($sheet, $info['written_col'], $row) : ['skip' => true, 'absent' => false, 'value' => null];

            if (($oral['absent'] ?? false) || ($written['absent'] ?? false)) {
                // If either component is Ab and the other is blank/Ab → absent for the subject.
                if (($oral['skip'] || $oral['absent']) && ($written['skip'] || $written['absent'])) {
                    return ['skip' => false, 'absent' => true, 'value' => null];
                }
            }

            $sum = 0.0;
            $any = false;
            foreach ([$oral, $written] as $part) {
                if ($part['skip'] || $part['absent']) {
                    continue;
                }
                $sum += (float) $part['value'];
                $any = true;
            }
            if (! $any) {
                return ['skip' => true, 'absent' => false, 'value' => null];
            }

            return ['skip' => false, 'absent' => false, 'value' => round($sum, 2)];
        }

        return $this->parseMarkCell($sheet, (int) $info['col'], $row);
    }

    /**
     * @return array{skip: bool, absent: bool, value: float|null}
     */
    private function parseMarkCell(Worksheet $sheet, int $col, int $row): array
    {
        $letter = Coordinate::stringFromColumnIndex($col);
        $raw = $sheet->getCell($letter.$row)->getCalculatedValue();
        if ($raw === null || trim((string) $raw) === '' || strcasecmp(trim((string) $raw), 'None') === 0) {
            return ['skip' => true, 'absent' => false, 'value' => null];
        }
        $text = trim((string) $raw);
        if (preg_match('/^ab\.?$/i', $text) || strcasecmp($text, 'absent') === 0) {
            return ['skip' => false, 'absent' => true, 'value' => null];
        }
        if (! is_numeric($raw) && ! is_numeric($text)) {
            return ['skip' => true, 'absent' => false, 'value' => null];
        }
        $value = round((float) $raw, 2);
        if ($value < 0) {
            return ['skip' => true, 'absent' => false, 'value' => null];
        }

        return ['skip' => false, 'absent' => false, 'value' => $value];
    }

    private function looksLikeSubHeaderRow(Worksheet $sheet, int $row, int $maxCol): bool
    {
        $joined = '';
        for ($c = 1; $c <= min(20, $maxCol); $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $joined .= ' '.$this->normalizeHeader((string) $sheet->getCell($letter.$row)->getCalculatedValue());
        }

        return str_contains($joined, 'oral')
            || str_contains($joined, 'written')
            || preg_match('/\bwri\b/', $joined) === 1;
    }

    /**
     * @param  array<int, string>  $headers
     * @return list<array{subject: Subject, col?: int, total_col?: int, oral_col?: int, written_col?: int}>
     */
    private function resolveBoardSubjectColumns(Worksheet $sheet, int $headerRow, array $headers): array
    {
        $attempts = [];
        $attempts[] = [$headers, $this->readHeaderRow($sheet, $headerRow + 1)];
        if ($headerRow > 1) {
            $above = $this->readHeaderRow($sheet, $headerRow - 1);
            $attempts[] = [$above, $headers];
            $attempts[] = [$above, $this->readHeaderRow($sheet, $headerRow + 1)];
        }

        foreach ($attempts as [$subjectRow, $componentRow]) {
            $resolved = $this->buildBoardSubjectGroups($subjectRow, $componentRow);
            if ($resolved !== []) {
                return $resolved;
            }
        }

        // Flat subject columns on the admission header row.
        $flat = $this->resolveSubjectColumns($headers);
        $out = [];
        foreach ($flat as $col => $info) {
            $out[] = ['subject' => $info['subject'], 'col' => (int) $col];
        }
        if ($out !== []) {
            return $out;
        }

        if ($headerRow > 1) {
            $flatAbove = $this->resolveSubjectColumns($this->readHeaderRow($sheet, $headerRow - 1));
            foreach ($flatAbove as $col => $info) {
                $out[] = ['subject' => $info['subject'], 'col' => (int) $col];
            }
        }

        return $out;
    }

    /** @return list<array{subject: Subject, col?: int, total_col?: int, oral_col?: int, written_col?: int}> */
    private function resolveBoardSubjectColumnsFallback(Worksheet $sheet, int $headerRow): array
    {
        foreach ([$headerRow - 1, $headerRow, $headerRow + 1, $headerRow + 2] as $row) {
            if ($row < 1) {
                continue;
            }
            $flat = $this->resolveSubjectColumns($this->readHeaderRow($sheet, $row));
            if ($flat !== []) {
                $out = [];
                foreach ($flat as $col => $info) {
                    $out[] = ['subject' => $info['subject'], 'col' => (int) $col];
                }

                return $out;
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $subjectRow
     * @param  array<int, string>  $componentRow
     * @return list<array{subject: Subject, total_col?: int|null, oral_col?: int|null, written_col?: int|null}>
     */
    private function buildBoardSubjectGroups(array $subjectRow, array $componentRow): array
    {
        $hasComponent = false;
        foreach ($componentRow as $label) {
            if (
                str_contains($label, 'oral')
                || str_contains($label, 'written')
                || str_starts_with($label, 'wri')
                || $label === 'total'
            ) {
                $hasComponent = true;
                break;
            }
        }

        $allSubjects = Subject::query()->get(['id', 'name']);
        $byLower = [];
        foreach ($allSubjects as $subject) {
            $byLower[$this->normalizeHeader($subject->name)] = $subject;
        }

        if (! $hasComponent) {
            return [];
        }

        $groups = [];
        $currentSubject = null;
        $bucket = null;
        $maxCol = max(array_keys($subjectRow + $componentRow) ?: [1]);

        for ($c = 1; $c <= $maxCol; $c++) {
            $top = $subjectRow[$c] ?? '';
            $sub = $componentRow[$c] ?? '';
            if ($top === '' && $sub === '') {
                continue;
            }
            if (in_array($top, self::SKIP_HEADER_KEYS, true) || str_starts_with($top, 'roll') || str_starts_with($top, 'adm') || $top === 'name') {
                // Keep current subject across identity columns only when they appear left of subjects.
                if ($currentSubject === null) {
                    continue;
                }
            }

            $subject = null;
            if ($top !== '' && ! in_array($top, ['oral', 'written', 'total', 'mo', 'mm'], true)
                && ! in_array($top, self::SKIP_HEADER_KEYS, true)
                && ! str_starts_with($top, 'roll')
                && ! str_starts_with($top, 'adm')
                && $top !== 'name'
            ) {
                $subject = $this->matchSubject($top, $byLower);
            }
            if ($subject) {
                if ($bucket && $currentSubject) {
                    $groups[] = $bucket;
                }
                $currentSubject = $subject;
                $bucket = ['subject' => $subject, 'oral_col' => null, 'written_col' => null, 'total_col' => null];
            }

            if (! $bucket) {
                continue;
            }

            if (str_contains($sub, 'oral') || $top === 'oral' || str_starts_with($sub, 'oral')) {
                $bucket['oral_col'] = $c;
            } elseif (
                str_contains($sub, 'written')
                || str_starts_with($sub, 'wri')
                || $top === 'written'
                || str_starts_with($top, 'wri')
            ) {
                $bucket['written_col'] = $c;
            } elseif ($sub === 'total' || $top === 'total' || str_contains($sub, 'total')) {
                $bucket['total_col'] = $c;
            } elseif ($subject && $sub === '' && empty($bucket['total_col']) && empty($bucket['oral_col'])) {
                $bucket['total_col'] = $c;
            }
        }
        if ($bucket && $currentSubject) {
            $groups[] = $bucket;
        }

        $byId = [];
        foreach ($groups as $g) {
            if ($g['oral_col'] || $g['written_col'] || $g['total_col']) {
                $byId[$g['subject']->id] = $g;
            }
        }

        return array_values($byId);
    }

    /** @param  array<int, string>  $headers */
    private function readHeaderRow(Worksheet $sheet, int $row): array
    {
        $headers = [];
        try {
            $highestCol = $sheet->getHighestDataColumn($row);
        } catch (\Throwable) {
            return [];
        }
        $colCount = Coordinate::columnIndexFromString($highestCol);
        // Client HY/ANNU sheets can be wide (oral+written per subject).
        $colCount = max($colCount, 30);
        for ($c = 1; $c <= $colCount; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $headers[$c] = $this->normalizeHeader((string) $sheet->getCell($letter.$row)->getCalculatedValue());
        }

        return $headers;
    }

    /** @return array{class_token: string, session_label: string, exam_name: string} */
    private function readSheetMeta(Worksheet $sheet, string $filename, string $sheetTitle): array
    {
        $classToken = '';
        $sessionLabel = '';
        $examName = '';

        for ($c = 1; $c <= 8; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $val = trim((string) $sheet->getCell($letter.'3')->getCalculatedValue());
            if ($val === '') {
                continue;
            }
            $upper = strtoupper($val);
            if ($classToken === '' && (str_contains($upper, 'CLASS') || isset(self::CLASS_MAP[$this->normalizeClassToken($val)]))) {
                $classToken = $this->extractClassToken($val);
            }
            if ($sessionLabel === '' && preg_match('/\d{4}\s*[-–]\s*\d{2,4}/', $val)) {
                $sessionLabel = $val;
            }
            if ($examName === '' && (
                str_contains($upper, 'UNIT')
                || str_contains($upper, 'TEST')
                || str_contains($upper, 'EXAM')
                || preg_match('/\b(PT|NB|SEA)-?\s*[12]\b/', $upper)
                || preg_match('/\bHY\b/', $upper)
                || preg_match('/\bANNU/', $upper)
                || str_contains($upper, 'HALF')
                || str_contains($upper, 'ANNUAL')
                || str_contains($upper, 'GRADE')
            )) {
                $examName = $val;
            }
        }

        if ($classToken === '' || $sessionLabel === '') {
            $base = pathinfo($filename, PATHINFO_FILENAME);
            if (preg_match('/CLASS[_\s-]*([A-Za-z0-9]+)/i', $base, $m)) {
                $classToken = $classToken !== '' ? $classToken : $this->normalizeClassToken($m[1]);
            }
            if (preg_match('/(\d{4})\s*[-–]\s*(\d{2,4})/', $base, $m)) {
                $sessionLabel = $sessionLabel !== '' ? $sessionLabel : $m[0];
            }
        }

        return [
            'class_token' => $this->normalizeClassToken($classToken),
            'session_label' => $sessionLabel,
            'exam_name' => $examName,
        ];
    }

    private function extractClassToken(string $value): string
    {
        if (preg_match('/CLASS\s*[-:]?\s*([A-Za-z0-9]+)/i', $value, $m)) {
            return $this->normalizeClassToken($m[1]);
        }

        return $this->normalizeClassToken($value);
    }

    private function normalizeClassToken(string $value): string
    {
        $token = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9]/', '', $token) ?? '';
    }

    private function resolveClass(string $token): ?SchoolClass
    {
        if ($token === '') {
            return null;
        }
        $name = self::CLASS_MAP[$token] ?? null;
        if ($name === null) {
            return null;
        }

        return SchoolClass::query()
            ->get(['id', 'name'])
            ->first(fn (SchoolClass $c) => mb_strtolower(trim($c->name)) === mb_strtolower($name));
    }

    private function resolveSession(string $label): ?AcademicSession
    {
        if ($label === '' || ! preg_match('/(\d{4})\s*[-–]\s*(\d{2,4})/', $label, $m)) {
            return AcademicSession::where('is_current', true)->first();
        }
        $start = (int) $m[1];
        $endRaw = $m[2];
        $end = strlen($endRaw) === 2 ? (int) (substr((string) $start, 0, 2).$endRaw) : (int) $endRaw;
        $full = sprintf('%04d-%04d', $start, $end);
        $short = sprintf('%04d-%02d', $start, $end % 100);

        $session = AcademicSession::query()
            ->where(function ($q) use ($full, $short, $start) {
                $q->where('name', $full)
                    ->orWhere('name', $short)
                    ->orWhere('name', 'like', $start.'-%');
            })
            ->orderByDesc('id')
            ->first();

        if ($session) {
            return $session;
        }

        return AcademicSession::query()->firstOrCreate(
            ['name' => $full],
            [
                'start_date' => sprintf('%04d-04-01', $start),
                'end_date' => sprintf('%04d-03-31', $end),
                'is_current' => false,
            ]
        );
    }

    private function findHeaderRow(Worksheet $sheet): ?int
    {
        $highest = min(12, (int) $sheet->getHighestDataRow());
        for ($r = 1; $r <= $highest; $r++) {
            $joined = '';
            for ($c = 1; $c <= 8; $c++) {
                $letter = Coordinate::stringFromColumnIndex($c);
                $joined .= ' '.$this->normalizeHeader((string) $sheet->getCell($letter.$r)->getCalculatedValue());
            }
            if (
                (str_contains($joined, 'adm') || str_contains($joined, 'admission') || str_contains($joined, 'enrol'))
                && (str_contains($joined, 'name') || str_contains($joined, 'roll'))
            ) {
                return $r;
            }
        }

        return 4;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['.', '_'], ['', ' '], $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  list<string>  $candidates
     */
    private function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($headers as $col => $label) {
            foreach ($candidates as $want) {
                $wantNorm = $this->normalizeHeader($want);
                if ($label === $wantNorm || str_contains($label, $wantNorm)) {
                    return (int) $col;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, array{subject: Subject, header: string, col: int}>
     */
    private function resolveSubjectColumns(array $headers): array
    {
        $allSubjects = Subject::query()->get(['id', 'name']);
        $byLower = [];
        foreach ($allSubjects as $subject) {
            $byLower[$this->normalizeHeader($subject->name)] = $subject;
        }

        $columns = [];
        foreach ($headers as $col => $label) {
            if ($label === '' || in_array($label, self::SKIP_HEADER_KEYS, true)) {
                continue;
            }
            if (str_starts_with($label, 'roll') || str_starts_with($label, 'adm') || $label === 'name') {
                continue;
            }
            if (in_array($label, ['oral', 'written', 'total'], true)) {
                continue;
            }

            $subject = $this->matchSubject($label, $byLower);
            if ($subject) {
                $columns[(int) $col] = ['subject' => $subject, 'header' => $label, 'col' => (int) $col];
            }
        }

        return $columns;
    }

    /** @param  array<string, Subject>  $byLower */
    private function matchSubject(string $label, array &$byLower): ?Subject
    {
        $key = $this->normalizeHeader($label);
        $key = str_replace(['\\'], ['/'], $key);
        // Strip trailing oral/written/total and max-mark noise ("english 80", "hindi (80)").
        $key = preg_replace('/\s+(oral|written|total)$/', '', $key) ?? $key;
        $key = preg_replace('/\s*\(?\s*\d{1,3}\s*\)?\s*$/', '', $key) ?? $key;
        $key = trim($key);

        if (isset($byLower[$key])) {
            return $byLower[$key];
        }

        $aliasKey = str_replace(' ', '', $key);
        $compactAliases = [];
        foreach (self::SUBJECT_ALIASES as $alias => $targets) {
            $compactAliases[str_replace(' ', '', $alias)] = $targets;
        }

        $targets = self::SUBJECT_ALIASES[$key] ?? $compactAliases[$aliasKey] ?? null;
        if ($targets) {
            foreach ($targets as $name) {
                $lower = $this->normalizeHeader($name);
                if (isset($byLower[$lower])) {
                    return $byLower[$lower];
                }
            }

            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $targets[0]) ?? 'SUB', 0, 10));
            // The name-based lookups above can still miss an existing subject whose name is
            // punctuated slightly differently than every known alias (e.g. DB has "S. St" while
            // the alias list assumes "S.ST"/"SST"). `code` is unique, so a blind firstOrCreate
            // keyed only on `name` would otherwise crash the whole import on that collision —
            // check by code first.
            $created = Subject::query()->where('code', $code)->first()
                ?? Subject::query()->firstOrCreate(['name' => $targets[0]], ['code' => $code]);
            $byLower[$this->normalizeHeader($created->name)] = $created;

            return $created;
        }

        foreach ($byLower as $name => $subject) {
            if (str_contains($key, $name) || str_contains($name, $key)) {
                return $subject;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $failedRowsBuffer
     * @param  list<array<string, mixed>>  $failedLogsBuffer
     * @param  list<array<string, mixed>>  $failedRowsResponse
     */
    private function pushStudentNotFound(
        ImportExportLog $log,
        string $now,
        string $title,
        int $row,
        string $enrol,
        string $className,
        string $examName,
        array &$failedRowsBuffer,
        array &$failedLogsBuffer,
        array &$failedRowsResponse
    ): void {
        $msg = "No student found for admission no. {$enrol}.";
        $failedRowsBuffer[] = [
            'import_export_log_id' => $log->id,
            'row_number' => $row,
            'row_data' => json_encode(['adm' => $enrol, 'sheet' => $title], JSON_THROW_ON_ERROR),
            'error_message' => $msg,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedLogsBuffer[] = [
            'import_export_log_id' => $log->id,
            'row_number' => $row,
            'status' => 'Failed',
            'identifier' => $enrol,
            'summary' => json_encode(['class' => $className, 'exam' => $examName, 'sheet' => $title], JSON_THROW_ON_ERROR),
            'error_message' => $msg,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $failedRowsResponse[] = [
            'id' => $title.'-'.$row,
            'sheet' => $title,
            'row_number' => $row,
            'error_message' => $msg,
        ];
    }
}
