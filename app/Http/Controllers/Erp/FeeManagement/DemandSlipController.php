<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\DocumentDataBuilder;
use App\Services\StudRecSumReport;
use App\Support\TabularExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fee Management › Demand Slip — the Global Workbook's Stud_Rec_Sum sheet on screen
 * (same StudRecSumReport rows), filtered by class / section / vehicle / dues, with
 * Excel / CSV / PDF export of the chosen columns. Printing is done in the browser.
 */
class DemandSlipController extends Controller
{
    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function index(Request $request)
    {
        [$session, $rows, $vehicles] = $this->filteredRows($request);
        $school = $this->dataBuilder->schoolContext();

        return response()->json([
            'session' => $session?->only(['id', 'name']),
            'school' => ['name' => $school['school_name'], 'address' => $school['school_address'], 'phone' => $school['school_phone']],
            'vehicles' => $vehicles,
            'columns' => collect(StudRecSumReport::COLUMNS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'amount' => in_array($key, StudRecSumReport::AMOUNT_COLUMNS, true),
            ])->values(),
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->validate(['format' => 'nullable|in:xlsx,csv,pdf'])['format'] ?? 'xlsx';
        [$session, $rows] = $this->filteredRows($request);
        $keys = $this->selectedColumns($request);
        $headers = array_map(fn ($k) => StudRecSumReport::COLUMNS[$k], $keys);
        $body = array_map(fn (array $row) => array_map(fn ($k) => $row[$k], $keys), $rows);
        $title = 'Demand Slip — Stud_Rec_Sum'.($session ? ' ('.$session->name.')' : '');

        if ($format === 'pdf') {
            return $this->pdf($keys, $headers, $rows, $title, $request);
        }
        if ($format === 'xlsx') {
            return $this->xlsx($keys, $rows, $title);
        }

        if ($body) {
            $totals = $this->totals($rows);
            $body[] = array_map(fn ($k, $i) => $i === 0 ? 'TOTAL ('.count($rows).')' : ($totals[$k] ?? ''), $keys, array_keys($keys));
        }

        return TabularExport::stream($headers, $body, 'demand-slip', $title, $format);
    }

    /**
     * Session rows after the page's filters.
     *
     * @return array{0: ?AcademicSession, 1: list<array<string, mixed>>, 2: list<string>}
     */
    private function filteredRows(Request $request): array
    {
        $f = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'school_class_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'vehicle' => 'nullable|string|max:100',
            'dues' => 'nullable|in:all,due,clear',
            'search' => 'nullable|string|max:100',
        ]);

        $session = ! empty($f['academic_session_id'])
            ? AcademicSession::find($f['academic_session_id'])
            : (AcademicSession::fromRequest($request, true)
                ?? AcademicSession::query()->orderByDesc('start_date')->first());

        $search = mb_strtolower(trim($f['search'] ?? ''));
        $vehicle = trim($f['vehicle'] ?? '');

        $all = StudRecSumReport::rows($session);
        $vehicles = array_values(array_unique(array_filter(array_column($all, 'vehicle'), fn ($v) => $v !== 'None')));
        sort($vehicles, SORT_NATURAL | SORT_FLAG_CASE);

        $rows = array_values(array_filter($all, function (array $r) use ($f, $search, $vehicle) {
            if (! empty($f['school_class_id']) && (int) $r['school_class_id'] !== (int) $f['school_class_id']) {
                return false;
            }
            if (! empty($f['section_id']) && (int) $r['section_id'] !== (int) $f['section_id']) {
                return false;
            }
            if ($vehicle === '__any' && $r['vehicle'] === 'None') {
                return false;
            }
            if ($vehicle !== '' && $vehicle !== '__any' && $r['vehicle'] !== $vehicle) {
                return false;
            }
            // DUES = paid − expected: negative means money still owed.
            if (($f['dues'] ?? 'all') === 'due' && $r['dues'] >= 0) {
                return false;
            }
            if (($f['dues'] ?? 'all') === 'clear' && $r['dues'] < 0) {
                return false;
            }
            if ($search !== '') {
                $hay = mb_strtolower($r['admission_no'].' '.$r['name'].' '.$r['father_name'].' '.$r['mobile']);
                if (! str_contains($hay, $search)) {
                    return false;
                }
            }

            return true;
        }));

        return [$session, $rows, $vehicles];
    }

    /** @return list<string> Column keys to export (all, in Stud_Rec_Sum order, when none picked). */
    private function selectedColumns(Request $request): array
    {
        $picked = array_filter((array) $request->input('columns', []), 'is_string');
        $keys = array_values(array_filter(array_keys(StudRecSumReport::COLUMNS), fn ($k) => in_array($k, $picked, true)));

        return $keys ?: array_keys(StudRecSumReport::COLUMNS);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function totals(array $rows): array
    {
        $totals = [];
        foreach (StudRecSumReport::AMOUNT_COLUMNS as $key) {
            $totals[$key] = round(array_sum(array_column($rows, $key)), 2);
        }

        return $totals;
    }

    /**
     * Landscape A4 table with the school header, filters line and a totals row.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function pdf(array $keys, array $headers, array $rows, string $title, Request $request): StreamedResponse
    {
        $school = $this->dataBuilder->schoolContext();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $isAmount = fn ($k) => in_array($k, StudRecSumReport::AMOUNT_COLUMNS, true);
        $fmt = fn ($v) => number_format((float) $v, 2);
        $totals = $this->totals($rows);

        $thead = '<tr><th>#</th>'.implode('', array_map(fn ($h, $k) => '<th class="'.($isAmount($k) ? 'num' : '').'">'.$e($h).'</th>', $headers, $keys)).'</tr>';
        $tbody = '';
        foreach ($rows as $i => $row) {
            $tbody .= '<tr><td>'.($i + 1).'</td>';
            foreach ($keys as $k) {
                $tbody .= $isAmount($k)
                    ? '<td class="num'.($row[$k] < 0 ? ' neg' : '').'">'.$fmt($row[$k]).'</td>'
                    : '<td>'.$e($row[$k]).'</td>';
            }
            $tbody .= '</tr>';
        }
        $tfoot = '<tr><td></td>'.implode('', array_map(fn ($k, $i) => $isAmount($k)
            ? '<td class="num'.($totals[$k] < 0 ? ' neg' : '').'">'.$fmt($totals[$k]).'</td>'
            : '<td>'.($i === 0 ? 'TOTAL ('.count($rows).')' : '').'</td>', $keys, array_keys($keys))).'</tr>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { margin: 8mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #111; }
            .school { text-align: center; } .school .name { font-size: 13pt; font-weight: bold; }
            h1 { font-size: 10pt; margin: 4pt 0 2pt; text-align: center; }
            .meta { text-align: center; font-size: 7pt; color: #555; margin-bottom: 6pt; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 0.5pt solid #999; padding: 2pt 3pt; vertical-align: top; }
            th { background: #eee; font-weight: bold; }
            .num { text-align: right; white-space: nowrap; } .neg { color: #b91c1c; }
            tfoot td { font-weight: bold; background: #f3f3f3; }
        </style></head><body>
            <div class="school"><div class="name">'.$e($school['school_name']).'</div><div>'.$e($school['school_address']).'</div></div>
            <h1>'.$e($title).'</h1>
            <div class="meta">'.$e($this->filterSummary($request)).' · Printed '.now()->format('d M Y, h:i A').' · DUES = paid − expected (negative = due)</div>
            <table><thead>'.$thead.'</thead><tbody>'.$tbody.'</tbody><tfoot>'.$tfoot.'</tfoot></table>
        </body></html>';

        return response()->streamDownload(function () use ($html) {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        }, 'demand-slip-'.now()->format('Ymd-His').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Colour-coded workbook: header colour by column group (student / payments / expected / dues),
     * amounts still owed in red, paid amounts and cleared dues in green, plus a totals row.
     *
     * @param  list<string>  $keys
     * @param  list<array<string, mixed>>  $rows
     */
    private function xlsx(array $keys, array $rows, string $title): StreamedResponse
    {
        $school = $this->dataBuilder->schoolContext();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Demand Slip');

        $lastCol = Coordinate::stringFromColumnIndex(count($keys) + 1); // + the "#" column
        $groupColours = [
            'detail' => ['head' => 'FF334155', 'font' => 'FFFFFFFF'], // slate
            'paid' => ['head' => 'FF15803D', 'font' => 'FFFFFFFF'],   // green
            'calc' => ['head' => 'FF1D4ED8', 'font' => 'FFFFFFFF'],   // blue
            'dues' => ['head' => 'FFB91C1C', 'font' => 'FFFFFFFF'],   // red
        ];
        $group = fn (string $k) => match (true) {
            ! in_array($k, StudRecSumReport::AMOUNT_COLUMNS, true) => 'detail',
            str_ends_with($k, '_calc') => 'calc',
            str_ends_with($k, '_dues') || $k === 'dues' => 'dues',
            default => 'paid',
        };
        $red = ['font' => ['color' => ['argb' => 'FFB91C1C'], 'bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']]];
        $green = ['font' => ['color' => ['argb' => 'FF15803D']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCFCE7']]];
        $blue = ['font' => ['color' => ['argb' => 'FF1D4ED8']]];

        // Title block
        $sheet->setCellValue('A1', $school['school_name']);
        $sheet->setCellValue('A2', $school['school_address']);
        $sheet->setCellValue('A3', $title);
        $sheet->setCellValue('A4', 'Colour key:  RED = still due   ·   GREEN = paid / no dues   ·   BLUE = expected (CALC)   ·   DUES = paid − expected');
        foreach ([1, 2, 3, 4] as $r) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF0F172A');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF475569');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF1E3A8A');
        $sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF64748B');

        // Header row
        $headerRow = 6;
        $sheet->setCellValue("A{$headerRow}", '#');
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $groupColours['detail']['head']]],
        ]);
        foreach ($keys as $i => $k) {
            $cell = Coordinate::stringFromColumnIndex($i + 2).$headerRow;
            $colours = $groupColours[$group($k)];
            $sheet->setCellValue($cell, StudRecSumReport::COLUMNS[$k]);
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => $colours['font']]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colours['head']]],
            ]);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Body
        $colourAmount = function (string $cell, string $k, float $v) use ($sheet, $group, $red, $green, $blue) {
            $g = $group($k);
            if ($g === 'dues') {
                $sheet->getStyle($cell)->applyFromArray($v < 0 ? $red : $green);
            } elseif ($g === 'paid' && $v > 0) {
                $sheet->getStyle($cell)->applyFromArray(['font' => $green['font']]);
            } elseif ($g === 'calc') {
                $sheet->getStyle($cell)->applyFromArray($blue);
            }
        };

        $r = $headerRow;
        foreach ($rows as $n => $row) {
            $r++;
            $sheet->setCellValue("A{$r}", $n + 1);
            foreach ($keys as $i => $k) {
                $cell = Coordinate::stringFromColumnIndex($i + 2).$r;
                if ($group($k) === 'detail') {
                    $sheet->setCellValueExplicit($cell, (string) $row[$k], DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, (float) $row[$k]);
                    $colourAmount($cell, $k, (float) $row[$k]);
                }
            }
            if ($n % 2 === 1) { // light zebra on the student columns only, so the amount colours stay clear
                foreach ($keys as $i => $k) {
                    if ($group($k) === 'detail') {
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($i + 2).$r)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                    }
                }
            }
        }
        $lastDataRow = $r;

        // Totals row
        if ($rows) {
            $r++;
            $totals = $this->totals($rows);
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF0F172A']]],
            ]);
            foreach ($keys as $i => $k) {
                $cell = Coordinate::stringFromColumnIndex($i + 2).$r;
                if ($group($k) === 'detail') {
                    if ($i === 0) {
                        $sheet->setCellValue($cell, 'TOTAL ('.count($rows).' students)');
                    }
                } else {
                    $sheet->setCellValue($cell, $totals[$k]);
                    $colourAmount($cell, $k, (float) $totals[$k]);
                    $sheet->getStyle($cell)->getFont()->setBold(true);
                }
            }
        }

        // Number format, borders, widths, freeze + filter
        foreach ($keys as $i => $k) {
            $col = Coordinate::stringFromColumnIndex($i + 2);
            if ($group($k) !== 'detail') {
                $sheet->getStyle("{$col}".($headerRow + 1).":{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
                $sheet->getColumnDimension($col)->setWidth(13);
            } else {
                $sheet->getColumnDimension($col)->setWidth(match ($k) {
                    'name', 'father_name' => 26,
                    'address' => 34,
                    'session', 'class' => 10,
                    default => 14,
                });
            }
        }
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$r}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);
        $sheet->freezePane('C'.($headerRow + 1));
        if ($rows) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        }
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'demand-slip-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function filterSummary(Request $request): string
    {
        $parts = [];
        if ($request->filled('class_label')) {
            $parts[] = 'Class: '.$request->string('class_label').($request->filled('section_label') ? ' - '.$request->string('section_label') : '');
        }
        $vehicle = (string) $request->input('vehicle', '');
        if ($vehicle !== '') {
            $parts[] = 'Vehicle: '.($vehicle === '__any' ? 'Any (transport students)' : $vehicle);
        }
        $dues = (string) $request->input('dues', 'all');
        if ($dues !== 'all' && $dues !== '') {
            $parts[] = $dues === 'due' ? 'Only students with dues' : 'Only fully paid';
        }
        if ($request->filled('search')) {
            $parts[] = 'Search: '.$request->string('search');
        }

        return $parts ? implode(' · ', $parts) : 'All students';
    }
}
