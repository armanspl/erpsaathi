<?php

namespace App\Http\Controllers\Erp\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\ImportExport\ExportController;
use App\Support\TabularExport;
use Illuminate\Http\Request;

/**
 * Reports › Daily Summary — the Global Workbook export's "Daily Summary" sheet on screen:
 * cash / bank / total income per day, for a date range (one day = from and to the same),
 * with each day's receipts. ?format=xlsx|csv|pdf downloads the day totals.
 */
class DailySummaryReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'format' => 'nullable|in:xlsx,csv,pdf',
        ]);
        @set_time_limit(300);

        $result = app(ExportController::class)->dailySummaryData($request, $data['from'] ?? null, $data['to'] ?? null);
        $days = $result['days'];
        $totals = [
            'cash' => round(array_sum(array_column($days, 'cash')), 2),
            'bank' => round(array_sum(array_column($days, 'bank')), 2),
            'income' => round(array_sum(array_column($days, 'income')), 2),
        ];

        if (! empty($data['format'])) {
            $table = array_map(fn (array $d) => [\Carbon\Carbon::parse($d['date'])->format('d-m-Y'), $d['cash'], $d['bank'], $d['income']], $days);
            $table[] = ['TOTAL', $totals['cash'], $totals['bank'], $totals['income']];

            return TabularExport::stream(['DATE', 'CASH', 'BANK', 'INCOME'], $table, 'daily-summary', 'Daily Summary', $data['format']);
        }

        return response()->json([
            'days' => $days,
            'entries' => $result['entries'],
            'totals' => $totals,
        ]);
    }
}
