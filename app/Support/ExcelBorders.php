<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExcelBorders
{
    /**
     * Thin solid black borders on all four sides of every cell in every worksheet — blank cells
     * inside the used range included. Call right before writing the workbook so it overrides any
     * per-sheet border styling applied earlier.
     */
    public static function applyThinGrid(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $lastCol = $sheet->getHighestColumn();
            $lastRow = $sheet->getHighestRow();

            $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }
    }
}
