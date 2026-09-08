<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\DemoSchoolService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the Try Demo school, block Import/Export API calls for keys Super Admin marked hidden.
 */
class BlockDemoImportExport
{
    public function __construct(private DemoSchoolService $demo) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->demo->isDemoRequest()) {
            return $next($request);
        }

        $key = $this->resolveKey($request);
        if ($key && $this->demo->isImportExportKeyHidden($key)) {
            return response()->json([
                'message' => 'This Import & Export option is disabled in the demo.',
            ], 403);
        }

        return $next($request);
    }

    private function resolveKey(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        // erp/api/import-export/...
        if (preg_match('#import-export/import/global-workbook$#', $path)) {
            return 'global-workbook-import';
        }
        if (preg_match('#import-export/import/student-pen$#', $path)) {
            return 'student-pen-import';
        }
        if (preg_match('#import-export/import/attendance$#', $path)) {
            return 'attendance-import';
        }
        if (preg_match('#import-export/import/class-term-marks$#', $path)) {
            return 'exam-marks-import';
        }
        if (preg_match('#import-export/export/([^/]+)$#', $path, $m)) {
            return match ($m[1]) {
                'global' => 'global-workbook-export',
                'student' => 'student-export',
                'student-udise' => 'student-udise-export',
                'attendance' => 'attendance-export',
                default => null,
            };
        }

        return null;
    }
}
