<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use App\Services\DocumentDataBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports one period's salary_slips back out in the exact shape of the school's source
 * workbook: school name, "SALARY PAYMENT DETAILS <MONTH>" + Days-in-Month, headers, employees
 * grouped Teaching Staff -> Class IV/Office Staff -> Drivers (same grouping the monthly
 * importer derives from which of Teacher/Staff/Driver an EMPL_CODE matched), a subtotal row
 * per group, and a grand total row.
 */
class SalaryMonthlySheetController extends Controller
{
    private const HEADERS = [
        'S.NO', 'EMPL_CODE', 'NAME', 'BASIC SALARY', 'DAYS IN MONTH', 'ABSENT', 'PRESENT', 'CL',
        'TOTAL DAYS', 'PER DAY', 'This Month Salary', 'ADV', 'G.SALARY', 'SIGNATURE',
    ];

    /** employee_type -> print label, in the source file's group order. */
    private const GROUPS = [
        'teacher' => 'TEACHING STAFF',
        'staff' => 'CLASS IV / OFFICE STAFF',
        'driver' => 'DRIVERS',
    ];

    private const TOTAL_KEYS = ['basic_salary', 'this_month_salary', 'advance', 'net_salary'];

    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function export(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $period = $data['period'];

        $slips = SalarySlip::with('employee')->where('period', $period)->get();
        abort_if($slips->isEmpty(), 404, 'No salary data found for this period.');

        $school = $this->dataBuilder->schoolContext();
        $periodDate = Carbon::createFromFormat('Y-m', $period);
        $periodLabel = mb_strtoupper($periodDate->format('F-Y'));
        $daysInMonth = (int) ($slips->first()->days_in_month ?? $periodDate->daysInMonth);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Salary Sheet');

        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADERS));

        $sheet->setCellValue('A1', $school['school_name'] ?? 'School');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue('A4', "SALARY PAYMENT DETAILS {$periodLabel}");
        $sheet->mergeCells('A4:J4');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->setCellValue("K4", "DAYS IN MONTH: {$daysInMonth}");
        $sheet->mergeCells("K4:{$lastCol}4");
        $sheet->getStyle('K4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        $sheet->fromArray(self::HEADERS, null, 'A5');
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '7C3AED']]],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(28);
        $sheet->freezePane('A6');
        $sheet->setAutoFilter("A5:{$lastCol}5");
        $sheet->getTabColor()->setRGB('7C3AED');

        $widths = ['A' => 6, 'B' => 12, 'C' => 22, 'D' => 13, 'E' => 13, 'F' => 10, 'G' => 10, 'H' => 8, 'I' => 11, 'J' => 10, 'K' => 15, 'L' => 10, 'M' => 13, 'N' => 14];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $row = 6;
        $grand = array_fill_keys(self::TOTAL_KEYS, 0.0);

        foreach (self::GROUPS as $type => $groupLabel) {
            $groupSlips = $slips->where('employee_type', $type)->sortBy(fn (SalarySlip $s) => $s->employee->name ?? '')->values();
            if ($groupSlips->isEmpty()) {
                continue;
            }

            $sheet->setCellValue("A{$row}", $groupLabel);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;

            $sub = array_fill_keys(self::TOTAL_KEYS, 0.0);
            foreach ($groupSlips as $i => $slip) {
                $sheet->fromArray([
                    $i + 1,
                    $slip->employee->employee_id ?? '',
                    $slip->employee->name ?? '',
                    (float) $slip->basic_salary,
                    $slip->days_in_month,
                    (float) $slip->absent,
                    (float) $slip->present,
                    (float) $slip->cl,
                    (float) $slip->total_days,
                    (float) $slip->per_day_rate,
                    (float) $slip->this_month_salary,
                    (float) $slip->advance,
                    (float) $slip->net_salary,
                    '',
                ], null, "A{$row}");

                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getRowDimension($row)->setRowHeight(18);
                $row++;

                foreach (self::TOTAL_KEYS as $key) {
                    $sub[$key] += (float) $slip->{$key};
                    $grand[$key] += (float) $slip->{$key};
                }
            }

            $sheet->setCellValue("C{$row}", 'SUB TOTAL');
            $sheet->setCellValue("D{$row}", $sub['basic_salary']);
            $sheet->setCellValue("K{$row}", $sub['this_month_salary']);
            $sheet->setCellValue("L{$row}", $sub['advance']);
            $sheet->setCellValue("M{$row}", $sub['net_salary']);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '7C3AED']]],
            ]);
            $row += 2;
        }

        $sheet->setCellValue("C{$row}", 'GRAND TOTAL');
        $sheet->setCellValue("D{$row}", $grand['basic_salary']);
        $sheet->setCellValue("K{$row}", $grand['this_month_salary']);
        $sheet->setCellValue("L{$row}", $grand['advance']);
        $sheet->setCellValue("M{$row}", $grand['net_salary']);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);

        $sheet->getStyle("D6:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("J6:M{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "salary-sheet-{$period}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
