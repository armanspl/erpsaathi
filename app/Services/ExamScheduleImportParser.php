<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses a school's existing printed exam-routine workbook into the same {date, is_holiday,
 * cells[]} shape the Exam Schedule grid UI already works with (see ExamScheduleSheetController
 * ::present()) — the result is meant to pre-fill that grid for review, never written to the
 * database directly (no section detail or holiday markers exist in the source file, so a blind
 * auto-save would risk silently wrong data — see ExamScheduleSheetController::importPreview()).
 *
 * Expected file shape: a header row ("Date/Days | [Sitting |] I | II | ... | VIII", or NUR/LKG/
 * UKG for a pre-primary sheet), then date-pairs — a real date in column A followed by one or
 * more "day name" continuation rows (plain text, not a merged cell) carrying additional subject
 * entries for the same date (e.g. an "Oral" second sitting) — then a free-text notes footer that
 * is simply not shaped like a grid row and is ignored. A workbook may have more than one such
 * sheet (e.g. a Primary sheet and a separate Pre-Primary sheet); all detected sheets are merged
 * by date into one result.
 */
class ExamScheduleImportParser
{
    /** Row index (0-based) after which we give up looking for a header row in a given sheet. */
    private const MAX_HEADER_SCAN_ROWS = 20;

    /** Hard safety cap on data rows read per sheet, in case a file has stray trailing content. */
    private const MAX_DATA_ROWS = 1000;

    /** Sheet-label alias -> the school-class name it should match (for grades a plain
     *  roman-numeral/digit comparison can't resolve, e.g. "NUR" for a class named "Nursery"). */
    private const CLASS_ALIASES = [
        'NUR' => 'NURSERY',
        'NURSERY' => 'NURSERY',
        'PP1' => 'NURSERY',
        'PP2' => 'LKG',
        'PP3' => 'UKG',
    ];

    /**
     * @return array{dates: list<array<string, mixed>>, warnings: array<string, mixed>}
     *
     * @throws \RuntimeException when a class-column label can't be mapped to a real class, or
     *                            when no sheet in the workbook looks like a schedule grid at all
     *                            — both are hard failures (nothing is returned to pre-fill)
     *                            rather than silently dropping data, per the school's request.
     */
    public function parse(string $path): array
    {
        $names = SpreadsheetImportReader::listSheetNames($path);
        $spreadsheet = SpreadsheetImportReader::loadSheetsOnly($path, $names);

        $schoolClasses = SchoolClass::all(['id', 'name']);
        $subjectLookup = $this->buildSubjectLookup();

        // Pass 1: detect every schedule-shaped sheet and resolve its class columns, collecting
        // every unmapped label across the whole workbook before deciding whether to proceed —
        // one clear error listing everything wrong, not a re-upload-and-retry loop.
        $grids = [];
        $unmapped = [];
        $classIdToLabel = [];
        foreach ($names as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            if (! $sheet) {
                continue;
            }
            $detected = $this->detectHeader($sheet);
            if ($detected === null) {
                continue;
            }
            [$headerRowIndex, $rows] = $detected;
            $classColumns = $this->resolveClassColumns($rows[$headerRowIndex], $schoolClasses, $unmapped, $name);
            foreach ($classColumns as $col) {
                $classIdToLabel[$col['class_id']] = $col['label'];
            }
            $grids[] = ['sheet' => $name, 'header_row_index' => $headerRowIndex, 'rows' => $rows, 'columns' => $classColumns];
        }

        if ($unmapped !== []) {
            $list = collect($unmapped)->map(fn ($u) => "\"{$u['label']}\" (sheet \"{$u['sheet']}\")")->implode(', ');
            $spreadsheet->disconnectWorksheets();

            throw new \RuntimeException("These column headers don't match any of your school's classes, so nothing was imported: {$list}. Add the matching class first, or fix the header text, then re-upload.");
        }

        if ($grids === []) {
            $spreadsheet->disconnectWorksheets();

            throw new \RuntimeException('Could not find an exam schedule grid in this file — expected a header row starting with "Date" somewhere in the first '.self::MAX_HEADER_SCAN_ROWS.' rows of a sheet.');
        }

        $warnings = [
            'unmatched_subjects' => [],
            'suspected_holidays' => [],
            'classes_without_sections' => [],
            'notes' => [
                'The source file has no section-level detail — each class\'s subject was applied to every active section of that class.',
                'The source file has no sitting start/end times — cell times were left blank. Add them manually if you want them shown on the printed schedule.',
                'No cells were marked as holidays — the source file has no holiday marker, so review the "possible holiday?" flags below and mark any real holidays yourself.',
            ],
        ];

        // Pass 2: walk each grid's data rows and accumulate entries per date, merging sheets
        // that share a date (e.g. the Primary and Pre-Primary sheets both list the same day).
        $byDate = [];
        $dateOrder = [];
        foreach ($grids as $grid) {
            $this->walkGrid($grid, $subjectLookup, $warnings, $byDate, $dateOrder);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $sectionsByClass = Section::all()->groupBy('school_class_id');
        $dates = [];
        foreach ($dateOrder as $dateStr) {
            $dates[] = $this->finalizeDate($dateStr, $byDate[$dateStr], $sectionsByClass, $classIdToLabel, $warnings);
        }
        usort($dates, fn ($a, $b) => $a['date'] <=> $b['date']);

        $warnings['classes_without_sections'] = array_values(array_unique($warnings['classes_without_sections']));

        return ['dates' => $dates, 'warnings' => $warnings];
    }

    /** @return array{0: int, 1: list<list<mixed>>}|null [header row index, all raw rows] */
    private function detectHeader(Worksheet $sheet): ?array
    {
        $rows = $sheet->toArray(null, false, false, false);
        foreach ($rows as $i => $row) {
            if ($i > self::MAX_HEADER_SCAN_ROWS) {
                break;
            }
            $first = mb_strtolower(trim((string) ($row[0] ?? '')));
            if ($first !== '' && str_starts_with($first, 'date')) {
                return [$i, $rows];
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $headerRow
     * @param  Collection<int, \App\Models\SchoolClass>  $schoolClasses
     * @param  list<array{label:string, sheet:string}>  $unmapped  mutated by reference
     * @return array<int, array{label: string, class_id: int}> column index -> resolved class
     */
    private function resolveClassColumns(array $headerRow, Collection $schoolClasses, array &$unmapped, string $sheetName): array
    {
        $hasSittingCol = isset($headerRow[1]) && mb_strtolower(trim((string) $headerRow[1])) === 'sitting';
        $startCol = $hasSittingCol ? 2 : 1;

        $columns = [];
        for ($c = $startCol; $c < count($headerRow); $c++) {
            $label = trim((string) ($headerRow[$c] ?? ''));
            if ($label === '') {
                continue;
            }
            $classId = $this->mapClassLabel($label, $schoolClasses);
            if ($classId === null) {
                $unmapped[] = ['label' => $label, 'sheet' => $sheetName];

                continue;
            }
            $columns[$c] = ['label' => $label, 'class_id' => $classId];
        }

        return $columns;
    }

    /** @param  Collection<int, \App\Models\SchoolClass>  $schoolClasses */
    private function mapClassLabel(string $label, Collection $schoolClasses): ?int
    {
        $norm = static fn (string $s): string => mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
        $target = $norm($label);

        foreach ($schoolClasses as $class) {
            if ($norm((string) $class->name) === $target) {
                return $class->id;
            }
        }

        if (preg_match('/^[IVXLCDM]+$/', $target)) {
            $num = self::romanToInt($target);
            if ($num !== null) {
                foreach ($schoolClasses as $class) {
                    if (is_numeric($class->name) && (int) $class->name === $num) {
                        return $class->id;
                    }
                }
            }
        }

        if (isset(self::CLASS_ALIASES[$target])) {
            $aliasTarget = self::CLASS_ALIASES[$target];
            foreach ($schoolClasses as $class) {
                if ($norm((string) $class->name) === $aliasTarget) {
                    return $class->id;
                }
            }
        }

        return null;
    }

    private static function romanToInt(string $roman): ?int
    {
        $map = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
        $total = 0;
        $prev = 0;
        foreach (array_reverse(str_split($roman)) as $ch) {
            if (! isset($map[$ch])) {
                return null;
            }
            $val = $map[$ch];
            $total += $val < $prev ? -$val : $val;
            $prev = $val;
        }

        // Sanity cap -- a real class-label roman numeral is never going to be huge.
        return ($total > 0 && $total <= 20) ? $total : null;
    }

    /** @return array<string, int> normalized subject name -> subject id */
    private function buildSubjectLookup(): array
    {
        $lookup = [];
        foreach (Subject::all(['id', 'name']) as $subject) {
            $key = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subject->name) ?? '');
            if ($key !== '') {
                $lookup[$key] = $subject->id;
            }
        }

        return $lookup;
    }

    /**
     * @param  array{sheet:string, header_row_index:int, rows:list<list<mixed>>, columns:array<int, array{label:string, class_id:int}>}  $grid
     * @param  array<string, int>  $subjectLookup
     * @param  array<string, mixed>  $warnings  mutated by reference
     * @param  array<string, array<int, list<array{subject_id: ?int, raw_label: ?string}>>>  $byDate  date -> class_id -> entries; mutated by reference
     * @param  list<string>  $dateOrder  mutated by reference (first-seen date order, across all grids)
     */
    private function walkGrid(array $grid, array $subjectLookup, array &$warnings, array &$byDate, array &$dateOrder): void
    {
        $dataRows = array_slice($grid['rows'], $grid['header_row_index'] + 1, self::MAX_DATA_ROWS);
        $openDate = null;

        foreach ($dataRows as $row) {
            $parsedDate = ExcelDateParser::parse($row[0] ?? null);

            if ($parsedDate !== null) {
                if (! isset($byDate[$parsedDate])) {
                    $byDate[$parsedDate] = [];
                    $dateOrder[] = $parsedDate;
                }
                $openDate = $parsedDate;
                $this->consumeRow($row, $grid['columns'], $subjectLookup, $warnings, $byDate[$openDate], $openDate);

                continue;
            }

            $hasAnyClassContent = false;
            foreach (array_keys($grid['columns']) as $colIndex) {
                if (trim((string) ($row[$colIndex] ?? '')) !== '') {
                    $hasAnyClassContent = true;
                    break;
                }
            }

            if ($openDate !== null && $hasAnyClassContent) {
                // A "day name" continuation row (e.g. "Saturday") for the currently open date —
                // not a merged cell in the source file, so this is detected purely by "doesn't
                // parse as a date, but still carries subject data" rather than row position.
                $this->consumeRow($row, $grid['columns'], $subjectLookup, $warnings, $byDate[$openDate], $openDate);

                continue;
            }

            if ($openDate !== null) {
                // A blank/text row with no class content after at least one real date row has
                // been seen — this is the free-text notes footer described in the file format,
                // not more grid data. Stop reading this sheet here.
                break;
            }
            // Else: stray content before the first real date row (e.g. a spacer) — skip it.
        }
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<int, array{label:string, class_id:int}>  $columns
     * @param  array<string, int>  $subjectLookup
     * @param  array<string, mixed>  $warnings  mutated by reference
     * @param  array<int, list<array{subject_id: ?int, raw_label: ?string}>>  $group  mutated by reference (class_id -> entries)
     */
    private function consumeRow(array $row, array $columns, array $subjectLookup, array &$warnings, array &$group, string $dateStr): void
    {
        foreach ($columns as $colIndex => $col) {
            $raw = trim((string) ($row[$colIndex] ?? ''));
            if ($raw === '' || mb_strtoupper($raw) === 'X') {
                // Blank or a literal "X" -- no exam for this class on this date, not a holiday.
                continue;
            }

            $key = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
            $subjectId = $subjectLookup[$key] ?? null;
            if ($subjectId === null) {
                $warnings['unmatched_subjects'][] = ['date' => $dateStr, 'class_label' => $col['label'], 'raw_text' => $raw];
            }

            $group[$col['class_id']][] = ['subject_id' => $subjectId, 'raw_label' => $subjectId === null ? $raw : null];
        }
    }

    /**
     * Expands one date's class-level entries into per-section cells (no section detail exists
     * in the source file, so the same subject is applied to every active section of the class —
     * surfaced as a standing note rather than assumed silently), and flags a date whose row(s)
     * resolved to zero real subjects across every class as a possible holiday for human review
     * (the source file never marks holidays, so this is never auto-applied to is_holiday).
     *
     * @param  array<int, list<array{subject_id: ?int, raw_label: ?string}>>  $group
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Section>>  $sectionsByClass
     * @param  array<int, string>  $classIdToLabel
     * @param  array<string, mixed>  $warnings  mutated by reference
     * @return array<string, mixed>
     */
    private function finalizeDate(string $dateStr, array $group, Collection $sectionsByClass, array $classIdToLabel, array &$warnings): array
    {
        $cells = [];
        $anyResolved = false;

        foreach ($group as $classId => $entries) {
            $sections = $sectionsByClass->get($classId, collect());
            if ($sections->isEmpty()) {
                $warnings['classes_without_sections'][] = $classIdToLabel[$classId] ?? "class #{$classId}";

                continue;
            }
            foreach ($sections as $section) {
                foreach ($entries as $entry) {
                    $cells[] = [
                        'school_class_id' => $classId,
                        'section_id' => $section->id,
                        'subject_id' => $entry['subject_id'],
                        'raw_label' => $entry['raw_label'],
                        'start_time' => null,
                        'end_time' => null,
                    ];
                    if ($entry['subject_id'] !== null) {
                        $anyResolved = true;
                    }
                }
            }
        }

        return [
            'date' => $dateStr,
            'is_holiday' => false,
            'suspected_holiday' => ! $anyResolved,
            'cells' => $cells,
        ];
    }
}
