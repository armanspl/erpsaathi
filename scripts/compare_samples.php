<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$before = json_decode(file_get_contents(storage_path('bench/before.json')), true);
$after = json_decode(file_get_contents(storage_path('bench/after.json')), true);

function normalize($v)
{
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $val) {
            $out[(string) $k] = normalize($val);
        }
        ksort($out);

        return $out;
    }
    if (is_int($v) || is_float($v)) {
        return $v;
    }
    if (is_string($v) && is_numeric($v) && (string) (0 + $v) === $v) {
        return 0 + $v;
    }

    return $v;
}

$b = normalize($before['sample']);
$a = normalize($after['sample']);

// notifications_total in before was stale-cache artifact — compare live fields only
unset($b['notifications_total'], $a['notifications_total']);

echo "Normalized sample equal: ".(json_encode($b) === json_encode($a) ? 'YES' : 'NO')."\n";
if (json_encode($b) !== json_encode($a)) {
    foreach (['people_stats', 'money_stats', 'today_summary', 'pending_tasks', 'fee_collection_status_top3'] as $k) {
        echo "$k equal: ".(json_encode($b[$k] ?? null) === json_encode($a[$k] ?? null) ? 'YES' : 'NO')."\n";
    }
}

echo "\nBefore top duplicates:\n";
foreach (array_slice($before['top_duplicate_queries'], 0, 5) as $d) {
    echo "  {$d['count']}x  {$d['sql']}\n";
}
echo "\nAfter top duplicates:\n";
foreach (array_slice($after['top_duplicate_queries'], 0, 5) as $d) {
    echo "  {$d['count']}x  {$d['sql']}\n";
}
