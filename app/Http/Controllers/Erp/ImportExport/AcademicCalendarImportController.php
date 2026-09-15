<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEntry;
use App\Models\AcademicSession;
use App\Models\ImportExportLog;
use App\Services\AcademicCalendarWorkbookService;
use App\Support\DashboardCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicCalendarImportController extends Controller
{
    public function store(Request $request, AcademicCalendarWorkbookService $workbook)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'academic_session_id' => 'nullable|integer|exists:academic_sessions,id',
            'replace' => 'nullable|boolean',
        ]);

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $parsed = $workbook->parsePath($request->file('file')->getRealPath());
        $entries = $parsed['entries'];

        if ($entries === []) {
            return response()->json([
                'message' => 'No academic calendar rows found. Expected SCHOOL HOLIDAYS / EXAMINATIONS / PROGRAMME columns.',
            ], 422);
        }

        $sessionId = $data['academic_session_id']
            ?? AcademicSession::query()->where('is_current', true)->value('id');

        $replace = (bool) ($data['replace'] ?? true);
        $success = 0;
        $failed = 0;
        $rowOutcomes = [];

        DB::transaction(function () use ($entries, $sessionId, $replace, &$success, &$failed, &$rowOutcomes) {
            if ($replace) {
                $q = AcademicCalendarEntry::query();
                if ($sessionId) {
                    $q->where(function ($inner) use ($sessionId) {
                        $inner->where('academic_session_id', $sessionId)->orWhereNull('academic_session_id');
                    });
                }
                $q->delete();
            }

            foreach ($entries as $index => $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    $failed++;
                    $rowOutcomes[] = [
                        'row_number' => $index + 1,
                        'status' => 'Failed',
                        'identifier' => '',
                        'error_message' => 'Title is required.',
                        'row_data' => $row,
                    ];
                    continue;
                }

                AcademicCalendarEntry::query()->create([
                    'academic_session_id' => $sessionId,
                    'category' => $row['category'],
                    'title' => $title,
                    'month_label' => $row['month_label'] ?? null,
                    'date_label' => $row['date_label'] ?? null,
                    'start_date' => $row['start_date'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                ]);
                $success++;
                $rowOutcomes[] = [
                    'row_number' => $index + 1,
                    'status' => 'Success',
                    'identifier' => $title,
                    'error_message' => null,
                    'row_data' => $row,
                ];
            }
        });

        $log = ImportExportLog::query()->create([
            'direction' => 'Import',
            'entity' => 'academic-calendar',
            'filename' => $request->file('file')->getClientOriginalName(),
            'total_rows' => count($entries),
            'success_count' => $success,
            'failed_count' => $failed,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        DashboardCache::forget();

        return response()->json([
            'message' => "Imported {$success} academic calendar item(s)"
                .($failed ? ", {$failed} failed" : '')
                .($replace ? ' (replaced previous entries).' : '.'),
            'log' => $log,
            'session_label' => $parsed['title'],
            'breakdown' => [
                'holiday' => collect($entries)->where('category', 'holiday')->count(),
                'examination' => collect($entries)->where('category', 'examination')->count(),
                'programme' => collect($entries)->where('category', 'programme')->count(),
                'deadline' => collect($entries)->where('category', 'deadline')->count(),
            ],
            'rows' => $rowOutcomes,
        ]);
    }
}
