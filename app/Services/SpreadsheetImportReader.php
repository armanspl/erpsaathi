<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetImportReader
{
    /**
     * Read an uploaded .xlsx/.xls/.csv file's first worksheet into a header row
     * (trimmed + lowercased) and the remaining data rows, so entity-specific
     * importers only need to deal with column mapping and row validation.
     *
     * $headerRow lets a caller skip leading title row(s) — e.g. a UDISE export whose
     * row 1 is a title label ("List of All Students - ...") and whose real header is row 2.
     *
     * @return array{header: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public static function read(UploadedFile $file, int $headerRow = 1): array
    {
        $path = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        // Single-sheet fast path: load only the first sheet when possible.
        if (method_exists($reader, 'listWorksheetNames') && method_exists($reader, 'setLoadSheetsOnly')) {
            $names = $reader->listWorksheetNames($path);
            if ($names !== []) {
                $reader->setLoadSheetsOnly([$names[0]]);
            }
        }

        $spreadsheet = $reader->load($path);
        $parsed = self::fromWorksheet($spreadsheet->getActiveSheet(), $headerRow);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $parsed;
    }

    /**
     * List worksheet titles in workbook order (trimmed).
     *
     * @return list<string>
     */
    public static function sheetNames(UploadedFile $file): array
    {
        return self::listSheetNames($file->getRealPath());
    }

    /**
     * Find a sheet by case-insensitive exact title (after trim).
     *
     * @return array{header: array<int, string>, rows: array<int, array<int, mixed>>, title: string}|null
     */
    public static function readSheetByName(UploadedFile $file, string $name, int $headerRow = 1): ?array
    {
        $path = $file->getRealPath();
        $needle = strtolower(trim($name));
        $titles = self::listSheetNames($path);
        $match = null;
        foreach ($titles as $title) {
            if (strtolower($title) === $needle) {
                $match = $title;
                break;
            }
        }
        if ($match === null) {
            return null;
        }

        $spreadsheet = self::loadSheetsOnly($path, [$match]);
        $parsed = self::fromWorksheet($spreadsheet->getActiveSheet(), $headerRow);
        $parsed['title'] = $match;
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $parsed;
    }

    /**
     * @return array{header: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public static function fromWorksheet(Worksheet $sheet, int $headerRow = 1): array
    {
        // calculateFormulas=false, formatData=false — evaluating every formula on large
        // school workbooks (pivots, bank sheets) can exceed PHP's max execution time.
        $rows = $sheet->toArray(null, false, false, false);
        $rows = array_slice($rows, $headerRow - 1);

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Load only named sheets (case-insensitive match on titles), data-only, no formula calc.
     *
     * @param  list<string>  $wantedTitles  Exact titles to load (as they appear in the file)
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function loadSheetsOnly(string $path, array $wantedTitles): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if ($wantedTitles !== [] && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly($wantedTitles);
        }

        return $reader->load($path);
    }

    /**
     * @return list<string>
     */
    public static function listSheetNames(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        // Keep original worksheet titles (do not trim) — client files use trailing
        // spaces like "CLASS 1 HY " / "CLASS 1 ANNU ", and setLoadSheetsOnly is exact-match.
        return array_map(
            static fn ($n) => (string) $n,
            $reader->listWorksheetNames($path)
        );
    }
}
