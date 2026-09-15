<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Services\ImportTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    public function download(string $type, ImportTemplateService $templates): StreamedResponse
    {
        return $templates->download($type);
    }
}
