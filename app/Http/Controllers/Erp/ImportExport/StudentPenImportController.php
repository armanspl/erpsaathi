<?php

namespace App\Http\Controllers\Erp\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\ImportExportLog;
use App\Models\Student;
use App\Models\StudentUdiseDetail;
use App\Services\SpreadsheetImportReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Runs after StudentMasterImportController — imports the UDISE portal's "Students Details"
 * export. Matching is by ENRL # (same value as Master Record Adm No. → students.admission_no).
 * Name / Class / Section stay in the header map for reference only (unused for matching today).
 * The only column written is student_udise_details.student_pen; everything else is ignored.
 */
class StudentPenImportController extends Controller
{
    private const HEADER_MAP = [
        // Match key — same value as Master Record "Adm No."
        'enrl #' => 'admission_no',

        // Kept for sheet reading / future fallback if a row ever lacks ENRL #; not used for matching today.
        'class' => 'class',
        'section' => 'section',
        'name' => 'name',

        'student pen' => 'student_pen',
    ];

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        // Row 1 is a title label ("List of All Students - ..."), the real header is row 2.
        ['header' => $header, 'rows' => $lines] = SpreadsheetImportReader::read($request->file('file'), 2);

        $studentsByAdm = Student::query()->pluck('id', 'admission_no')->all();
        $existingUdise = StudentUdiseDetail::query()->pluck('id', 'student_id')->all();

        $totalRows = 0;
        $successCount = 0;
        $rowOutcomes = [];
        $penUpdates = []; // student_id => pen
        $udiseCreates = []; // student_id => pen|null

        $rowNumber = 2;
        foreach ($lines as $line) {
            $rowNumber++;
            if (count(array_filter($line, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $totalRows++;

            $row = [];
            foreach ($header as $index => $column) {
                if (array_key_exists($column, self::HEADER_MAP)) {
                    $row[self::HEADER_MAP[$column]] = trim((string) ($line[$index] ?? ''));
                }
            }

            $enrlNo = $row['admission_no'] ?? '';
            $name = $row['name'] ?? '';
            $identifier = $enrlNo !== '' ? $enrlNo : trim("{$name} ({$row['class']}-{$row['section']})");

            if ($enrlNo === '') {
                $rowOutcomes[] = [
                    'row_number' => $rowNumber,
                    'status' => 'Failed',
                    'identifier' => $identifier,
                    'error_message' => 'ENRL # is required to match a student.',
                    'row_data' => $row,
                ];

                continue;
            }

            $studentId = $studentsByAdm[$enrlNo] ?? null;
            if (! $studentId) {
                $rowOutcomes[] = [
                    'row_number' => $rowNumber,
                    'status' => 'Failed',
                    'identifier' => $identifier,
                    'error_message' => 'No student found for this admission no.',
                    'row_data' => $row,
                ];

                continue;
            }

            $penProvided = ($row['student_pen'] ?? '') !== '';
            if ($penProvided) {
                if (isset($existingUdise[$studentId])) {
                    $penUpdates[$studentId] = $row['student_pen'];
                } else {
                    $udiseCreates[$studentId] = $row['student_pen'];
                    $existingUdise[$studentId] = true;
                }
            } elseif (! isset($existingUdise[$studentId])) {
                $udiseCreates[$studentId] = null;
                $existingUdise[$studentId] = true;
            }

            $successCount++;
            $rowOutcomes[] = [
                'row_number' => $rowNumber,
                'status' => 'Success',
                'identifier' => $identifier,
                'error_message' => null,
                'row_data' => null,
                'summary' => [
                    'student_id' => $studentId,
                    'pen_action' => $penProvided ? 'updated' : 'not_provided',
                ],
            ];
        }

        $now = now()->toDateTimeString();

        foreach (array_chunk($udiseCreates, 200, true) as $chunk) {
            $insert = [];
            foreach ($chunk as $studentId => $pen) {
                $insert[] = [
                    'student_id' => $studentId,
                    'student_pen' => $pen,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('student_udise_details')->insert($insert);
        }

        $upsertRows = [];
        foreach ($penUpdates as $studentId => $pen) {
            $upsertRows[] = [
                'student_id' => $studentId,
                'student_pen' => $pen,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($upsertRows, 200) as $chunk) {
            StudentUdiseDetail::upsert($chunk, ['student_id'], ['student_pen', 'updated_at']);
        }

        $log = ImportExportLog::create([
            'direction' => 'Import',
            'entity' => 'student-pen',
            'filename' => $request->file('file')->getClientOriginalName(),
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'failed_count' => $totalRows - $successCount,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);

        $rowLogRows = [];
        $failedRowRows = [];
        foreach ($rowOutcomes as $outcome) {
            $rowLogRows[] = [
                'import_export_log_id' => $log->id,
                'row_number' => $outcome['row_number'],
                'status' => $outcome['status'],
                'identifier' => $outcome['identifier'],
                'summary' => isset($outcome['summary'])
                    ? json_encode($outcome['summary'], JSON_THROW_ON_ERROR)
                    : null,
                'error_message' => $outcome['error_message'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($outcome['status'] === 'Failed') {
                $failedRowRows[] = [
                    'import_export_log_id' => $log->id,
                    'row_number' => $outcome['row_number'],
                    'row_data' => json_encode($outcome['row_data'] ?? [], JSON_THROW_ON_ERROR),
                    'error_message' => mb_substr((string) $outcome['error_message'], 0, 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rowLogRows, 300) as $chunk) {
            DB::table('import_row_logs')->insert($chunk);
        }
        foreach (array_chunk($failedRowRows, 300) as $chunk) {
            DB::table('import_failed_rows')->insert($chunk);
        }

        return response()->json([
            'log' => $log,
            'failed_rows' => $log->failedRows()->limit(200)->get(),
        ], 201);
    }
}
