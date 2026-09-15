<?php

/**
 * Smoke-check major module endpoints that share fee/dashboard services.
 * Boots the app (single-school for global-school; tenant for erp_allschool via BENCH_SCHOOL).
 */

use App\Models\AcademicSession;
use App\Services\DashboardCalculator;
use App\Services\FeeCalculator;
use App\Services\FeeReportCalculator;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$isMulti = class_exists(\App\Services\Tenancy\TenantManager::class)
    && class_exists(\App\Models\Master\School::class);

if ($isMulti) {
    $slug = getenv('BENCH_SCHOOL') ?: (env('FIRST_SCHOOL_SLUG') ?: 'demo');
    $school = \App\Models\Master\School::query()->where('slug', $slug)->first()
        ?? \App\Models\Master\School::query()->where('status', 'active')->orderBy('id')->first();
    if (! $school) {
        fwrite(STDERR, "No school\n");
        exit(1);
    }
    app(\App\Services\Tenancy\TenantManager::class)->initialize($school);
    echo "Tenant: {$school->slug}\n";
} else {
    echo "Single-school mode\n";
}

DashboardCache::forget();

$session = AcademicSession::query()->where('is_current', true)->first()
    ?? AcademicSession::query()->orderByDesc('start_date')->first();

$active = (int) DB::table('students')->where('status', 'Active')->count();
echo "Active students: {$active}\n";
echo "Session: ".($session?->name ?? 'none')."\n";

$checks = [];

try {
    $dash = DashboardCalculator::summary($session, false);
    $checks['dashboard.summary'] = [
        'ok' => isset($dash['people_stats'], $dash['money_stats'], $dash['fee_collection_status']),
        'students' => $dash['people_stats']['students'] ?? null,
        'fee_pending' => $dash['money_stats']['fee_pending'] ?? null,
        'fee_classes' => count($dash['fee_collection_status'] ?? []),
    ];
} catch (Throwable $e) {
    $checks['dashboard.summary'] = ['ok' => false, 'error' => $e->getMessage()];
}

try {
    $notes = DashboardCalculator::notifications($session, false);
    $checks['dashboard.notifications'] = [
        'ok' => isset($notes['total'], $notes['items']),
        'total' => $notes['total'] ?? null,
        'items' => count($notes['items'] ?? []),
    ];
} catch (Throwable $e) {
    $checks['dashboard.notifications'] = ['ok' => false, 'error' => $e->getMessage()];
}

try {
    $fee = FeeReportCalculator::summary($session, false);
    $checks['fee_reports.summary'] = [
        'ok' => isset($fee['total_paid'], $fee['total_due'], $fee['defaulters_count']),
        'total_paid' => $fee['total_paid'] ?? null,
        'total_due' => $fee['total_due'] ?? null,
        'defaulters' => $fee['defaulters_count'] ?? null,
    ];
} catch (Throwable $e) {
    $checks['fee_reports.summary'] = ['ok' => false, 'error' => $e->getMessage()];
}

// Spot-check Fee Due style warm path on a small cohort
try {
    $students = \App\Models\Student::query()->where('status', 'Active')->with('schoolClass:id,name')->limit(5)->get();
    FeeCalculator::flushRuntimeCache();
    FeeCalculator::warmForStudents($students, $session);
    $balance = app(\App\Services\FeeBalanceService::class);
    $ok = 0;
    foreach ($students as $student) {
        $calc = FeeCalculator::forStudent($student, $session);
        $paid = $balance->paidByHead($student, $session, $calc);
        $remaining = $balance->remainingForMonths(
            $student,
            $session,
            $balance->filterMonthsFromFeeStart($student, method_exists($session, 'months') ? collect($session->months())->pluck('key')->filter()->values()->all() : []),
            $calc,
            $paid
        );
        if (isset($remaining['due'], $remaining['charge'])) {
            $ok++;
        }
    }
    $checks['fee_due.warm_path'] = ['ok' => $ok === $students->count(), 'checked' => $ok];
} catch (Throwable $e) {
    $checks['fee_due.warm_path'] = ['ok' => false, 'error' => $e->getMessage()];
}

// Module table presence smoke (sidebar modules that own data)
$tables = [
    'students' => 'People/Students',
    'teachers' => 'People/Teachers',
    'staff' => 'People/Staff',
    'fee_payments' => 'Fee Management',
    'attendances' => 'Attendance',
    'exams' => 'Exams',
    'incomes' => 'Finance',
    'expenses' => 'Finance',
    'events' => 'Communication',
];
foreach ($tables as $table => $label) {
    try {
        $count = DB::table($table)->count();
        $checks["table.$table"] = ['ok' => true, 'module' => $label, 'rows' => $count];
    } catch (Throwable $e) {
        $checks["table.$table"] = ['ok' => false, 'module' => $label, 'error' => $e->getMessage()];
    }
}

$failed = 0;
foreach ($checks as $name => $row) {
    $status = ($row['ok'] ?? false) ? 'OK' : 'FAIL';
    if ($status === 'FAIL') {
        $failed++;
    }
    echo str_pad($status, 5)." {$name} ".json_encode($row)."\n";
}

echo $failed === 0 ? "\nALL CHECKS PASSED\n" : "\n{$failed} CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
