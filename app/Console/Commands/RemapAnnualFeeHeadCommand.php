<?php

namespace App\Console\Commands;

use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeePaymentAudit;
use App\Services\AnnualFeeHead;
use App\Support\DashboardCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves imported "ANN" fee lines onto the fee head their academic session's fee structure
 * actually charges once a year (e.g. "Annual Fee"), so they offset that charge. Only the
 * line's fee_head_id / fee_head_name change — the payment, its receipt no., amounts, months
 * and sessions stay as they are. Every changed payment gets a fee_payment_audits "Edit"
 * row holding the before/after items. Sessions without an annual charge are left alone.
 */
class RemapAnnualFeeHeadCommand extends Command
{
    protected $signature = 'fees:remap-annual-head
        {--school= : Tenant slug (multi-school installs)}
        {--database= : Run against this database instead (e.g. a test copy)}
        {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Move imported ANN payment lines onto the session\'s configured annual fee head';

    public function handle(): int
    {
        if ($slug = $this->option('school')) {
            $manager = app(\App\Services\Tenancy\TenantManager::class);
            $school = $manager->findBySlug($slug);
            if (! $school) {
                $this->error("No school with slug {$slug}.");

                return self::FAILURE;
            }
            $manager->initialize($school);
        }
        if ($database = $this->option('database')) {
            $connection = config('database.default');
            config(["database.connections.{$connection}.database" => $database]);
            DB::purge($connection);
        }
        AnnualFeeHead::flush();

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? 'APPLYING' : 'DRY RUN').' on database '.DB::connection()->getDatabaseName());

        $heads = FeeHead::query()->pluck('id', 'name')->all();
        $changes = [];
        $lineCount = 0;
        $amountMoved = [];

        foreach (FeePayment::query()->orderBy('id')->get() as $payment) {
            $items = is_array($payment->items) ? $payment->items : [];
            $newItems = $items;
            $touched = false;

            foreach ($items as $i => $item) {
                if (strtolower(trim((string) ($item['source_head'] ?? ''))) !== 'ann') {
                    continue;
                }
                $target = AnnualFeeHead::nameFor($payment->itemSessionId($item));
                if (($item['fee_head_name'] ?? null) === $target) {
                    continue;
                }
                $from = (string) ($item['fee_head_name'] ?? '');
                $newItems[$i]['fee_head_id'] = $heads[$target] ??= $apply
                    ? FeeHead::query()->firstOrCreate(['name' => $target], ['description' => 'Annual fee head'])->id
                    : 0;
                $newItems[$i]['fee_head_name'] = $target;
                $touched = true;
                $lineCount++;
                $key = "{$from} → {$target} (".($item['session'] ?? $payment->itemSessionId($item)).')';
                $amountMoved[$key] = ($amountMoved[$key] ?? 0) + (float) ($item['amount'] ?? 0);
            }

            if ($touched) {
                $changes[] = [$payment, $items, $newItems];
            }
        }

        foreach ($amountMoved as $key => $amount) {
            $this->line(sprintf('  %s: ₹%s', $key, number_format($amount, 2)));
        }
        $this->line(sprintf('%d line(s) on %d payment(s) %s.', $lineCount, count($changes), $apply ? 'updated' : 'would be updated'));

        if (! $apply || $changes === []) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as [$payment, $before, $after]) {
                // Query-builder update: the line's head is the only thing that changes.
                DB::table('fee_payments')->where('id', $payment->id)->update([
                    'items' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
                FeePaymentAudit::create([
                    'fee_payment_id' => $payment->id,
                    'action' => 'Edit',
                    'before' => ['items' => $before],
                    'after' => ['items' => $after],
                    'reason' => 'Annual fee head remap: imported ANN line moved to the session\'s configured annual fee head',
                ]);
            }
        });
        DashboardCache::forget();

        return self::SUCCESS;
    }
}
