<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Master\School;
use App\Services\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;

foreach (School::query()->orderBy('id')->get() as $school) {
    try {
        app(TenantManager::class)->initialize($school);
        $total = (int) DB::table('students')->count();
        $active = (int) DB::table('students')->where('status', 'Active')->count();
        $payments = (int) DB::table('fee_payments')->count();
        echo "{$school->slug}\t{$school->db_name}\tstudents={$total}\tactive={$active}\tpayments={$payments}\n";
    } catch (Throwable $e) {
        echo "{$school->slug}\tERROR\t{$e->getMessage()}\n";
    }
}
