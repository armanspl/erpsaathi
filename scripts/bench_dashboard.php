<?php

/**
 * Dashboard before/after performance + correctness benchmark.
 *
 * Usage:
 *   php scripts/bench_dashboard.php after
 *   php scripts/bench_dashboard.php before   # expects legacy service files already swapped in
 *   php scripts/bench_dashboard.php compare
 */

use App\Models\AcademicSession;
use App\Models\Master\School;
use App\Services\DashboardCalculator;
use App\Services\Tenancy\TenantManager;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'after';
$outDir = storage_path('bench');
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$slug = getenv('BENCH_SCHOOL') ?: (env('FIRST_SCHOOL_SLUG') ?: 'demo');
$school = School::query()->where('slug', $slug)->first();
if (! $school) {
    $school = School::query()->where('status', 'active')->orderBy('id')->first();
}
if (! $school) {
    fwrite(STDERR, "No school found in master DB.\n");
    exit(1);
}

app(TenantManager::class)->initialize($school);

$activeStudents = (int) DB::table('students')->where('status', 'Active')->count();
$session = AcademicSession::query()->where('is_current', true)->first()
    ?? AcademicSession::query()->orderByDesc('start_date')->first();

echo "School: {$school->slug} ({$school->db_name})\n";
echo "Active students: {$activeStudents}\n";
echo "Session: ".($session?->name ?? 'none')."\n";
echo "Mode: {$mode}\n\n";

if ($mode === 'compare') {
    $before = json_decode(file_get_contents($outDir.'/before.json'), true);
    $after = json_decode(file_get_contents($outDir.'/after.json'), true);
    if (! $before || ! $after) {
        fwrite(STDERR, "Missing before.json or after.json — run before/after first.\n");
        exit(1);
    }
    compareResults($before, $after);
    exit(0);
}

// Bust caches so cold run is fair.
if (class_exists(DashboardCache::class)) {
    DashboardCache::forget();
}
Cache::flush();

$label = $mode === 'before' ? 'BEFORE' : 'AFTER';

// ---- Cold run ----
DB::flushQueryLog();
DB::enableQueryLog();
$t0 = hrtime(true);
$payload = DashboardCalculator::summary($session, false);
$msCold = (hrtime(true) - $t0) / 1e6;
$queriesCold = DB::getQueryLog();
DB::disableQueryLog();

// ---- Warm / cached run ----
DB::flushQueryLog();
DB::enableQueryLog();
$t1 = hrtime(true);
$payloadWarm = DashboardCalculator::summary($session, false);
$msWarm = (hrtime(true) - $t1) / 1e6;
$queriesWarm = DB::getQueryLog();
DB::disableQueryLog();

// Notifications (may share fee cache after dashboard)
if (class_exists(DashboardCache::class)) {
    // keep fee cache; only measure notifications path as second request would see it
}
DB::flushQueryLog();
DB::enableQueryLog();
$t2 = hrtime(true);
$notes = DashboardCalculator::notifications($session, false);
$msNotes = (hrtime(true) - $t2) / 1e6;
$queriesNotes = DB::getQueryLog();
DB::disableQueryLog();

$fingerprint = fingerprint($payload);
$dupes = findDuplicateQueries($queriesCold);

$result = [
    'label' => $label,
    'school' => $school->slug,
    'db' => $school->db_name,
    'active_students' => $activeStudents,
    'session' => $session?->name,
    'cold_ms' => round($msCold, 2),
    'warm_ms' => round($msWarm, 2),
    'notifications_ms' => round($msNotes, 2),
    'cold_query_count' => count($queriesCold),
    'warm_query_count' => count($queriesWarm),
    'notifications_query_count' => count($queriesNotes),
    'duplicate_query_groups' => count($dupes),
    'top_duplicate_queries' => array_slice($dupes, 0, 15),
    'fingerprint' => $fingerprint,
    'sample' => [
        'people_stats' => $payload['people_stats'] ?? null,
        'money_stats' => $payload['money_stats'] ?? null,
        'today_summary' => $payload['today_summary'] ?? null,
        'fee_collection_status_count' => count($payload['fee_collection_status'] ?? []),
        'fee_collection_status_top3' => array_slice($payload['fee_collection_status'] ?? [], 0, 3),
        'class_strength_count' => count($payload['class_strength'] ?? []),
        'pending_tasks' => $payload['pending_tasks'] ?? null,
        'notifications_total' => $notes['total'] ?? null,
    ],
];

$file = $outDir.'/'.($mode === 'before' ? 'before' : 'after').'.json';
file_put_contents($file, json_encode($result, JSON_PRETTY_PRINT));

echo "=== {$label} ===\n";
echo "Cold: {$result['cold_ms']} ms, {$result['cold_query_count']} queries\n";
echo "Warm: {$result['warm_ms']} ms, {$result['warm_query_count']} queries\n";
echo "Notifications: {$result['notifications_ms']} ms, {$result['notifications_query_count']} queries\n";
echo "Duplicate SQL groups (cold): {$result['duplicate_query_groups']}\n";
echo "Fingerprint: {$fingerprint}\n";
echo "Wrote {$file}\n";

function fingerprint(array $payload): string
{
    $slice = [
        'people_stats' => $payload['people_stats'] ?? null,
        'money_stats' => $payload['money_stats'] ?? null,
        'today_summary' => $payload['today_summary'] ?? null,
        'class_strength' => $payload['class_strength'] ?? null,
        'category_by_class' => $payload['category_by_class'] ?? null,
        'fee_collection_status' => $payload['fee_collection_status'] ?? null,
        'recent_admissions' => $payload['recent_admissions'] ?? null,
        'pending_tasks' => $payload['pending_tasks'] ?? null,
        'upcoming_events' => $payload['upcoming_events'] ?? null,
        // recent_activities is time-ordered text — include for equality check
        'recent_activities' => $payload['recent_activities'] ?? null,
    ];

    return hash('sha256', json_encode($slice));
}

function findDuplicateQueries(array $log): array
{
    $groups = [];
    foreach ($log as $q) {
        $sql = $q['query'] ?? '';
        // Normalize bindings placeholder already in SQL from Laravel log
        $key = preg_replace('/\s+/', ' ', $sql);
        $groups[$key] = ($groups[$key] ?? 0) + 1;
    }
    $dupes = [];
    foreach ($groups as $sql => $count) {
        if ($count >= 5) {
            $dupes[] = ['count' => $count, 'sql' => mb_substr($sql, 0, 180)];
        }
    }
    usort($dupes, fn ($a, $b) => $b['count'] <=> $a['count']);

    return $dupes;
}

function compareResults(array $before, array $after): void
{
    echo "=== COMPARE ===\n";
    echo "Active students: {$after['active_students']}\n\n";

    $rows = [
        ['Metric', 'Before', 'After', 'Delta'],
        ['Cold ms', $before['cold_ms'], $after['cold_ms'], round($after['cold_ms'] - $before['cold_ms'], 2)],
        ['Warm ms', $before['warm_ms'], $after['warm_ms'], round($after['warm_ms'] - $before['warm_ms'], 2)],
        ['Notifications ms', $before['notifications_ms'], $after['notifications_ms'], round($after['notifications_ms'] - $before['notifications_ms'], 2)],
        ['Cold queries', $before['cold_query_count'], $after['cold_query_count'], $after['cold_query_count'] - $before['cold_query_count']],
        ['Warm queries', $before['warm_query_count'], $after['warm_query_count'], $after['warm_query_count'] - $before['warm_query_count']],
        ['Notif queries', $before['notifications_query_count'], $after['notifications_query_count'], $after['notifications_query_count'] - $before['notifications_query_count']],
        ['Dup SQL groups (>=5)', $before['duplicate_query_groups'], $after['duplicate_query_groups'], $after['duplicate_query_groups'] - $before['duplicate_query_groups']],
    ];
    foreach ($rows as $r) {
        printf("%-22s %12s %12s %12s\n", $r[0], $r[1], $r[2], $r[3]);
    }

    echo "\nValues fingerprint match: ".($before['fingerprint'] === $after['fingerprint'] ? 'YES' : 'NO')."\n";
    if ($before['fingerprint'] !== $after['fingerprint']) {
        echo "Before sample:\n".json_encode($before['sample'], JSON_PRETTY_PRINT)."\n";
        echo "After sample:\n".json_encode($after['sample'], JSON_PRETTY_PRINT)."\n";
        // Diff money/people specifically
        echo "\npeople_stats equal: ".json_encode($before['sample']['people_stats'] === $after['sample']['people_stats'])."\n";
        echo "money_stats equal: ".json_encode($before['sample']['money_stats'] === $after['sample']['money_stats'])."\n";
        echo "today_summary equal: ".json_encode($before['sample']['today_summary'] === $after['sample']['today_summary'])."\n";
        echo "pending_tasks equal: ".json_encode($before['sample']['pending_tasks'] === $after['sample']['pending_tasks'])."\n";
        echo "fee_status top3 before: ".json_encode($before['sample']['fee_collection_status_top3'])."\n";
        echo "fee_status top3 after:  ".json_encode($after['sample']['fee_collection_status_top3'])."\n";
    }

    $speedup = $before['cold_ms'] > 0 ? round($before['cold_ms'] / max(0.01, $after['cold_ms']), 2) : 0;
    $qReduce = $before['cold_query_count'] > 0
        ? round((1 - ($after['cold_query_count'] / $before['cold_query_count'])) * 100, 1)
        : 0;
    echo "\nCold speedup: {$speedup}x\n";
    echo "Cold query reduction: {$qReduce}%\n";
}
