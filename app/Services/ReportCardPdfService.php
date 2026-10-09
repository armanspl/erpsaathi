<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\AttendanceMonthlySummary;
use App\Models\CoScholasticGrade;
use App\Models\Exam;
use App\Models\GradeSystem;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds Template Builder data tokens for report-card PDFs (scholastic rows, grading key, chart).
 * Layout lives in documents/templates/{classic|modern}/report_card.blade.php.
 */
class ReportCardPdfService
{
    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    /** @param  array<string, mixed>  $row  from ExamResultCalculator or AnnualReportCalculator */
    public function buildData(Exam $exam, array $row): array
    {
        $school = $this->dataBuilder->schoolContext();
        $student = Student::with(['father:id,name', 'mother:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->find($row['student_id'] ?? null);

        $session = AcademicSession::fromRequest(request(), true);
        $sessionName = $this->shortSessionLabel($session?->name ?: ($school['session_year'] ?? ''));

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        // Keep Drawing available for Co-Scholastic "Drawing & Art", but never print it in the
        // scholastic marks table / chart, and never let it feed OVERALL MARKS.
        if (! isset($row['all_subjects'])) {
            $row['all_subjects'] = $row['subjects'] ?? [];
        }
        $subjects = collect($row['subjects'] ?? [])
            ->reject(fn ($s) => str_contains(mb_strtolower((string) ($s['subject_name'] ?? '')), 'drawing'))
            ->values();
        $grades = GradeSystem::orderByDesc('min_percentage')->get();
        $isAnnual = ($row['format'] ?? '') === 'annual_term';

        $father = $student?->father?->name ?? ($row['father_name'] ?? '—');
        $mother = $student?->mother?->name ?? ($row['mother_name'] ?? '—');
        $dob = $student?->dob ? Carbon::parse($student->dob)->format('d-m-Y') : '—';
        $className = $student?->schoolClass?->name ?? ($row['school_class_name'] ?? '—');
        $sectionName = $student?->section?->name ?? ($row['section_name'] ?? '—');

        // OVERALL MARKS always match the printed (non-Drawing) scholastic table.
        $obtained = 0.0;
        $maxTotal = 0.0;
        foreach ($subjects as $s) {
            if (($s['marks_obtained'] ?? null) === null) {
                continue;
            }
            $obtained += (float) $s['marks_obtained'];
            $maxTotal += (float) ($s['max_marks'] ?? 0);
        }
        $percentage = $maxTotal > 0 ? round(($obtained / $maxTotal) * 100, 2) : 0.0;
        $gradeBand = $grades->first(fn ($g) => $percentage >= (float) $g->min_percentage);
        if ($gradeBand) {
            $row['grade'] = $gradeBand->grade;
        }
        $fmt = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        $classTeacher = $student?->school_class_id
            ? Teacher::where('school_class_id', $student->school_class_id)->orderBy('id')->first()
            : null;

        $phones = trim(collect([$school['school_phone'] ?? null])->filter()->implode(', '));
        $classSection = trim($className.($sectionName && $sectionName !== '—' ? ' ('.$sectionName.')' : ''));

        $marksPlain = $subjects->map(function ($s) {
            return sprintf(
                '%-18s %5s / %s',
                $s['subject_name'] ?? '',
                $s['marks_obtained'] ?? '—',
                $s['max_marks'] ?? ''
            );
        })->implode("\n");

        $title = $isAnnual
            ? ($row['mapping_name'] ?? 'Annual Examination Report Card')
            : trim($exam->name.' Report Card');
        $bandTitle = $isAnnual
            ? mb_strtoupper((string) ($row['mapping_name'] ?? 'Annual Examination'))
            : (mb_strtoupper($exam->name).' ('.($maxTotal > 0 ? $fmt($maxTotal) : '—').' Marks)');

        $accentPalette = $this->dataBuilder->accentPalette($exam->pdf_accent_color);
        $isMono = $exam->pdf_accent_color === 'none';

        return array_merge($school, $accentPalette, [
            // "None" isn't just a grey accent_color — the header bands fill with accent_color/
            // accent_dark and hardcode white text on top, so a dark-grey fill would just swap
            // "coloured header" for "grey header", still with white lettering. This class lets
            // the template's CSS drop the fill entirely and force black text instead (see
            // `.sheet.mono` rules in the classic/modern report_card templates).
            'mono_class' => $isMono ? 'mono' : '',
            'exam_title' => $title,
            'exam_band_title' => $bandTitle,
            'student_name' => $row['name'] ?? '',
            'father_name' => $father,
            'mother_name' => $mother,
            'dob' => $dob,
            'admission_id' => $row['admission_no'] ?? '',
            'roll_number' => $row['roll_no'] ?? '',
            'class' => $className,
            'section' => $sectionName,
            'class_section' => $classSection,
            'session_year' => $sessionName ?: '—',
            'school_phone_line' => $phones !== '' ? 'MOB. '.$phones : '',
            'logo_html' => $this->logoHtml($school, $escape),
            'marks_table' => "Subject            Obt / Max\n".($marksPlain ?: 'No marks'),
            'marks_format' => $isAnnual ? 'annual_term' : 'single_exam',
            'marks_table_class' => $isAnnual ? 'annual' : '',
            'marks_thead_html' => $isAnnual
                ? $this->annualTheadHtml($row['columns'] ?? [], $escape)
                : $this->singleExamTheadHtml($bandTitle, $escape),
            'marks_colspan' => $isAnnual ? $this->annualColCount($row['columns'] ?? []) : 5,
            'marks_html' => $isAnnual
                ? $this->annualScholasticRowsHtml($subjects, $row['columns'] ?? [], $escape)
                : $this->scholasticRowsHtml($subjects, $grades, $escape),
            'marks_summary_html' => $isAnnual ? $this->annualSummaryHtml($row['summary'] ?? null, $escape) : '',
            'total_marks' => $fmt($obtained).' / '.$fmt($maxTotal),
            'percentage' => $fmt($percentage).' %',
            'grade' => $row['grade'] ?? '—',
            'result' => $row['result'] ?? '',
            'rank' => (string) ($row['rank'] ?? '—'),
            'attendance' => $this->attendanceSummary((int) ($row['student_id'] ?? 0), $session),
            'summary_rows_html' => $this->summaryRowsHtml(
                $this->attendanceSummary((int) ($row['student_id'] ?? 0), $session),
                $fmt($obtained).' / '.$fmt($maxTotal),
                $fmt($percentage).' %',
                (string) ($row['grade'] ?? '—'),
                (string) ($row['rank'] ?? '—'),
                $escape
            ),
            'remarks' => $row['remarks'] ?? 'GOOD / VERY GOOD / EXCELLENT',
            'co_scholastic_html' => $this->coScholasticHtml($row, $grades, $accentPalette['accent_color'], $isMono, $escape),
            'grading_html' => $this->gradingSystemHtml($grades, $escape),
            'chart_html' => $this->subjectMarksChart($subjects),
            'class_teacher_signature' => "Class Teacher's Sign",
            // Left empty: the "Principal's Sign" label now lives inside principal_sign_html
            // itself (see below), in the same centered block as the image, so the two can
            // never drift apart. The old standalone <div>{{ principal_signature }}</div> line
            // still exists in already-created templates' stored HTML — it just renders nothing.
            'principal_signature' => '',
            'stamp_html' => ! empty($school['school_stamp'])
                ? '<img class="stamp" src="'.$school['school_stamp'].'" alt="Stamp" />'
                : '',
            'class_teacher_sign_html' => $classTeacher?->signature_path
                ? '<img class="sig-img" src="'.$this->dataBuilder->resolveStoredImage($classTeacher->signature_path).'" alt="Class Teacher" />'
                : '<div class="sig-space"></div>',
            // A small fixed-width table, pinned to the right edge via margin-left:auto — not
            // centered within the whole right-hand column (that drifted left/center depending
            // on surrounding content) and not a plain block div (a block's own box ignores an
            // ancestor's text-align — only its content obeys its own). margin-left:auto on an
            // explicitly-sized table reliably pins it to the right regardless of what the
            // ancestor cell's own CSS says, so it stays put across every report card template,
            // old or new. Image and label are one unit inside it, centered with each other.
            'principal_sign_html' => '<table style="width:100pt;margin-left:auto;border-collapse:collapse"><tr>'
                .'<td style="border:none;padding:0;width:auto;text-align:center;vertical-align:top;font-weight:inherit;font-size:inherit">'
                .(! empty($school['principal_signature_image'])
                    ? '<img class="sig-img" src="'.$school['principal_signature_image'].'" alt="Principal" />'
                    : '<div class="sig-space"></div>')
                .'<div>Principal\'s Sign</div>'
                .'</td></tr></table>',
        ]);
    }

    /** @param  array<string, mixed>  $row */
    public function binary(Exam $exam, array $row): string
    {
        return app(DocumentRenderService::class)->pdfBinary('report_card', $this->buildData($exam, $row));
    }

    /** @param  array<string, mixed>  $row */
    public function streamDownload(Exam $exam, array $row, string $filename)
    {
        return app(DocumentRenderService::class)->streamPdf('report_card', $this->buildData($exam, $row), $filename);
    }

    private function logoHtml(array $school, callable $escape): string
    {
        if (! empty($school['school_logo'])) {
            return '<img class="logo" src="'.$school['school_logo'].'" alt="Logo" />';
        }

        return '<div class="logo-fallback">'.$escape(mb_strtoupper(mb_substr($school['school_name'] ?? 'S', 0, 2))).'</div>';
    }

    private function scholasticRowsHtml(Collection $subjects, Collection $grades, callable $escape): string
    {
        $rows = $subjects->map(function ($s) use ($escape, $grades) {
            $obt = $s['marks_obtained'];
            $max = (float) ($s['max_marks'] ?? 0);
            if (! empty($s['is_absent'])) {
                return '<tr>'
                    .'<td class="subj">'.$escape(mb_strtoupper((string) ($s['subject_name'] ?? ''))).'</td>'
                    .'<td class="num">Ab</td>'
                    .'<td class="num">'.($max > 0 ? rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') : '—').'</td>'
                    .'<td class="num">—</td>'
                    .$this->gradeCellHtml('—', $escape)
                    .'</tr>';
            }
            $obtNum = $obt === null || $obt === '' ? null : (float) $obt;
            $pct = ($obtNum !== null && $max > 0) ? round(($obtNum / $max) * 100, 1) : null;
            // $grades is ordered highest-min-percentage first, so the first band whose floor the
            // percentage clears is the right one — also checking max_percentage leaves gaps
            // between whole-number bands (e.g. 80-89 then 90-100) that a 1-decimal percentage
            // like 89.9 falls straight through, showing no grade at all.
            $grade = $pct !== null
                ? ($grades->first(fn ($g) => $pct >= (float) $g->min_percentage)?->grade ?? '—')
                : '—';
            $fmt = fn (?float $n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

            return '<tr>'
                .'<td class="subj">'.$escape(mb_strtoupper((string) ($s['subject_name'] ?? ''))).'</td>'
                .'<td class="num">'.$fmt($obtNum).'</td>'
                .'<td class="num">'.($max > 0 ? $fmt($max) : '—').'</td>'
                .'<td class="num">'.($pct === null ? '—' : $pct).'</td>'
                .$this->gradeCellHtml($grade, $escape)
                .'</tr>';
        })->implode('');

        return $rows !== '' ? $rows : '<tr><td colspan="5" class="empty">No subject marks recorded.</td></tr>';
    }

    /**
     * The single-exam header (Scholastic Area band + Subject/Obtained/Max/%/Grade). Computed
     * in PHP — like annualTheadHtml() — rather than left as static markup in the Blade file,
     * because the live template is a frozen token snapshot (see DesignCatalog::seedHtml()):
     * a Blade @if choosing between this and the annual header only ever evaluates once, at
     * seed time, with placeholder values — so it would permanently freeze on whichever branch
     * happened to be "false" then, no matter what the real render needs later.
     */
    private function singleExamTheadHtml(string $bandTitle, callable $escape): string
    {
        return '<tr><th class="band" colspan="1">Scholastic Area</th><th class="band" colspan="4">'.$escape($bandTitle).'</th></tr>'
            .'<tr><th>Subjects</th><th>Obtained</th><th>Max</th><th>%</th><th>Grade</th></tr>';
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function annualColCount(array $columns): int
    {
        $n = 1; // subject
        foreach ($columns as $group) {
            $n += count($group['children'] ?? []);
        }

        return max(2, $n);
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function annualTheadHtml(array $columns, callable $escape): string
    {
        if ($columns === []) {
            return '';
        }
        $top = '<tr><th class="band">Scholastic Area</th>';
        $bottom = '<tr><th>Subjects</th>';
        foreach ($columns as $group) {
            $children = $group['children'] ?? [];
            $span = max(1, count($children));
            $name = (string) ($group['term_name'] ?? '');
            if ($name === 'OVERALL') {
                $label = 'OVERALL';
            } else {
                $max = (int) round((float) ($group['max_marks'] ?? 100));
                $label = $name.' ('.$max.' Marks)';
            }
            $top .= '<th class="band" colspan="'.$span.'">'.$escape($label).'</th>';
            foreach ($children as $child) {
                $bottom .= '<th>'.$escape((string) ($child['label'] ?? '')).'</th>';
            }
        }
        $top .= '</tr>';
        $bottom .= '</tr>';

        return $top.$bottom;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function annualScholasticRowsHtml(Collection $subjects, array $columns, callable $escape): string
    {
        $fmt = fn ($n) => $n === null || $n === ''
            ? '—'
            : (is_string($n) && ! is_numeric($n)
                ? $escape($n)
                : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'));

        $colCount = $this->annualColCount($columns);
        $rows = $subjects->map(function ($s) use ($escape, $fmt, $columns) {
            $termBlocks = collect($s['terms'] ?? [])->keyBy('term_id');
            $html = '<tr><td class="subj">'.$escape(mb_strtoupper((string) ($s['subject_name'] ?? ''))).'</td>';
            foreach ($columns as $group) {
                $isOverall = ($group['term_name'] ?? '') === 'OVERALL'
                    || ($group['term_name'] ?? '') === 'Overall'
                    || ($group['term_id'] ?? null) === null;
                if ($isOverall) {
                    foreach ($group['children'] ?? [] as $child) {
                        if (($child['type'] ?? '') === 'grade') {
                            $html .= $this->gradeCellHtml((string) ($s['grade'] ?? '—'), $escape);
                        } else {
                            // Overall Total is an average of Term-1 + Term-2 totals (see
                            // AnnualReportCalculator::forSession()), so it's shown with a fixed
                            // 2 decimals (e.g. "89.50") to make the averaging visible, unlike
                            // the term columns which trim trailing zeros for whole-number marks.
                            $overall = $s['overall'] ?? null;
                            $html .= '<td class="num">'.($overall === null ? '—' : number_format((float) $overall, 2, '.', '')).'</td>';
                        }
                    }
                    continue;
                }
                $block = $termBlocks->get($group['term_id']) ?? ['cells' => [], 'test_total' => null, 'total' => null];
                foreach ($group['children'] ?? [] as $child) {
                    $type = $child['type'] ?? '';
                    if ($type === 'exam') {
                        $key = $child['key'] ?? '';
                        $html .= '<td class="num">'.$fmt($block['cells'][$key] ?? null).'</td>';
                    } elseif ($type === 'test_total') {
                        $html .= '<td class="num">'.$fmt($block['test_total'] ?? null).'</td>';
                    } elseif ($type === 'term_total') {
                        $html .= '<td class="num">'.$fmt($block['total'] ?? null).'</td>';
                    } elseif ($type === 'grade') {
                        // Single-term reports (Term Result / Half Yearly) put Grade at the end
                        // of the one term block instead of a separate OVERALL group — see
                        // AcademicTermController::toPdfRow(), which appends this column.
                        $html .= $this->gradeCellHtml((string) ($s['grade'] ?? '—'), $escape);
                    } else {
                        $html .= '<td class="num">—</td>';
                    }
                }
            }
            $html .= '</tr>';

            return $html;
        })->implode('');

        return $rows !== '' ? $rows : '<tr><td colspan="'.$colCount.'" class="empty">No subject marks recorded.</td></tr>';
    }

    /** @param  array{headers?: list<string>, values?: list<string|float>}|null  $summary */
    private function annualSummaryHtml(?array $summary, callable $escape): string
    {
        $headers = $summary['headers'] ?? [];
        $values = $summary['values'] ?? [];
        if ($headers === [] || count($headers) !== count($values)) {
            return '';
        }

        $th = '';
        $td = '';
        foreach ($headers as $i => $header) {
            $th .= '<th>'.$escape((string) $header).'</th>';
            $val = $values[$i];
            $cell = is_numeric($val)
                ? rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.')
                : $escape((string) $val);
            $td .= '<td'.($i === 0 ? ' class="subj"' : ' class="num"').'>'.$cell.'</td>';
        }

        return '<table class="marks-summary"><thead><tr>'.$th.'</tr></thead><tbody><tr>'.$td.'</tr></tbody></table>';
    }

    /**
     * Co-Scholastic Areas "Remarks" column — not manually entered per student, derived from
     * grades already computed elsewhere on this same card:
     * - Work Education mirrors the card's own Overall Grade.
     * - Drawing & Art mirrors the student's "Drawing" subject grade, or "A" when this result
     *   doesn't include a Drawing subject at all.
     * - Sports is always "A" — nothing in the system grades sports, so there's no value to
     *   derive it from.
     * Applies identically to every result type (Individual Exam, Term/Half Yearly, Annual) —
     * all of them set $row['grade'] and $row['subjects'], which is all this needs.
     *
     * @param  array<string, mixed>  $row
     */
    private function coScholasticHtml(array $row, Collection $grades, string $accentColor, bool $isMono, callable $escape): string
    {
        $overallGrade = trim((string) ($row['grade'] ?? ''));
        $values = [
            'work_education' => $overallGrade !== '' ? $overallGrade : 'A',
            'drawing_art' => $this->subjectGrade($row, 'drawing', $grades) ?? 'A',
            'sports' => 'A',
        ];

        // Header fill mirrors the marks table's own coloured header — white text on the
        // accent colour, or black text with no fill in "None"/mono mode. Set inline rather
        // than via the template's own CSS so it takes effect on already-created report card
        // templates immediately, not just newly-seeded ones.
        $headStyle = $isMono
            ? 'background:#ffffff;color:#000000'
            : 'background:'.$escape($accentColor).';color:#ffffff';
        $thead = '<tr><th class="area-head" style="'.$headStyle.'">CO-SCHOLASTIC AREAS</th>'
            .'<th class="remarks-head" style="'.$headStyle.'">GRADE</th></tr>';

        $bodyRows = '';
        foreach (CoScholasticGrade::AREAS as $key => $label) {
            // The fixed-width box is centered within the column (so the group of grades reads
            // as centered overall), but the text inside each box is left-aligned — "A" and "A+"
            // are different widths, so centering the text itself would start each one at a
            // different x position; this keeps every value starting at the same spot within its
            // own (equally sized, equally centered) box. Same technique as the marks table's own
            // Grade column. Area names print in capitals ("WORK EDUCATION").
            $bodyRows .= '<tr><td class="area" style="text-transform:uppercase">'.$escape(mb_strtoupper($label)).'</td>'
                .'<td class="grade" style="text-align:center"><span style="display:inline-block;width:16pt;text-align:left">'
                .$escape($values[$key] ?? 'A').'</span></td></tr>';
        }

        return '<table class="co-grid">'.$thead.$bodyRows.'</table>';
    }

    /**
     * ATTENDANCE / OVERALL MARKS / OVERALL PERCENTAGE / OVERALL GRADE / CLASS RANK — five
     * bordered boxes, each built as its own fixed-width-label two-column mini table instead of
     * plain "LABEL: value" text. Every box shares the same label-column width, so every value
     * starts at the same x position across all five boxes regardless of label length (plain
     * text meant "CLASS RANK:" pushed its value much further left than "OVERALL PERCENTAGE:" did).
     * The grade badge's left padding (6pt in the template CSS) is cancelled with an equal negative
     * margin so its letter starts at the same x as the plain values (e.g. CLASS RANK's "3").
     */
    private function summaryRowsHtml(string $attendance, string $totalMarks, string $percentage, string $grade, string $rank, callable $escape): string
    {
        $rows = [
            ['ATTENDANCE:', $attendance, 'sum-val'],
            ['OVERALL MARKS:', $totalMarks, 'sum-val'],
            ['OVERALL PERCENTAGE:', $percentage, 'sum-val'],
            ['OVERALL GRADE:', $grade, 'sum-grade'],
            ['CLASS RANK:', $rank, 'sum-val'],
        ];

        $html = '';
        foreach ($rows as [$label, $value, $valueClass]) {
            $html .= '<div class="sum-box"><table style="width:100%;border-collapse:collapse"><tr>'
                .'<td style="border:none;padding:0;width:130pt;text-align:left;white-space:nowrap">'.$escape($label).'</td>'
                .'<td style="border:none;padding:0;text-align:left"><span class="'.$valueClass.'"'.($valueClass === 'sum-grade' ? ' style="margin-left:-6pt"' : '').'>'.$escape($value).'</span></td>'
                .'</tr></table></div>';
        }

        return $html;
    }

    /**
     * Case-insensitive substring match on subject name within the row's subject list, returning
     * its grade — the grade already computed on the row when present (Term/Half Yearly/Annual
     * rows carry one per subject), or computed here from marks/max via the grade bands when it
     * isn't (Individual Exam rows only carry raw marks per subject, no precomputed grade).
     * Checks `all_subjects` first — Term/Half Yearly rows drop Drawing from the printed marks
     * table but still carry it there, so Drawing & Art can still mirror its real grade.
     */
    private function subjectGrade(array $row, string $needle, Collection $grades): ?string
    {
        $match = collect($row['all_subjects'] ?? $row['subjects'] ?? [])
            ->first(fn ($s) => str_contains(mb_strtolower((string) ($s['subject_name'] ?? '')), $needle));
        if (! $match) {
            return null;
        }
        if (! empty($match['grade'])) {
            return (string) $match['grade'];
        }

        $obt = $match['marks_obtained'] ?? null;
        $max = (float) ($match['max_marks'] ?? 0);
        if ($obt === null || $obt === '' || $max <= 0) {
            return null;
        }

        $pct = round(((float) $obt / $max) * 100, 2);

        return $grades->first(fn ($g) => $pct >= (float) $g->min_percentage)?->grade;
    }

    /** "2026-2027" → "2026-27"; anything not matching that shape is left untouched. */
    private function shortSessionLabel(string $name): string
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', trim($name), $m)) {
            return $m[1].'-'.substr($m[2], 2, 2);
        }

        return $name;
    }

    /**
     * Prefer imported/manual monthly summaries for the session; fall back to daily marks
     * when no monthly rows exist (schools that still mark day-by-day).
     * Display: "38 / 84 (45.2%)" = days present / working days (percentage).
     */
    private function attendanceSummary(int $studentId, ?AcademicSession $session): string
    {
        if ($studentId <= 0) {
            return '— / —';
        }

        $sessionStartYear = $session?->start_date
            ? (int) $session->start_date->format('Y')
            : null;

        if ($sessionStartYear) {
            $monthly = AttendanceMonthlySummary::query()
                ->where('student_id', $studentId)
                ->where('session_start_year', $sessionStartYear)
                ->get(['working_days', 'days_present']);

            if ($monthly->isNotEmpty()) {
                $workingDays = (int) $monthly->sum('working_days');
                $daysPresent = (int) $monthly->sum('days_present');
                if ($workingDays <= 0 && $daysPresent <= 0) {
                    return '— / —';
                }
                $pct = $workingDays > 0 ? round(($daysPresent / $workingDays) * 100, 1) : 0;

                return $daysPresent.' / '.$workingDays.' ('.$pct.'%)';
            }
        }

        $query = Attendance::query()
            ->where('attendable_type', 'student')
            ->where('attendable_id', $studentId);

        if ($session?->start_date && $session?->end_date) {
            $query->whereDate('date', '>=', $session->start_date->toDateString())
                ->whereDate('date', '<=', $session->end_date->toDateString());
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            return '— / —';
        }

        $present = (clone $query)->whereIn('status', ['Present', 'Late', 'Half Day'])->count();
        $pct = round(($present / $total) * 100, 1);

        return $present.' / '.$total.' ('.$pct.'%)';
    }

    /**
     * "A" vs "A+" centered in a plain <td> sit at different horizontal positions — a shorter
     * string's centered midpoint isn't the same x as a longer string's. Wrapping the value in a
     * fixed-width inline-block keeps every grade's box the same size, so 1- and 2-character
     * grades line up at the same position while still reading as centered within the column.
     */
    private function gradeCellHtml(string $value, callable $escape): string
    {
        return '<td class="grade"><span class="grade-val">'.$escape($value).'</span></td>';
    }

    private function gradingSystemHtml(Collection $grades, callable $escape): string
    {
        if ($grades->isEmpty()) {
            $defaults = [
                ['91-100', 'A1'], ['81-90', 'A2'], ['71-80', 'B1'], ['61-70', 'B2'],
                ['51-60', 'C1'], ['41-50', 'C2'], ['33-40', 'D'], ['32 BELOW', 'E'],
            ];
            $left = array_slice($defaults, 0, 4);
            $right = array_slice($defaults, 4, 4);
        } else {
            $items = $grades->map(function ($g) {
                $min = (float) $g->min_percentage;
                $max = (float) $g->max_percentage;
                $range = $min <= 0.01
                    ? ((int) $max).' BELOW'
                    : ((int) $min).'-'.((int) $max);

                return [$range, (string) $g->grade];
            })->values()->all();
            $mid = (int) ceil(count($items) / 2);
            $left = array_slice($items, 0, $mid);
            $right = array_slice($items, $mid);
        }

        // Every cell gets its own border (inline, not the stylesheet's own "border: none" rule
        // for .grade-grid td — inline wins, so this also takes effect on already-created report
        // card templates) rather than just one outer box around the whole grid. The old blank
        // 10pt spacer column between the two halves is dropped in favour of equal cell padding,
        // so the grid reads as one clean 4-column table instead of two separate 2-column ones.
        $cellStyle = 'border:0.7pt solid #111;padding:2pt 6pt;text-align:center';
        $rows = max(count($left), count($right));
        $html = '<table class="grade-grid" style="border-collapse:collapse;width:100%">';
        for ($i = 0; $i < $rows; $i++) {
            $l = $left[$i] ?? ['', ''];
            $r = $right[$i] ?? ['', ''];
            $html .= '<tr>'
                .'<td class="g-range" style="'.$cellStyle.'">'.$escape($l[0]).'</td>'
                .'<td class="g-letter" style="'.$cellStyle.'">'.$escape($l[1]).'</td>'
                .'<td class="g-range" style="'.$cellStyle.'">'.$escape($r[0]).'</td>'
                .'<td class="g-letter" style="'.$cellStyle.'">'.$escape($r[1]).'</td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    /**
     * Dompdf's SVG <text> support doesn't honour x/y positioning or text-anchor reliably — axis
     * ticks and subject-abbreviation labels rendered as SVG text all collapsed onto the same spot
     * and read as one garbled run (e.g. "0255075100EngHINURDMatGK-SciS.Ara"). Plain HTML/CSS
     * (tables + colored cells) renders correctly in dompdf, so subject marks are shown as a
     * horizontal bar list instead: full subject name, a proportional bar, and the obtained/max
     * value — one row per subject, nothing overlapping.
     */
    private function subjectMarksChart(Collection $subjects): string
    {
        $items = $subjects->filter(fn ($s) => $s['marks_obtained'] !== null && $s['marks_obtained'] !== '')->values();
        if ($items->isEmpty()) {
            return '<div style="text-align:center;color:#888;font-size:8.5pt;padding:20pt 0">No marks to chart</div>';
        }

        $colors = ['#3b82f6', '#ef4444', '#22c55e', '#f97316', '#eab308', '#a855f7', '#06b6d4', '#ec4899', '#84cc16', '#14b8a6'];
        $maxY = max(100, (float) $items->max(fn ($s) => (float) ($s['max_marks'] ?: 100)));

        $numFmt = fn (float $n) => rtrim(rtrim(number_format($n, 1), '0'), '.');

        $rows = '';
        foreach ($items as $i => $s) {
            $obt = (float) $s['marks_obtained'];
            $max = (float) ($s['max_marks'] ?: 100);
            $pct = $maxY > 0 ? max(0, min(100, ($obt / $maxY) * 100)) : 0;
            $color = $colors[$i % count($colors)];
            $name = htmlspecialchars(mb_strtoupper((string) ($s['subject_name'] ?? '')), ENT_QUOTES, 'UTF-8');
            $value = $numFmt($obt).'/'.$numFmt($max);

            $rows .= '<tr>'
                .'<td style="border:none;padding:1.5pt 4pt 1.5pt 0;font-size:7pt;color:#374151;white-space:nowrap;text-align:right;width:56pt">'.$name.'</td>'
                .'<td style="border:none;padding:1.5pt 0">'
                    .'<table style="width:100%;border-collapse:collapse"><tr>'
                        .'<td style="border:none;padding:0;background:'.$color.';width:'.$pct.'%;height:7pt;font-size:1pt;line-height:7pt">&nbsp;</td>'
                        .'<td style="border:none;padding:0;width:'.(100 - $pct).'%;font-size:1pt;line-height:7pt">&nbsp;</td>'
                    .'</tr></table>'
                .'</td>'
                .'<td style="border:none;padding:1.5pt 0 1.5pt 4pt;font-size:7pt;color:#111827;font-weight:bold;white-space:nowrap;width:30pt">'.$value.'</td>'
                .'</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse">'.$rows.'</table>';
    }
}
