<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEntry;
use App\Models\AcademicSession;
use App\Models\ImportExportLog;
use App\Services\AcademicCalendarWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicCalendarExportController extends Controller
{
    public function download(Request $request, AcademicCalendarWorkbookService $workbook): StreamedResponse
    {
        $data = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
        ]);

        $sessionId = $data['academic_session_id']
            ?? AcademicSession::query()->where('is_current', true)->value('id');

        $query = AcademicCalendarEntry::query()->orderBy('category')->orderBy('sort_order')->orderBy('start_date');
        if ($sessionId) {
            $query->where(function ($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)->orWhereNull('academic_session_id');
            });
        }

        $entries = $query->get();
        $session = $sessionId ? AcademicSession::query()->find($sessionId) : null;
        $title = $session?->name
            ? 'ACADEMIC CALENDER '.$session->name.' AT A GLANCE'
            : null;

        $spreadsheet = $workbook->buildSpreadsheet($entries, $title);
        $filename = 'academic-calendar-export-'.now()->format('Ymd-His').'.xlsx';

        ImportExportLog::query()->create([
            'direction' => 'Export',
            'entity' => 'academic-calendar',
            'filename' => $filename,
            'total_rows' => $entries->count(),
            'success_count' => $entries->count(),
            'failed_count' => 0,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->streamDownload(function () use ($spreadsheet) {
            \App\Support\ExcelBorders::applyThinGrid($spreadsheet);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
