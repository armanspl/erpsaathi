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
        $sessionName = $session?->name ?: ($school['session_year'] ?? '');

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $subjects = collect($row['subjects'] ?? []);
        $grades = GradeSystem::orderByDesc('min_percentage')->get();
        $isAnnual = ($row['format'] ?? '') === 'annual_term';

        $father = $student?->father?->name ?? ($row['father_name'] ?? '—');
        $mother = $student?->mother?->name ?? ($row['mother_name'] ?? '—');
        $dob = $student?->dob ? Carbon::parse($student->dob)->format('d-m-Y') : '—';
        $className = $student?->schoolClass?->name ?? ($row['school_class_name'] ?? '—');
        $sectionName = $student?->section?->name ?? ($row['section_name'] ?? '—');

        $obtained = (float) ($row['obtained'] ?? 0);
        $maxTotal = (float) ($row['max_total'] ?? 0);
        $percentage = (float) ($row['percentage'] ?? 0);
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

        return array_merge($school, $this->dataBuilder->accentPalette($exam->pdf_accent_color), [
            // "None" isn't just a grey accent_color — the header bands fill with accent_color/
            // accent_dark and hardcode white text on top, so a dark-grey fill would just swap
            // "coloured header" for "grey header", still with white lettering. This class lets
            // the template's CSS drop the fill entirely and force black text instead (see
            // `.sheet.mono` rules in the classic/modern report_card templates).
            'mono_class' => $exam->pdf_accent_color === 'none' ? 'mono' : '',
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
            'remarks' => $row['remarks'] ?? 'GOOD / VERY GOOD / EXCELLENT',
            'co_scholastic_html' => $this->coScholasticHtml($row, $escape),
            'grading_html' => $this->gradingSystemHtml($grades, $escape),
            'chart_html' => $this->barChartSvg($subjects),
            'class_teacher_signature' => "Class Teacher's Sign",
            'principal_signature' => "Principal's Sign",
            'stamp_html' => ! empty($school['school_stamp'])
                ? '<img class="stamp" src="'.$school['school_stamp'].'" alt="Stamp" />'
                : '',
            'class_teacher_sign_html' => $classTeacher?->signature_path
                ? '<img class="sig-img" src="'.$this->dataBuilder->resolveStoredImage($classTeacher->signature_path).'" alt="Class Teacher" />'
                : '<div class="sig-space"></div>',
            'principal_sign_html' => ! empty($school['principal_signature_image'])
                ? '<img class="sig-img" src="'.$school['principal_signature_image'].'" alt="Principal" />'
                : '<div class="sig-space"></div>',
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
                    .'<td class="grade">—</td>'
                    .'</tr>';
            }
            $obtNum = $obt === null || $obt === '' ? null : (float) $obt;
            $pct = ($obtNum !== null && $max > 0) ? round(($obtNum / $max) * 100, 1) : null;
            $grade = $pct !== null
                ? ($grades->first(fn ($g) => $pct >= (float) $g->min_percentage && $pct <= (float) $g->max_percentage)?->grade ?? '—')
                : '—';
            $fmt = fn (?float $n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

            return '<tr>'
                .'<td class="subj">'.$escape(mb_strtoupper((string) ($s['subject_name'] ?? ''))).'</td>'
                .'<td class="num">'.$fmt($obtNum).'</td>'
                .'<td class="num">'.($max > 0 ? $fmt($max) : '—').'</td>'
                .'<td class="num">'.($pct === null ? '—' : $pct).'</td>'
                .'<td class="grade">'.$escape($grade).'</td>'
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
            .'<tr><th>Subject</th><th>Obtained</th><th>Max</th><th>%</th><th>Grade</th></tr>';
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
        $bottom = '<tr><th>Subject</th>';
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
                            $html .= '<td class="grade">'.$escape((string) ($s['grade'] ?? '—')).'</td>';
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
                        $html .= '<td class="grade">'.$escape((string) ($s['grade'] ?? '—')).'</td>';
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
     * A bordered "Co-Scholastic Area | Remarks" grid, with one Remarks sub-column per term
     * when per-term grades are available (Annual report), or a single flat Remarks column
     * when they aren't (Individual exam / single-term reports have no per-term co-scholastic
     * data at all — see AnnualReportCalculator, the only calculator that populates it).
     *
     * @param  array<string, mixed>  $row
     */
    private function coScholasticHtml(array $row, callable $escape): string
    {
        $areaLabels = CoScholasticGrade::AREAS;
        $items = $row['co_scholastic'] ?? [];
        $byArea = is_array($items) ? collect($items)->keyBy('area') : collect();

        $termNames = collect($byArea->first()['terms'] ?? [])->map(fn ($t) => (string) ($t['term_name'] ?? 'Term'))->all();
        $termCount = count($termNames);

        if ($termCount === 0) {
            $thead = '<tr><th class="area-head">Co-Scholastic Area</th><th class="remarks-head">Remarks</th></tr>';
        } else {
            $thead = '<tr><th class="area-head" rowspan="2">Co-Scholastic Area</th><th class="remarks-head" colspan="'.$termCount.'">Remarks</th></tr><tr>';
            foreach ($termNames as $name) {
                $thead .= '<th class="term-head">'.$escape($name).'</th>';
            }
            $thead .= '</tr>';
        }

        $bodyRows = '';
        foreach ($areaLabels as $key => $label) {
            $bodyRows .= '<tr><td class="area">'.$escape(mb_strtoupper($label)).'</td>';
            $terms = $byArea->get($key)['terms'] ?? [];
            if ($termCount === 0) {
                $bodyRows .= '<td class="grade">—</td>';
            } else {
                foreach ($terms as $t) {
                    $g = trim((string) ($t['grade'] ?? ''));
                    $bodyRows .= '<td class="grade">'.($g !== '' ? $escape($g) : '—').'</td>';
                }
            }
            $bodyRows .= '</tr>';
        }

        return '<table class="co-grid">'.$thead.$bodyRows.'</table>';
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

        $rows = max(count($left), count($right));
        $html = '<table class="grade-grid">';
        for ($i = 0; $i < $rows; $i++) {
            $l = $left[$i] ?? ['', ''];
            $r = $right[$i] ?? ['', ''];
            $html .= '<tr>'
                .'<td class="g-range">'.$escape($l[0]).'</td><td class="g-letter">'.$escape($l[1]).'</td>'
                .'<td style="width:10pt"></td>'
                .'<td class="g-range">'.$escape($r[0]).'</td><td class="g-letter">'.$escape($r[1]).'</td>'
                .'</tr>';
        }

        return $html.'</table>';
    }

    private function barChartSvg(Collection $subjects): string
    {
        $items = $subjects->filter(fn ($s) => $s['marks_obtained'] !== null && $s['marks_obtained'] !== '')->values();
        if ($items->isEmpty()) {
            return '<div style="text-align:center;color:#888;font-size:8.5pt;padding:20pt 0">No marks to chart</div>';
        }

        $colors = ['#3b82f6', '#ef4444', '#22c55e', '#f97316', '#eab308', '#a855f7', '#06b6d4', '#ec4899', '#84cc16', '#14b8a6'];
        $maxY = max(100, (float) $items->max(fn ($s) => (float) ($s['max_marks'] ?: 100)));
        $chartW = 220;
        $chartH = 76;
        $padL = 22;
        $padB = 22;
        $padT = 4;
        $plotW = $chartW - $padL - 8;
        $plotH = $chartH - $padB - $padT;
        $n = $items->count();
        $gap = 4;
        $barW = max(8, ($plotW - ($n + 1) * $gap) / $n);

        $bars = '';
        $labels = '';
        $legend = '';
        foreach ($items as $i => $s) {
            $obt = (float) $s['marks_obtained'];
            $h = $maxY > 0 ? ($obt / $maxY) * $plotH : 0;
            $x = $padL + $gap + $i * ($barW + $gap);
            $y = $padT + ($plotH - $h);
            $color = $colors[$i % count($colors)];
            $bars .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barW.'" height="'.max(1, $h).'" fill="'.$color.'" />';
            $short = mb_substr((string) ($s['subject_name'] ?? ''), 0, 3);
            $labels .= '<text x="'.($x + $barW / 2).'" y="'.($chartH - 10).'" text-anchor="middle" font-size="6" fill="#333">'.htmlspecialchars($short, ENT_QUOTES).'</text>';
            $legend .= '<div style="font-size:6.5pt;margin:0 0 1pt"><span style="display:inline-block;width:7pt;height:7pt;background:'.$color.';margin-right:3pt"></span>'
                .htmlspecialchars((string) ($s['subject_name'] ?? ''), ENT_QUOTES).'</div>';
        }

        $ticks = '';
        foreach ([0, 25, 50, 75, 100] as $t) {
            if ($t > $maxY) {
                continue;
            }
            $ty = $padT + $plotH - ($t / $maxY) * $plotH;
            $ticks .= '<line x1="'.($padL - 2).'" y1="'.$ty.'" x2="'.$padL.'" y2="'.$ty.'" stroke="#666" stroke-width="0.6" />';
            $ticks .= '<text x="'.($padL - 4).'" y="'.($ty + 2).'" text-anchor="end" font-size="6" fill="#555">'.$t.'</text>';
        }

        $svg = '<svg width="'.$chartW.'" height="'.$chartH.'" viewBox="0 0 '.$chartW.' '.$chartH.'" xmlns="http://www.w3.org/2000/svg">'
            .'<line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.($padT + $plotH).'" stroke="#333" stroke-width="0.8" />'
            .'<line x1="'.$padL.'" y1="'.($padT + $plotH).'" x2="'.($chartW - 6).'" y2="'.($padT + $plotH).'" stroke="#333" stroke-width="0.8" />'
            .$ticks.$bars.$labels
            .'</svg>';

        return '<table style="width:100%;border-collapse:collapse"><tr>'
            .'<td style="border:none;vertical-align:middle">'.$svg.'</td>'
            .'<td style="border:none;vertical-align:middle;width:78pt;padding-left:4pt">'.$legend.'</td>'
            .'</tr></table>';
    }
}
