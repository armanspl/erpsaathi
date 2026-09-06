<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\ImportFailedRow;

class FailedRecordController extends Controller
{
    public function index()
    {
        return response()->json(
            ImportFailedRow::with('log:id,entity,filename,created_at')->orderByDesc('id')->get()
        );
    }
}
