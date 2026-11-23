<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Services\StudRecSumReport;
use App\Support\TabularExport;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fee Management › Fee Reports › Stud_Rec_Sum — the Global Workbook's Stud_Rec_Sum sheet as its
 * own report: same rows and filters as Demand Slip (StudRecSumReport), exported as a plain
 * Stud_Rec_Sum sheet in Arial 10.
 */
class StudRecSumController extends DemandSlipController
{
    protected string $filePrefix = 'stud-rec-sum';

    private const FONT = ['name' => 'Arial', 'size' => 10];

    public function export(Request $request): StreamedResponse
    {
        $format = $request->validate(['format' => 'nullable|in:xlsx,csv,pdf'])['format'] ?? 'xlsx';
        [$session, $rows] = $this->filteredRows($request);
        $keys = $this->selectedColumns($request);
        $headers = array_map(fn ($k) => StudRecSumReport::COLUMNS[$k], $keys);
        $title = 'Stud_Rec_Sum'.($session ? ' ('.$session->name.')' : '')
            .($request->input('tab') === 'left' ? ' · Left Students' : '').' · '.$this->period($session)['label'];

        if ($format === 'pdf') {
            return $this->pdf($keys, $headers, $rows, $title, $request);
        }
        if ($format === 'xlsx') {
            return $this->sheet($keys, $rows);
        }

        $body = array_map(fn (array $row) => array_map(fn ($k) => $row[$k], $keys), $rows);
        if ($body) {
            $totals = $this->totals($rows);
            $body[] = array_map(fn ($k, $i) => $i === 0 ? 'TOTAL ('.count($rows).')' : ($totals[$k] ?? ''), $keys, array_keys($keys));
        }

        return TabularExport::stream($headers, $body, $this->filePrefix, $title, $format);
    }

    /**
     * Workbook-style sheet named Stud_Rec_Sum: header row, one row per student, totals row.
     * Every cell Arial 10.
     *
     * @param  list<string>  $keys
     * @param  list<array<string, mixed>>  $rows
     */
    private function sheet(array $keys, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName(self::FONT['name'])->setSize(self::FONT['size']);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stud_Rec_Sum');

        $isAmount = fn (string $k) => in_array($k, StudRecSumReport::AMOUNT_COLUMNS, true);
        $lastCol = Coordinate::stringFromColumnIndex(count($keys));

        foreach ($keys as $i => $k) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', StudRecSumReport::COLUMNS[$k]);
        }
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
        ]);

        $r = 1;
        foreach ($rows as $row) {
            $r++;
            foreach ($keys as $i => $k) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
                $isAmount($k)
                    ? $sheet->setCellValue($cell, (float) $row[$k])
                    : $sheet->setCellValueExplicit($cell, (string) $row[$k], DataType::TYPE_STRING);
            }
        }
        $lastDataRow = $r;

        if ($rows) {
            $r++;
            $totals = $this->totals($rows);
            foreach ($keys as $i => $k) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
                if ($isAmount($k)) {
                    $sheet->setCellValue($cell, $totals[$k]);
                } elseif ($i === 0) {
                    $sheet->setCellValue($cell, 'TOTAL ('.count($rows).')');
                }
            }
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        foreach ($keys as $i => $k) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            if ($isAmount($k)) {
                $sheet->getStyle("{$col}2:{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00');
                $sheet->getColumnDimension($col)->setWidth(12);
            } else {
                $sheet->getColumnDimension($col)->setWidth(match ($k) {
                    'name', 'father_name' => 26,
                    'address' => 30,
                    'session', 'class' => 9,
                    default => 13,
                });
            }
        }
        // Re-assert the font on the used range (styles applied above keep it, this is belt-and-braces).
        $sheet->getStyle("A1:{$lastCol}{$r}")->getFont()->setName(self::FONT['name'])->setSize(self::FONT['size']);
        $sheet->freezePane('A2');
        if ($rows) {
            $sheet->setAutoFilter("A1:{$lastCol}{$lastDataRow}");
        }
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filePrefix.'-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
