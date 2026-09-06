<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream a header + rows grid as csv | xlsx | pdf.
 *
 * Mirrors the private helpers in FeeDueController so the Reports register
 * exports share one implementation. Rows are plain arrays of scalars.
 */
class TabularExport
{
    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function stream(array $header, array $rows, string $basename, string $title, string $format = 'xlsx'): StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            $format = 'xlsx';
        }
        $filename = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        return match ($format) {
            'csv' => self::csv($header, $rows, $filename),
            'pdf' => self::pdf($header, $rows, $filename, $title),
            default => self::xlsx($header, $rows, $filename, $title),
        };
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function csv(array $header, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function xlsx(array $header, array $rows, string $filename, string $title): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $sheet->fromArray($header, null, 'A1');
        if ($rows) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        for ($col = 1, $n = count($header); $col <= $n; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function pdf(array $header, array $rows, string $filename, string $title): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $title) {
            $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $thead = '<tr>'.collect($header)->map(fn ($h) => '<th>'.$escape($h).'</th>')->implode('').'</tr>';
            $tbody = '';
            foreach ($rows as $row) {
                $tbody .= '<tr>';
                foreach ($row as $cell) {
                    $tbody .= '<td>'.$escape($cell).'</td>';
                }
                $tbody .= '</tr>';
            }

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                @page { margin: 10mm; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
                h1 { font-size: 13pt; margin: 0 0 8pt; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 0.5pt solid #999; padding: 3pt 4pt; vertical-align: top; }
                th { background: #eee; font-weight: bold; }
            </style></head><body>
                <h1>'.$escape($title).'</h1>
                <table><thead>'.$thead.'</thead><tbody>'.$tbody.'</tbody></table>
            </body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
