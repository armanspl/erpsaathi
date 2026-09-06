<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\ImportExportLog;
use Illuminate\Http\Request;

class ImportExportLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ImportExportLog::with('performedBy:id,name');

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction'));
        }

        return response()->json($query->orderByDesc('id')->get());
    }

    /** Per-row outcome detail for one import run — what got saved/updated/skipped and why, per Excel row. */
    public function rows(ImportExportLog $importExportLog)
    {
        return response()->json(
            $importExportLog->rowLogs()->orderBy('row_number')->get()
        );
    }
}
