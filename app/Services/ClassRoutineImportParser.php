<?php

namespace App\Services;

use App\Models\ClassRoutineEntry;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses a school's printed Class Routine workbook (the "GAS CLASS ROUTINE" layout) into a
 * preview payload the routine grid UI pre-fills — nothing is written to the database here (see
 * ClassRoutineController::importPreview()); saving goes through the normal store()/update() flow
 * once the school has reviewed the warnings below and fixed anything in the grid.
 *
 * Expected shape: one sheet per "day group" (a sheet whose name starts with MON is applied to
 * Monday/Tuesday/Wednesday, one starting with THU to Thursday/Friday/Saturday — this school's
 * workbook prints one identical grid for each half of the week rather than six separate daily
 * grids). Any other sheet (e.g. a "TEACHER WISE" pivot) is a derived view, not source data, and
 * is ignored — the routine UI/export computes an equivalent teacher-wise view live instead.
 *
 * Each day-group sheet has two side-by-side tables:
 *  - a teacher legend in columns A-C (serial, name, a COUNTIF of that name across the grid) —
 *    each name's cell fill is that teacher's persistent colour;
 *  - the class grid in columns E-N: column E carries the class name merged over a 2-row block,
 *    column F alternates "SUB" (subject per period) / "TEACH" (teacher per period, coloured),
 *    columns after that are one per period (as labelled by the numeric header in row 2), and the
 *    last column is free-text remarks.
 */
class ClassRoutineImportParser
{
    /** Subject text seen in the sheet -> normalized catalog subject name it should resolve to,
     *  for abbreviations a plain normalized-text match can't bridge on its own. */
    private const SUBJECT_ALIASES = [
        'ENG' => 'ENGLISH',
        'GK' => 'GKCOMP',
        'COMPGK' => 'GKCOMP',
        'ORALRHYMES' => 'ORAL',
        'RHYMES' => 'ORAL',
    ];

    /** Class label (normalized) -> the school-class name it should match, for labels a plain
     *  roman-numeral/digit comparison can't resolve. */
    private const CLASS_ALIASES = [
        'NURS' => 'NURSERY',
        'NUR' => 'NURSERY',
    ];

    /**
     * @return array{days: array<int, array{day_of_week:int, label:string, entries: list<array>}>, warnings: array<string, mixed>, teacher_colors: array<string, string>}
     *
     * @throws \RuntimeException when the workbook has no recognisable day-group sheet at all.
     */
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $schoolClasses = SchoolClass::all(['id', 'name']);
        $teachers = Teacher::all(['id', 'name']);
        $subjectLookup = $this->buildSubjectLookup();

        $warnings = [
            'unmatched_subjects' => [],
            'unmatched_teachers' => [],
            'unmatched_classes' => [],
            'sheets_skipped' => [],
        ];

        $dayGroups = [];
        foreach ($spreadsheet->getSheetNames() as $name) {
            $upper = mb_strtoupper(trim($name));
            if (str_starts_with($upper, 'MON')) {
                $dayGroups[] = ['sheet' => $spreadsheet->getSheetByName($name), 'days' => [1, 2, 3], 'label' => 'Mon / Tue / Wed'];
            } elseif (str_starts_with($upper, 'THU')) {
                $dayGroups[] = ['sheet' => $spreadsheet->getSheetByName($name), 'days' => [4, 5, 6], 'label' => 'Thu / Fri / Sat'];
            } else {
                $warnings['sheets_skipped'][] = $name;
            }
        }

        if ($dayGroups === []) {
            $spreadsheet->disconnectWorksheets();

            throw new \RuntimeException('Could not find a class routine grid in this file — expected a sheet named starting with "MON" (Mon/Tue/Wed) and/or "THU" (Thu/Fri/Sat).');
        }

        $byDay = [];
        $teacherColors = [];
        foreach ($dayGroups as $group) {
            $groupEntries = $this->walkSheet($group['sheet'], $schoolClasses, $teachers, $subjectLookup, $warnings);
            foreach ($group['days'] as $day) {
                $byDay[$day] = ['day_of_week' => $day, 'label' => ClassRoutineEntry::DAYS[$day], 'entries' => $groupEntries];
            }
            $teacherColors = [...$teacherColors, ...$this->legendColorsForSheet($group['sheet'])];
        }

        $spreadsheet->disconnectWorksheets();
        ksort($byDay);

        $warnings['unmatched_subjects'] = collect($warnings['unmatched_subjects'])->unique()->values()->all();
        $warnings['unmatched_teachers'] = collect($warnings['unmatched_teachers'])->unique()->values()->all();
        $warnings['unmatched_classes'] = collect($warnings['unmatched_classes'])->unique()->values()->all();

        return ['days' => array_values($byDay), 'warnings' => $warnings, 'teacher_colors' => $teacherColors];
    }

    private static function normalize(string $s): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
    }

    /** @return array<string, int> normalized subject name -> subject id */
    private function buildSubjectLookup(): array
    {
        $lookup = [];
        foreach (Subject::all(['id', 'name']) as $subject) {
            $key = self::normalize((string) $subject->name);
            if ($key !== '') {
                $lookup[$key] = $subject->id;
            }
        }

        return $lookup;
    }

    /** @param  Collection<int, SchoolClass>  $schoolClasses */
    private function matchClass(string $label, Collection $schoolClasses): ?SchoolClass
    {
        $target = self::normalize($label);
        if ($target === '') {
            return null;
        }

        foreach ($schoolClasses as $class) {
            if (self::normalize((string) $class->name) === $target) {
                return $class;
            }
        }

        if (preg_match('/^[IVXLCDM]+$/', $target)) {
            $num = self::romanToInt($target);
            if ($num !== null) {
                foreach ($schoolClasses as $class) {
                    if (is_numeric($class->name) && (int) $class->name === $num) {
                        return $class;
                    }
                }
            }
        }

        if (isset(self::CLASS_ALIASES[$target])) {
            $aliasTarget = self::CLASS_ALIASES[$target];
            foreach ($schoolClasses as $class) {
                if (self::normalize((string) $class->name) === $aliasTarget) {
                    return $class;
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

        return ($total > 0 && $total <= 20) ? $total : null;
    }

    /** @param  array<string, int>  $subjectLookup */
    private function matchSubject(string $raw, array $subjectLookup): ?int
    {
        $key = self::normalize($raw);
        if (isset($subjectLookup[$key])) {
            return $subjectLookup[$key];
        }
        if (isset(self::SUBJECT_ALIASES[$key], $subjectLookup[self::SUBJECT_ALIASES[$key]])) {
            return $subjectLookup[self::SUBJECT_ALIASES[$key]];
        }

        return null;
    }

    /**
     * One-word text in the sheet ("IQBAL", "SHAHID") against full recorded names ("IQBALUR
     * RAHMAN", "MD SHAHID") — the short name can be a prefix of one word in the full name
     * ("IQBAL" / "IQBALUR"), or match a later word exactly ("SHAHID" is the second word of "MD
     * SHAHID"), or be a near-spelling of a word ("TABASSUM" vs "TABBASSUM"). Tried in that order;
     * a tie between several teachers is broken by shortest full name.
     *
     * @param  Collection<int, Teacher>  $teachers
     */
    private function matchTeacher(string $raw, Collection $teachers): ?Teacher
    {
        $target = self::normalize($raw);
        if ($target === '') {
            return null;
        }

        foreach ($teachers as $teacher) {
            if (self::normalize((string) $teacher->name) === $target) {
                return $teacher;
            }
        }

        $wordsOf = fn ($t) => array_map(self::normalize(...), preg_split('/\s+/', trim((string) $t->name)) ?: []);

        $exactWord = $teachers->filter(fn ($t) => in_array($target, $wordsOf($t), true));
        if ($exactWord->count() >= 1) {
            return $exactWord->sortBy(fn ($t) => mb_strlen((string) $t->name))->first();
        }

        $startingWord = $teachers->filter(fn ($t) => collect($wordsOf($t))->contains(fn ($w) => str_starts_with($w, $target)));
        if ($startingWord->count() >= 1) {
            return $startingWord->sortBy(fn ($t) => mb_strlen((string) $t->name))->first();
        }

        // A single-character edit only counts on a long-enough word (>= 7 letters, e.g.
        // "TABASSUM" vs "TABBASSUM") — on a short word it's too easy to collide with a
        // genuinely different name that happens to differ by one letter (e.g. "SANA" is one
        // edit from "SABA", but this school has both as separate teachers). Short unmatched
        // names are left as a label rather than risk misattributing someone's periods.
        if (mb_strlen($target) < 7) {
            return null;
        }

        $best = null;
        $bestDistance = 2;
        foreach ($teachers as $teacher) {
            foreach ($wordsOf($teacher) as $word) {
                if (mb_strlen($word) < 7) {
                    continue;
                }
                $distance = levenshtein($target, $word);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $teacher;
                }
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, SchoolClass>  $schoolClasses
     * @param  Collection<int, Teacher>  $teachers
     * @param  array<string, int>  $subjectLookup
     * @param  array<string, mixed>  $warnings  mutated by reference
     * @return list<array>
     */
    private function walkSheet(Worksheet $sheet, Collection $schoolClasses, Collection $teachers, array $subjectLookup, array &$warnings): array
    {
        // Period columns: any column after F whose row-2 header is a plain number.
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $periodColumns = [];
        for ($c = 7; $c <= $highestCol; $c++) { // column G = index 7
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $header = trim((string) $sheet->getCell($colLetter.'2')->getValue());
            if ($header !== '' && is_numeric($header)) {
                $periodColumns[(int) $header] = $colLetter;
            } else {
                break; // stop at the first non-numeric header (REMARKS) after periods start
            }
        }

        // Class blocks: every column-E merge range (e.g. "E3:E4") marks one class's 2-row block —
        // this naturally skips the trailing substitution/leisure block below the grid, which has
        // no such merges.
        $blockStartRows = [];
        foreach ($sheet->getMergeCells() as $range) {
            if (str_starts_with($range, 'E') && preg_match('/^E(\d+):E(\d+)$/', $range, $m)) {
                $blockStartRows[] = (int) $m[1];
            }
        }
        sort($blockStartRows);

        $entries = [];
        foreach ($blockStartRows as $subRow) {
            $teachRow = $subRow + 1;
            $classLabel = trim((string) $sheet->getCell('E'.$subRow)->getValue());
            if ($classLabel === '') {
                continue;
            }

            // An empty block (e.g. a placeholder grade the school hasn't filled in yet, like a
            // future IX/X row with no periods entered) is skipped silently — nothing to import,
            // so no point warning about a class label that doesn't even carry any data here.
            $blockHasContent = false;
            foreach ($periodColumns as $colLetter) {
                if (trim((string) $sheet->getCell($colLetter.$subRow)->getValue()) !== ''
                    || trim((string) $sheet->getCell($colLetter.$teachRow)->getValue()) !== '') {
                    $blockHasContent = true;
                    break;
                }
            }
            if (! $blockHasContent) {
                continue;
            }

            $class = $this->matchClass($classLabel, $schoolClasses);
            if (! $class) {
                $warnings['unmatched_classes'][] = $classLabel;

                continue;
            }
            $remarks = trim((string) $sheet->getCell('N'.$subRow)->getValue()) ?: (trim((string) $sheet->getCell('N'.$teachRow)->getValue()) ?: null);

            foreach ($periodColumns as $periodNumber => $colLetter) {
                $subjectRaw = trim((string) $sheet->getCell($colLetter.$subRow)->getValue());
                $teacherRaw = trim((string) $sheet->getCell($colLetter.$teachRow)->getValue());
                if ($subjectRaw === '' && $teacherRaw === '') {
                    continue;
                }

                $subjectId = null;
                $subjectLabel = null;
                if ($subjectRaw !== '') {
                    $subjectId = $this->matchSubject($subjectRaw, $subjectLookup);
                    if ($subjectId === null) {
                        $subjectLabel = $subjectRaw;
                        $warnings['unmatched_subjects'][] = $subjectRaw;
                    }
                }

                $teacherId = null;
                $teacherLabel = null;
                $color = null;
                if ($teacherRaw !== '') {
                    $teacherCell = $sheet->getCell($colLetter.$teachRow);
                    $fill = $teacherCell->getStyle()->getFill();
                    $color = $fill->getFillType() !== Fill::FILL_NONE ? '#'.substr($fill->getStartColor()->getRGB(), -6) : null;

                    $teacher = $this->matchTeacher($teacherRaw, $teachers);
                    if ($teacher) {
                        $teacherId = $teacher->id;
                    } else {
                        $teacherLabel = $teacherRaw;
                        $warnings['unmatched_teachers'][] = $teacherRaw;
                    }
                }

                $entries[] = [
                    'school_class_id' => $class->id,
                    'section_id' => null,
                    'period_number' => $periodNumber,
                    'subject_id' => $subjectId,
                    'subject_label' => $subjectLabel,
                    'teacher_id' => $teacherId,
                    'teacher_label' => $teacherLabel,
                    'color' => $color,
                    'remarks' => $remarks,
                ];
            }
        }

        return $entries;
    }

    /**
     * Reads one day-group sheet's teacher-legend block (columns A-C) so the caller can persist
     * each recognised teacher's badge colour once, in addition to the per-cell colours captured
     * in walkSheet().
     *
     * @return array<string, string> teacher name (raw, as printed) -> "#RRGGBB"
     */
    private function legendColorsForSheet(Worksheet $sheet): array
    {
        $colors = [];
        $row = 3;
        $blankStreak = 0;
        while ($blankStreak < 3 && $row < 200) {
            $serial = trim((string) $sheet->getCell('A'.$row)->getValue());
            $name2 = trim((string) $sheet->getCell('B'.$row)->getValue());
            if ($serial === '' && $name2 === '') {
                $blankStreak++;
                $row++;

                continue;
            }
            $blankStreak = 0;
            if ($name2 !== '') {
                $fill = $sheet->getCell('B'.$row)->getStyle()->getFill();
                if ($fill->getFillType() !== Fill::FILL_NONE) {
                    $colors[$name2] = '#'.substr($fill->getStartColor()->getRGB(), -6);
                }
            }
            $row++;
        }

        return $colors;
    }
}
