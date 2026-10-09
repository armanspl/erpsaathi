<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\AttendanceMonthlySummary;
use App\Models\ImportExportLog;
use App\Models\SchoolClass;
use App\Services\TermResultCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One-row-per-student consolidated result sheet for a class/term — the "Cross List" layout:
 * Adm No. / Student Name / Roll No. / <subject columns, final marks only> / Total Marks /
 * %age / Rank / Attendance / Attdn %age. Unlike the Exam Marks export (raw PT/NB/SEA/board
 * components, one sheet per class), this mirrors the already-computed totals shown on the
 * Term/Half Yearly Result screen, one line per student, matching the school's own
 * "Class_<N>_Exam_Cross_List" workbook format.
 *
 * Leaving school_class_id out exports every class — one sheet per class, since each class can
 * have its own subject set (matching how ClassTermMarksExportController handles "all classes").
 */
class ExamCrossListExportController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'academic_term_id' => 'required|integer|exists:academic_terms,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'section_id' => 'nullable|integer|exists:sections,id',
        ]);

        $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);

        $classes = isset($data['school_class_id'])
            ? SchoolClass::query()->where('id', $data['school_class_id'])->get()
            : SchoolClass::query()->orderBy('sort_order')->orderBy('id')->get();

        abort_if($classes->isEmpty(), 422, 'No classes found to export.');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->removeSheetByIndex(0);

        $usedTitles = [];
        $totalRows = 0;
        foreach ($classes as $class) {
            $payload = TermResultCalculator::forTerm($term, $class->id, null, $data['section_id'] ?? null);
            $rows = $payload['rows'];
            if ($rows === []) {
                continue;
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->uniqueSheetTitle($class->name, $usedTitles));
            $totalRows += $this->writeClassSheet($sheet, $term, $rows);
        }

        abort_if($spreadsheet->getSheetCount() === 0, 422, 'No results found for the selected class and term.');

        $spreadsheet->setActiveSheetIndex(0);

        $classToken = isset($data['school_class_id']) ? $this->classToken($classes->first()->name) : 'ALL-CLASSES';
        $termToken = preg_replace('/[^A-Za-z0-9]+/', '-', $term->name) ?: 'Term';
        $filename = "Class_{$classToken}_Exam_Cross_List_{$termToken}_".now()->format('Ymd-His').'.xlsx';

        ImportExportLog::create([
            'direction' => 'Export',
            'entity' => 'exam-cross-list',
            'filename' => $filename,
            'total_rows' => $totalRows,
            'success_count' => $totalRows,
            'failed_count' => 0,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->streamDownload(function () use ($spreadsheet) {
            \App\Support\ExcelBorders::applyThinGrid($spreadsheet);
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Writes one class's cross list onto the given (already-created) sheet and returns its row count.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeClassSheet($sheet, AcademicTerm $term, array $rows): int
    {
        // Subject columns come from the first row — every row in a single class/term shares the
        // same subject set (StudentSubjectEnrollmentService already filters optional subjects
        // per student before TermResultCalculator builds these rows). Drawing is left out of the
        // printed subject columns and of Total Marks/%age/Rank — matching the Report Card
        // (Drawing grade still feeds Co-Scholastic "Drawing & Art" there, not the marks total).
        $subjectNames = collect($rows[0]['subjects'] ?? [])
            ->pluck('subject_name')
            ->reject(fn ($name) => str_contains(mb_strtolower((string) $name), 'drawing'))
            ->values()
            ->all();

        $header = array_merge(
            ['Adm No.', 'Student Name', 'Roll No.'],
            $subjectNames,
            ['Total Marks', '%age', 'Rank', 'Attendance', 'Attdn %age']
        );

        $studentIds = collect($rows)->pluck('student_id')->all();
        $attendance = $this->attendanceFor($studentIds, $term);

        // Total Marks / %age / Rank already exclude Drawing (TermResultCalculator).
        usort($rows, fn ($a, $b) => (int) ($a['roll_no'] ?? PHP_INT_MAX) <=> (int) ($b['roll_no'] ?? PHP_INT_MAX));

        $sheetRows = [];
        foreach ($rows as $row) {
            $subjectValues = [];
            foreach ($subjectNames as $name) {
                $subject = collect($row['subjects'])->firstWhere('subject_name', $name);
                $subjectValues[] = $subject['marks_obtained'] ?? null;
            }
            [$attendanceLabel, $attendancePct] = $attendance[$row['student_id']] ?? ['— / —', null];

            $sheetRows[] = array_merge(
                [$row['admission_no'], $row['name'], $row['roll_no']],
                $subjectValues,
                [$row['obtained'], $row['percentage'], $row['rank'], $attendanceLabel, $attendancePct]
            );
        }

        $sheet->fromArray($header, null, 'A1');
        if ($sheetRows !== []) {
            // strictNullComparison=true — a genuine 0 mark must not be treated as "null" and skipped.
            $sheet->fromArray($sheetRows, null, 'A2', true);
        }

        $this->styleSheet($sheet, $header, count($sheetRows), count($subjectNames));

        return count($sheetRows);
    }

    /**
     * @param  array<int, string>  $header
     */
    private function styleSheet($sheet, array $header, int $rowCount, int $subjectCount): void
    {
        $colCount = count($header);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $lastRow = $rowCount > 0 ? $rowCount + 1 : 1;

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->freezePane('D2');
        $sheet->setAutoFilter("A1:{$lastCol}1");

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(9);
        for ($i = 0; $i < $subjectCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(4 + $i))->setWidth(12);
        }
        $tailStart = 4 + $subjectCount;
        $tailWidths = [12, 9, 7, 16, 11];
        foreach ($tailWidths as $offset => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($tailStart + $offset))->setWidth($width);
        }

        if ($rowCount < 1) {
            return;
        }

        $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $pctCol = Coordinate::stringFromColumnIndex($tailStart + 1);
        $sheet->getStyle("{$pctCol}2:{$pctCol}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $attdnPctCol = Coordinate::stringFromColumnIndex($tailStart + 4);
        $sheet->getStyle("{$attdnPctCol}2:{$attdnPctCol}{$lastRow}")->getNumberFormat()->setFormatCode('0.0');

        for ($row = 2; $row <= $lastRow; $row += 2) {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        }
    }

    /**
     * Batch attendance for every student on this sheet in two queries total (never N+1), reusing
     * the same monthly-summary-first, daily-marks-fallback approach as the Report Card PDF (see
     * ReportCardPdfService::attendanceSummary()) but split into the Cross List's two columns:
     * a "present / working" label and a bare percentage number for the dedicated %age column.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{0: string, 1: float|null}>
     */
    private function attendanceFor(array $studentIds, AcademicTerm $term): array
    {
        if ($studentIds === []) {
            return [];
        }

        $session = $term->academicSession;
        $sessionStartYear = $session?->start_date ? (int) $session->start_date->format('Y') : null;

        $result = [];
        $remaining = $studentIds;

        if ($sessionStartYear) {
            $monthly = AttendanceMonthlySummary::query()
                ->whereIn('student_id', $studentIds)
                ->where('session_start_year', $sessionStartYear)
                ->get(['student_id', 'working_days', 'days_present'])
                ->groupBy('student_id');

            foreach ($monthly as $studentId => $rows) {
                $workingDays = (int) $rows->sum('working_days');
                $daysPresent = (int) $rows->sum('days_present');
                if ($workingDays <= 0 && $daysPresent <= 0) {
                    continue;
                }
                $pct = $workingDays > 0 ? round(($daysPresent / $workingDays) * 100, 1) : 0.0;
                $result[$studentId] = [$daysPresent.' / '.$workingDays, $pct];
                $remaining = array_diff($remaining, [$studentId]);
            }
        }

        if ($remaining === []) {
            return $result;
        }

        $query = Attendance::query()
            ->where('attendable_type', 'student')
            ->whereIn('attendable_id', $remaining);

        if ($session?->start_date && $session?->end_date) {
            $query->whereDate('date', '>=', $session->start_date->toDateString())
                ->whereDate('date', '<=', $session->end_date->toDateString());
        }

        $byStudent = $query->get(['attendable_id', 'status'])->groupBy('attendable_id');
        foreach ($byStudent as $studentId => $rows) {
            $total = $rows->count();
            if ($total === 0) {
                continue;
            }
            $present = $rows->whereIn('status', ['Present', 'Late', 'Half Day'])->count();
            $pct = round(($present / $total) * 100, 1);
            $result[$studentId] = [$present.' / '.$total, $pct];
        }

        return $result;
    }

    private function classToken(string $className): string
    {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $className) ?: 'CLASS');

        return $key;
    }

    /**
     * Excel sheet titles must be unique and ≤31 chars — class names are usually short and already
     * unique, but this guards against both limits being hit when exporting every class at once.
     *
     * @param  array<string, true>  $usedTitles
     */
    private function uniqueSheetTitle(string $className, array &$usedTitles): string
    {
        $base = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $className) ?: 'Class', 0, 31);
        $title = $base;
        $suffix = 2;
        while (isset($usedTitles[$title])) {
            $title = mb_substr($base, 0, 31 - mb_strlen((string) $suffix) - 1).'-'.$suffix;
            $suffix++;
        }
        $usedTitles[$title] = true;

        return $title;
    }
}
