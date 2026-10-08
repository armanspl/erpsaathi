<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\ErpUser;
use App\Models\SalarySlipHistory;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\Payroll\SalaryHistory;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Salary History: every import sheet, hand-made slip, edit, payment and delete as one row, with
 * a Rollback that undoes it (see App\Services\Payroll\SalaryHistory).
 */
class SalaryHistoryController extends Controller
{
    /** One row per batch, newest first. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'source' => 'nullable|string|max:30',
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'employee_type' => 'nullable|in:teacher,staff,driver',
            'employee_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $filtered = SalarySlipHistory::query()
            ->when($data['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->when($data['period'] ?? null, fn ($q, $p) => $q->where('period', $p))
            ->when(isset($data['employee_type'], $data['employee_id']), fn ($q) => $q->where('employee_type', $data['employee_type'])->where('employee_id', $data['employee_id']));

        $batches = (clone $filtered)
            ->select('batch_id')
            ->selectRaw('MIN(id) as first_id, MAX(created_at) as created_at, COUNT(*) as entries')
            ->selectRaw("SUM(action = 'created') as created_count, SUM(action = 'updated') as updated_count, SUM(action = 'deleted') as deleted_count")
            ->selectRaw('MIN(period) as period_from, MAX(period) as period_to, MAX(rolled_back_at) as rolled_back_at, SUM(rolled_back_at IS NULL) as active_entries')
            ->groupBy('batch_id')
            ->orderByDesc('first_id')
            ->limit($data['limit'] ?? 200)
            ->get();

        $meta = SalarySlipHistory::query()
            ->whereIn('id', $batches->pluck('first_id'))
            ->get(['id', 'source', 'batch_label', 'performed_by_id', 'rolled_back_by_id'])
            ->keyBy('id');
        $users = ErpUser::query()
            ->whereIn('id', $meta->pluck('performed_by_id')->merge($meta->pluck('rolled_back_by_id'))->filter()->unique())
            ->pluck('name', 'id');

        // Slip numbers / staff / amount per batch for the Salary History table.
        $entries = SalarySlipHistory::query()
            ->whereIn('batch_id', $batches->pluck('batch_id'))
            ->get(['batch_id', 'employee_type', 'employee_id', 'period', 'before', 'after'])
            ->groupBy('batch_id');
        $names = $this->employeeNames($entries->flatten(1));

        return response()->json($batches->map(function ($b) use ($meta, $users, $entries, $names) {
            $m = $meta[$b->first_id] ?? null;
            $rows = $entries[$b->batch_id] ?? collect();
            $slipNos = $rows->map(fn ($h) => ($h->after ?? $h->before ?? [])['slip_no'] ?? null)->filter()->unique()->values();
            $people = $rows->map(fn ($h) => "{$h->employee_type}|{$h->employee_id}")->unique()->values();
            $amount = $rows->sum(fn ($h) => (float) (($h->after ?? $h->before ?? [])['net_salary'] ?? 0));

            return [
                'slip_nos' => $slipNos->take(3)->all(),
                'slip_count' => $slipNos->count(),
                'staff' => $people->count() === 1 ? ($names[$people[0]]['name'] ?? null) : null,
                'staff_code' => $people->count() === 1 ? ($names[$people[0]]['code'] ?? null) : null,
                'staff_count' => $people->count(),
                'amount' => round($amount, 2),
                'batch_id' => $b->batch_id,
                'source' => $m?->source,
                'label' => $m?->batch_label,
                'created_at' => $b->created_at,
                'performed_by' => $m?->performed_by_id ? ($users[$m->performed_by_id] ?? null) : null,
                'entries' => (int) $b->entries,
                'created_count' => (int) $b->created_count,
                'updated_count' => (int) $b->updated_count,
                'deleted_count' => (int) $b->deleted_count,
                'period_from' => $b->period_from,
                'period_to' => $b->period_to,
                'rolled_back' => (int) $b->active_entries === 0,
                'rolled_back_at' => $b->rolled_back_at,
                'rolled_back_by' => $m?->rolled_back_by_id ? ($users[$m->rolled_back_by_id] ?? null) : null,
            ];
        }));
    }

    /** The slips one batch touched, with what changed. */
    public function show(string $batchId)
    {
        $entries = SalarySlipHistory::query()->where('batch_id', $batchId)->orderBy('id')->get();
        abort_if($entries->isEmpty(), 404);

        $names = $this->employeeNames($entries);

        $watch = ['basic_salary', 'days_in_month', 'present', 'absent', 'cl', 'this_month_salary', 'advance', 'net_salary', 'status', 'paid_on', 'payment_mode'];

        return response()->json($entries->map(function (SalarySlipHistory $h) use ($names, $watch) {
            $who = $names["{$h->employee_type}|{$h->employee_id}"] ?? ['name' => '(deleted employee)', 'code' => null];
            $changes = [];
            if ($h->action === 'updated') {
                foreach ($watch as $col) {
                    $old = $h->before[$col] ?? null;
                    $new = $h->after[$col] ?? null;
                    if ((is_numeric($old) && is_numeric($new)) ? (float) $old !== (float) $new : (string) $old !== (string) $new) {
                        $changes[] = ['field' => $col, 'from' => $old, 'to' => $new];
                    }
                }
            }
            $row = $h->after ?? $h->before ?? [];

            return [
                'id' => $h->id,
                'slip_no' => $row['slip_no'] ?? null,
                'action' => $h->action,
                'employee_type' => $h->employee_type,
                'employee_name' => $who['name'],
                'employee_code' => $who['code'],
                'period' => $h->period,
                'net_salary' => $row['net_salary'] ?? null,
                'status' => $row['status'] ?? null,
                'changes' => $changes,
                'rolled_back' => $h->rolled_back_at !== null,
            ];
        }));
    }

    /** @return array<string, array{name:string, code:?string}> "type|id" => name + code */
    private function employeeNames($entries): array
    {
        $names = [];
        foreach (['teacher' => Teacher::class, 'staff' => Staff::class, 'driver' => Driver::class] as $type => $class) {
            $ids = collect($entries)->where('employee_type', $type)->pluck('employee_id')->unique();
            foreach ($class::query()->whereIn('id', $ids)->get(['id', 'name', 'employee_id']) as $e) {
                $names["{$type}|{$e->id}"] = ['name' => $e->name, 'code' => $e->employee_id];
            }
        }

        return $names;
    }

    public function rollback(string $batchId)
    {
        try {
            $stats = SalaryHistory::rollback($batchId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $parts = array_filter([
            $stats['deleted'] ? "{$stats['deleted']} slip(s) removed" : null,
            $stats['restored'] ? "{$stats['restored']} slip(s) restored" : null,
            $stats['recreated'] ? "{$stats['recreated']} slip(s) brought back" : null,
        ]);

        return response()->json(['message' => 'Rolled back — '.($parts ? implode(', ', $parts) : 'nothing left to change').'.'] + $stats);
    }
}
