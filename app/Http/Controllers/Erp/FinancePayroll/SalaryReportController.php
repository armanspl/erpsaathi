<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use Illuminate\Http\Request;

class SalaryReportController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->string('period')->toString() ?: now()->format('Y-m');

        $slips = SalarySlip::with('employee')->where('period', $period)->get();

        $byType = $slips->groupBy('employee_type')->map(fn ($rows) => [
            'count' => $rows->count(),
            'total_net' => round((float) $rows->sum('net_salary'), 2),
        ]);

        return response()->json([
            'period' => $period,
            'total_employees' => $slips->count(),
            'total_basic' => round((float) $slips->sum('basic_salary'), 2),
            'total_allowances' => round((float) $slips->sum('allowances'), 2),
            'total_deductions' => round((float) $slips->sum('deductions'), 2),
            'total_net' => round((float) $slips->sum('net_salary'), 2),
            'paid_count' => $slips->where('status', 'Paid')->count(),
            'pending_count' => $slips->where('status', 'Pending')->count(),
            'by_type' => $byType,
            'rows' => $slips->map(fn (SalarySlip $s) => [
                'employee_type' => $s->employee_type,
                'employee_id' => $s->employee_id,
                'name' => $s->employee->name ?? '—',
                'net_salary' => (float) $s->net_salary,
                'status' => $s->status,
            ])->values(),
        ]);
    }
}
