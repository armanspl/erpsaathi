<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Services\SpreadsheetImportReader;
use App\Services\StudentMasterImportService;
use Illuminate\Http\Request;

/**
 * Primary student importer, replacing StudentImportController — imports the "Student Master
 * Record" UDISE-style workbook, which stacks every academic session a student was ever
 * enrolled in as its own row (same Adm No. repeated once per session).
 *
 * The actual header-mapping/upsert pipeline lives in {@see StudentMasterImportService} — it's
 * shared with GlobalWorkbookImportController, which runs the same logic against any
 * "Student Master*" sheet found inside a combined workbook upload, so the two never drift.
 */
class StudentMasterImportController extends Controller
{
    public function store(Request $request, StudentMasterImportService $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        ['header' => $header, 'rows' => $rows] = SpreadsheetImportReader::read($request->file('file'));

        $result = $importer->import($header, $rows, $request->file('file')->getClientOriginalName());

        return response()->json([
            'log' => $result['log'],
            'transport_stats' => $result['transport_stats'],
            'failed_rows' => $result['failed_rows'],
        ], 201);
    }
}
