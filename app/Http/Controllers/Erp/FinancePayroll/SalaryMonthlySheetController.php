<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use App\Services\DocumentDataBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports salary slips in the exact shape of the school's own workbook ("GAS SALARY 2026-27"):
 * per month the same sheets it came from ("AUG -26" + "AUG-26-2"), school name on rows 1-3,
 * "SALARY PAYMENT DETAILS AUGUST-2026" + days in month (O4) on row 4, the S.NO ... SIGNATURE
 * (+ PMNT MODE: cash or which bank)
 * header on row 5, the same blocks of people with a subtotal row after each, and the same live
 * formulas (TOTAL DAYS = G+H, PER DAY = D/$O$4, THIS MONTH = D/E*I, G.SALARY = L-M).
 *
 * Slips imported from that workbook remember their sheet/block/row; slips created by hand go to
 * the month's first sheet in a Teaching / Non-Teaching / Drivers block. The file can be edited
 * and imported back on the Salary Sheet page.
 *
 * ?period=2026-08 exports one month; ?session=2026 exports April 2026 – March 2027.
 */
class SalaryMonthlySheetController extends Controller
{
    private const HEADERS = [
        'S.NO', 'EMPL_CODE', 'NAME', 'BASIC SALARY', 'DAYS IN MONTH', 'ABSENT', 'PRESENT', 'CL',
        'TOTAL DAYS', 'PER DAY', 'SALARY CALCULATE', "This Month \nSalary", 'ADV', 'G.SALARY', 'SIGNATURE', 'PMNT MODE',
    ];

    /** Block order for slips without a sheet position (created by hand). */
    private const TYPE_BLOCKS = ['teacher' => 1, 'staff' => 2, 'driver' => 3];

    private const MONTHS_SHORT = ['01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR', '05' => 'MAY', '06' => 'JUN', '07' => 'JUL', '08' => 'AUG', '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DEC'];

    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function export(Request $request)
    {
        $data = $request->validate([
            'period' => ['required_without:session', 'nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'session' => ['required_without:period', 'nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        if (! empty($data['period'])) {
            $periods = [$data['period']];
            $filename = "salary-sheet-{$data['period']}.xlsx";
        } else {
            $start = Carbon::create((int) $data['session'], 4, 1);
            $periods = collect(range(0, 11))->map(fn ($i) => $start->copy()->addMonths($i)->format('Y-m'))->all();
            $filename = 'salary-sheet-'.$data['session'].'-'.substr((string) ($data['session'] + 1), 2).'.xlsx';
        }

        $slips = SalarySlip::with(['employee', 'bankAccount'])->whereIn('period', $periods)->get();
        abort_if($slips->isEmpty(), 404, 'No salary data found for this period.');

        $schoolName = mb_strtoupper((string) ($this->dataBuilder->schoolContext()['school_name'] ?? 'School'));

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $usedTitles = [];

        foreach ($periods as $period) {
            $monthSlips = $slips->where('period', $period);
            if ($monthSlips->isEmpty()) {
                continue;
            }
            foreach ($this->groupIntoSheets($period, $monthSlips) as $title => $blocks) {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($this->uniqueTitle($title, $usedTitles));
                $this->writeSheet($sheet, $schoolName, $period, $blocks);
            }
        }
        $spreadsheet->setActiveSheetIndex($spreadsheet->getSheetCount() - 1);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, list<list<SalarySlip>>> sheet title => blocks of slips (in order)
     */
    private function groupIntoSheets(string $period, Collection $slips): array
    {
        [$year, $month] = explode('-', $period);
        $defaultTitle = (self::MONTHS_SHORT[$month] ?? $month).'-'.substr($year, 2);

        // Imported sheets in the order they were in the workbook ("AUG -26" before "AUG-26-2").
        $titles = $slips->pluck('sheet_name')->filter()->unique()
            ->sortBy(fn ($t) => [preg_match('/-\s*2\s*$/', $t) ? 1 : 0, $t])
            ->values();
        $firstTitle = $titles->first() ?? $defaultTitle;

        $sheets = [];
        foreach ($slips as $slip) {
            $title = $slip->sheet_name ?: $firstTitle;
            $block = $slip->sheet_name ? (int) $slip->sheet_block : 100 + self::TYPE_BLOCKS[$slip->employee_type];
            $sheets[$title][$block][] = $slip;
        }

        $ordered = [];
        foreach ($titles->push($firstTitle)->unique() as $title) {
            if (! isset($sheets[$title])) {
                continue;
            }
            ksort($sheets[$title]);
            $ordered[$title] = array_map(
                fn ($block) => collect($block)->sortBy(fn (SalarySlip $s) => [$s->sheet_row ?? PHP_INT_MAX, $s->employee->name ?? ''])->values()->all(),
                array_values($sheets[$title])
            );
        }

        return $ordered;
    }

    /** @param  list<list<SalarySlip>>  $blocks */
    private function writeSheet(Worksheet $sheet, string $schoolName, string $period, array $blocks): void
    {
        $date = Carbon::createFromFormat('Y-m-d', $period.'-01');
        $daysInMonth = (int) ($blocks[0][0]->days_in_month ?? $date->daysInMonth);

        $sheet->setCellValue('A1', $schoolName);
        $sheet->mergeCells('A1:P3');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->setCellValue('C4', 'SALARY PAYMENT DETAILS  '.mb_strtoupper($date->format('F-Y')));
        $sheet->mergeCells('C4:N4');
        $sheet->setCellValue('O4', $daysInMonth);
        $sheet->getStyle('A4:P4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->fromArray(self::HEADERS, null, 'A5');
        $sheet->getStyle('A5:P5')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(30);

        $row = 6;
        $subtotalRows = [];
        foreach ($blocks as $block) {
            $first = $row;
            foreach ($block as $i => $slip) {
                $sheet->setCellValue("A{$row}", $i + 1);
                $code = (string) ($slip->employee->employee_id ?? '');
                preg_match('/^[1-9]\d{0,14}$/', $code) ? $sheet->setCellValue("B{$row}", (int) $code) : $sheet->setCellValueExplicit("B{$row}", $code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $slip->employee->name ?? '');
                $sheet->setCellValue("D{$row}", (float) $slip->basic_salary);
                $sheet->setCellValue("E{$row}", (int) ($slip->days_in_month ?: $daysInMonth));
                $sheet->setCellValue("F{$row}", (float) $slip->absent);
                $sheet->setCellValue("G{$row}", (float) $slip->present);
                $sheet->setCellValue("H{$row}", (float) $slip->cl);
                $sheet->setCellValue("I{$row}", "=G{$row}+H{$row}");
                $sheet->setCellValue("J{$row}", "=+D{$row}/\$O\$4");
                $sheet->setCellValue("K{$row}", "=I{$row}*J{$row}");
                // Extra earnings/deductions entered on a slip by hand are folded into THIS MONTH.
                $extra = (float) $slip->allowances - (float) $slip->deductions;
                $sheet->setCellValue("L{$row}", "=(D{$row}/E{$row})*I{$row}".($extra != 0.0 ? sprintf('%+.2f', $extra) : ''));
                $sheet->setCellValue("M{$row}", (float) $slip->advance);
                $sheet->setCellValue("N{$row}", "=L{$row}-M{$row}");
                if ($slip->status === 'Paid') {
                    $sheet->setCellValue("O{$row}", 'PAID'.($slip->paid_on ? ' '.$slip->paid_on->format('d-m-Y') : ''));
                }
                $sheet->setCellValue("P{$row}", $this->paymentMode($slip));
                $row++;
            }
            $last = $row - 1;
            foreach (['D', 'L', 'M', 'N'] as $col) {
                $sheet->setCellValue("{$col}{$row}", "=SUM({$col}{$first}:{$col}{$last})");
            }
            $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $subtotalRows[] = $row;
            $row++;
        }

        if (count($subtotalRows) > 1) {
            $row++;
            $sheet->setCellValue("C{$row}", 'TOTAL');
            foreach (['D', 'L', 'M', 'N'] as $col) {
                $sheet->setCellValue("{$col}{$row}", '='.implode('+', array_map(fn ($r) => "{$col}{$r}", $subtotalRows)));
            }
            $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
            ]);
        }

        $sheet->getStyle("A6:P{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:P5")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E6:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D6:D{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("J6:N{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (['A' => 6, 'B' => 11, 'C' => 24, 'D' => 12, 'E' => 9, 'F' => 8, 'G' => 9, 'H' => 6, 'I' => 8, 'J' => 9, 'K' => 12, 'L' => 12, 'M' => 9, 'N' => 12, 'O' => 16, 'P' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A6');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * PMNT MODE for a paid slip: "CASH", or "BANK — <bank name> (A/c ••1662)" for the account the
     * salary was debited from. Blank while unpaid (or when the mode wasn't recorded).
     */
    private function paymentMode(SalarySlip $slip): string
    {
        if ($slip->status !== 'Paid' || ! $slip->payment_mode) {
            return '';
        }
        if ($slip->payment_mode !== 'Bank') {
            return mb_strtoupper($slip->payment_mode);
        }
        $bank = $slip->bankAccount;
        if (! $bank) {
            return 'BANK';
        }
        $tail = $bank->account_number ? ' (A/c ••'.substr((string) $bank->account_number, -4).')' : '';

        return 'BANK — '.mb_strtoupper(trim((string) $bank->bank_name)).$tail;
    }

    /** Sheet titles must be unique and at most 31 characters. */
    private function uniqueTitle(string $title, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $title)) ?: 'SALARY', 0, 28);
        $candidate = $base;
        for ($n = 2; isset($used[mb_strtolower($candidate)]); $n++) {
            $candidate = "{$base} ({$n})";
        }
        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}
