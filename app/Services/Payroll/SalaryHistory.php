<?php

namespace App\Services\Payroll;

use App\Models\SalarySlip;
use App\Models\SalarySlipHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Salary history + rollback. Every create/update/delete of a SalarySlip (from any code path —
 * the monthly import, the Create Salary Slip form, Mark Paid, Generate, the bank workbook) is
 * snapshotted by SalarySlip's model events into salary_slip_histories, grouped into a batch:
 *
 *   SalaryHistory::batch('import', 'GAS SALARY.xlsx — AUG -26 (August 2026)', fn () => ...);
 *
 * Writes outside an explicit batch() still get recorded, one "other" batch per request.
 *
 * rollback($batchId) undoes a whole batch: created slips are deleted, updated ones restored to
 * their `before` row, deleted ones re-inserted (same id). It refuses — changing nothing — when
 * any of those slips was changed again by a later batch that is still active, so an import
 * can't silently wipe a payment made afterwards; roll the later change back first.
 */
class SalaryHistory
{
    /** Columns never restored/compared (bookkeeping only). */
    private const VOLATILE = ['updated_at'];

    private static ?array $current = null;

    private static ?array $fallback = null;

    private static ?string $last = null;

    /** Runs $callback with every slip write recorded under one new batch. */
    public static function batch(string $source, string $label, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = ['id' => (string) Str::uuid(), 'source' => $source, 'label' => mb_substr($label, 0, 250)];
        self::$last = self::$current['id'];
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    /** Id of the most recently started batch (for linking a response to its history entry). */
    public static function lastBatchId(): ?string
    {
        return self::$last;
    }

    /** Called from SalarySlip model events. */
    public static function record(string $action, SalarySlip $slip): void
    {
        $before = $action === 'created' ? null : self::raw($slip->getRawOriginal());
        $after = $action === 'deleted' ? null : self::raw($slip->getAttributes());

        if ($action === 'updated' && self::comparable($before) == self::comparable($after)) {
            return; // only updated_at moved — nothing worth undoing
        }

        $batch = self::$current ?? (self::$fallback ??= ['id' => (string) Str::uuid(), 'source' => 'other', 'label' => 'Salary slip change']);
        $row = $after ?? $before;

        SalarySlipHistory::create([
            'batch_id' => $batch['id'],
            'source' => $batch['source'],
            'batch_label' => $batch['label'],
            'action' => $action,
            'salary_slip_id' => $slip->getKey(),
            'employee_type' => (string) ($row['employee_type'] ?? ''),
            'employee_id' => (int) ($row['employee_id'] ?? 0),
            'period' => (string) ($row['period'] ?? ''),
            'before' => $before,
            'after' => $after,
            'performed_by_id' => Auth::guard('erp')->id(),
        ]);
    }

    /**
     * @return array{restored:int, deleted:int, recreated:int}
     *
     * @throws RuntimeException when a later, still-active change touches the same slip
     */
    public static function rollback(string $batchId): array
    {
        return DB::transaction(function () use ($batchId) {
            $entries = SalarySlipHistory::query()
                ->where('batch_id', $batchId)
                ->whereNull('rolled_back_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw new RuntimeException('Nothing to roll back — this change was already undone.');
            }

            $conflicts = [];
            foreach ($entries as $entry) {
                $later = SalarySlipHistory::query()
                    ->where('id', '>', $entry->id)
                    ->where('batch_id', '!=', $batchId)
                    ->whereNull('rolled_back_at')
                    ->where(function ($q) use ($entry) {
                        $q->where('salary_slip_id', $entry->salary_slip_id)
                            ->orWhere(fn ($k) => $k->where('employee_type', $entry->employee_type)
                                ->where('employee_id', $entry->employee_id)
                                ->where('period', $entry->period));
                    })
                    ->orderBy('id')
                    ->first();
                if ($later) {
                    $conflicts[$later->batch_id] = $later->batch_label ?: $later->source;
                }
            }
            if ($conflicts !== []) {
                throw new RuntimeException('Some of these slips were changed afterwards — roll back these first: '.implode('; ', array_unique($conflicts)).'.');
            }

            $stats = ['restored' => 0, 'deleted' => 0, 'recreated' => 0];
            // Query builder writes bypass the model events, so a rollback is not itself recorded.
            foreach ($entries as $entry) {
                if ($entry->action === 'created') {
                    $stats['deleted'] += DB::table('salary_slips')->where('id', $entry->salary_slip_id)->delete();
                } elseif ($entry->action === 'updated') {
                    $before = array_diff_key($entry->before ?? [], array_flip(['id']));
                    DB::table('salary_slips')->where('id', $entry->salary_slip_id)->update($before + ['updated_at' => now()]);
                    $stats['restored']++;
                } elseif ($entry->action === 'deleted' && $entry->before) {
                    DB::table('salary_slips')->insert($entry->before);
                    $stats['recreated']++;
                }
            }

            // Bank debits follow the restored slips (a rolled-back payment gives the money back).
            foreach ($entries->pluck('salary_slip_id')->filter()->unique() as $slipId) {
                SalaryBankSync::sync((int) $slipId);
            }

            SalarySlipHistory::query()
                ->where('batch_id', $batchId)
                ->whereNull('rolled_back_at')
                ->update(['rolled_back_at' => now(), 'rolled_back_by_id' => Auth::guard('erp')->id()]);

            return $stats;
        });
    }

    /** Raw DB row; JSON columns stay as their stored strings so a restore writes them back verbatim. */
    private static function raw(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $attributes[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $attributes;
    }

    private static function comparable(?array $row): array
    {
        $row = array_diff_key($row ?? [], array_flip(self::VOLATILE));
        foreach ($row as $key => $value) {
            // "11300" vs "11300.00" — compare numbers as numbers.
            $row[$key] = is_numeric($value) ? (float) $value : $value;
        }

        return $row;
    }
}
