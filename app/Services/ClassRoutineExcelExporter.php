<?php

namespace App\Services;

use App\Models\ClassRoutineEntry;
use App\Models\ClassRoutineSheet;
use App\Models\SchoolClass;
use App\Models\Teacher;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rebuilds the school's own printed Class Routine layout (see ClassRoutineImportParser's class
 * doc for the source shape this mirrors) from the saved routine: one sheet per day, each with the
 * same teacher-legend-plus-class-grid layout, Verdana font, thin borders, landscape A4 page setup
 * and — the point of this exporter — every teacher badge coloured exactly as saved, so the
 * exported file looks like the school's own workbook rather than a generic report.
 */
class ClassRoutineExcelExporter
{
    private const FONT = 'Verdana';

    public function stream(ClassRoutineSheet $sheet): StreamedResponse
    {
        $spreadsheet = $this->build($sheet);
        $writer = new Xlsx($spreadsheet);
        $filename = 'class-routine-'.$sheet->id.'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(ClassRoutineSheet $sheet): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(10);
        $spreadsheet->removeSheetByIndex(0);

        $classes = SchoolClass::orderBy('sort_order')->get(['id', 'name']);
        $teachers = Teacher::orderBy('name')->get(['id', 'name', 'color']);
        $entriesByDay = $sheet->entries->groupBy('day_of_week');

        $first = true;
        foreach (ClassRoutineEntry::DAYS as $dow => $label) {
            $dayEntries = $entriesByDay->get($dow, collect());
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr($label, 0, 31));
            $this->buildDaySheet($ws, $label, $classes, $teachers, $dayEntries, $sheet->periods_per_day);
            if ($first) {
                $spreadsheet->setActiveSheetIndex(0);
                $first = false;
            }
        }

        return $spreadsheet;
    }

    private function buildDaySheet(Worksheet $ws, string $dayLabel, $classes, $teachers, $dayEntries, int $periods): void
    {
        $periodCols = [];
        for ($p = 1; $p <= $periods; $p++) {
            $periodCols[$p] = Coordinate::stringFromColumnIndex(6 + $p); // G=7 -> period 1
        }
        $lastPeriodCol = $periodCols[$periods];
        $remarksCol = Coordinate::stringFromColumnIndex(6 + $periods + 1);

        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->getSheetView()->setZoomScale(100);

        foreach (['A' => 3.5, 'B' => 15, 'C' => 9.5, 'D' => 2.5, 'E' => 9, 'F' => 9.5] as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
        foreach ($periodCols as $col) {
            $ws->getColumnDimension($col)->setWidth(15);
        }
        $ws->getColumnDimension($remarksCol)->setWidth(14);

        // Title
        $ws->setCellValue('A1', strtoupper($dayLabel).' — CLASS ROUTINE');
        $ws->mergeCells('A1:'.$remarksCol.'1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row 2
        $headers = ['A2' => '#', 'B2' => 'TEACHERS', 'C2' => 'PERIOD', 'E2' => 'CLASS', 'F2' => 'PERIOD'];
        foreach ($headers as $addr => $text) {
            $ws->setCellValue($addr, $text);
        }
        foreach ($periodCols as $p => $col) {
            $ws->setCellValue($col.'2', $p);
        }
        $ws->setCellValue($remarksCol.'2', 'REMARKS');
        $ws->getStyle('A2:'.$remarksCol.'2')->getFont()->setBold(true);
        $ws->getStyle('A2:'.$remarksCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // --- Teacher legend (A-C) ---
        $row = 3;
        $serial = 1;
        foreach ($teachers as $teacher) {
            $ws->setCellValue('A'.$row, $serial);
            $ws->setCellValue('B'.$row, mb_strtoupper((string) $teacher->name));
            if ($teacher->color) {
                $this->fillCell($ws, 'B'.$row, $teacher->color);
                $ws->getStyle('B'.$row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($teacher->color)));
            }
            $ws->getStyle('B'.$row)->getFont()->setBold(true);
            $row++;
            $serial++;
        }
        $legendLastRow = $row - 1;
        // The C column's COUNTIF formulas need the grid's row extent, which isn't known until the
        // class grid below is built — written in a second pass over the legend rows further down.

        // --- Class grid (E-N) ---
        $gridRow = 3;
        foreach ($classes as $class) {
            $subRow = $gridRow;
            $teachRow = $gridRow + 1;

            $ws->setCellValue('E'.$subRow, mb_strtoupper((string) $class->name));
            $ws->mergeCells('E'.$subRow.':E'.$teachRow);
            $ws->getStyle('E'.$subRow)->getFont()->setBold(true);
            $ws->getStyle('E'.$subRow.':E'.$teachRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $ws->setCellValue('F'.$subRow, 'SUB');
            $ws->setCellValue('F'.$teachRow, 'TEACH');
            $ws->getStyle('F'.$subRow.':F'.$teachRow)->getFont()->setBold(true);

            $entriesForClass = $dayEntries->where('school_class_id', $class->id)->keyBy('period_number');
            $remarksText = null;
            foreach ($periodCols as $p => $col) {
                $entry = $entriesForClass->get($p);
                if (! $entry) {
                    continue;
                }
                $subjectText = $entry->subject?->name ?? $entry->subject_label;
                $teacherText = $entry->teacher?->name ?? $entry->teacher_label;
                if ($subjectText) {
                    $ws->setCellValue($col.$subRow, mb_strtoupper($subjectText));
                }
                if ($teacherText) {
                    $ws->setCellValue($col.$teachRow, mb_strtoupper($teacherText));
                    $color = $entry->color ?? $entry->teacher?->color;
                    if ($color) {
                        $this->fillCell($ws, $col.$teachRow, $color);
                        $ws->getStyle($col.$teachRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->contrastingFontArgb($color)));
                    }
                    $ws->getStyle($col.$teachRow)->getFont()->setBold(true);
                }
                $remarksText ??= $entry->remarks;
            }
            if ($remarksText) {
                $ws->setCellValue($remarksCol.$subRow, $remarksText);
            }

            $gridRow += 2;
        }
        $gridLastRow = $gridRow - 1;

        // Now that the grid's row extent is known, write the real COUNTIF/SUM formulas — the whole
        // grid range is safe to scan since a SUB row never contains a teacher's name.
        $row = 3;
        foreach ($teachers as $teacher) {
            $ws->setCellValue('C'.$row, '=COUNTIF(G3:'.$lastPeriodCol.$gridLastRow.',B'.$row.')');
            $row++;
        }
        $ws->setCellValue('C'.($legendLastRow + 1), '=SUM(C3:C'.$legendLastRow.')');

        // --- Borders + alignment for the whole used range ---
        $lastRow = max($legendLastRow, $gridLastRow);
        $usedRange = 'A2:'.$remarksCol.$lastRow;
        $ws->getStyle($usedRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
        $ws->getStyle('G3:'.$lastPeriodCol.$gridLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('B3:B'.$legendLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range(2, $lastRow) as $r) {
            $ws->getRowDimension($r)->setRowHeight(15);
        }
    }

    private function fillCell(Worksheet $ws, string $addr, string $hex): void
    {
        $hex = ltrim($hex, '#');
        $ws->getStyle($addr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.strtoupper($hex));
    }

    /** White text on a dark fill, black text on a light one — same rule PhpSpreadsheet users
     *  commonly apply; keeps every teacher badge readable regardless of their chosen colour. */
    private function contrastingFontArgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (mb_strlen($hex) !== 6) {
            return 'FF000000';
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? 'FF000000' : 'FFFFFFFF';
    }
}
