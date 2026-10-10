<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\ImportExport\SalaryMonthlyImportController;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\Driver;
use App\Models\SalarySlip;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use App\Services\Payroll\CasualLeavePolicy;
use App\Services\Payroll\SalaryHistory;
use App\Support\EmployeeCustomFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalarySlipController extends Controller
{
    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request)
    {
        $query = SalarySlip::with(['employee', 'bankAccount']);

        if ($request->filled('period')) {
            $query->where('period', $request->string('period'));
        }
        if ($request->filled('employee_type')) {
            $query->where('employee_type', $request->string('employee_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $term = '%' . trim($request->string('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('slip_no', 'like', $term)
                    ->orWhereHasMorph('employee', [Teacher::class, Staff::class, Driver::class], fn ($sub) => $sub->where('name', 'like', $term));
            });
        }

        if (! $request->filled('period') && ! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session?->start_date && $session?->end_date) {
                $query->where('period', '>=', $session->start_date->format('Y-m'))
                    ->where('period', '<=', $session->end_date->format('Y-m'));
            }
        }

        return response()->json(
            $query->orderByDesc('period')->orderBy('employee_type')->get()->map(fn (SalarySlip $slip) => $this->present($slip))
        );
    }

    /**
     * People for the Create Salary Slip form, straight from the database (not the cached people
     * lookups) so a just-imported teacher/staff/driver shows up at once. Includes the basic salary
     * to pre-fill and the designation to show next to the name.
     */
    public function employees(Request $request)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['teacher', 'staff', 'driver'])]]);
        $class = $this->modelClass($data['type']);
        $columns = ['id', 'employee_id', 'name', 'status', 'salary', 'custom_field_values'];
        if ($data['type'] === 'staff') {
            $columns[] = 'department';
        }

        $period = $request->filled('period') && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->string('period'))
            ? (string) $request->string('period')
            : now()->format('Y-m');

        return response()->json(
            $class::query()->orderByRaw("status = 'active' desc")->orderBy('name')
                ->get($columns)
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'code' => $e->employee_id,
                    'name' => $e->name,
                    'status' => $e->status,
                    'salary' => $e->salary !== null ? (float) $e->salary : null,
                    'designation' => EmployeeCustomFields::get($e->custom_field_values, 'Designation') ?: ($data['type'] === 'staff' ? (string) $e->department : ''),
                    'cl_balance' => CasualLeavePolicy::balance($data['type'], (int) $e->id, $period),
                ])
        );
    }

    /** CL balance for Create Salary Slip / staff profiles. */
    public function clBalance(Request $request)
    {
        $data = $request->validate([
            'employee_type' => ['required', Rule::in(['teacher', 'staff', 'driver'])],
            'employee_id' => 'required|integer',
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'exclude_slip_id' => 'nullable|integer',
        ]);
        $this->assertEmployeeExists($data['employee_type'], (int) $data['employee_id']);

        return response()->json([
            'settings' => CasualLeavePolicy::settings(),
            'balance' => CasualLeavePolicy::balance(
                $data['employee_type'],
                (int) $data['employee_id'],
                $data['period'],
                isset($data['exclude_slip_id']) ? (int) $data['exclude_slip_id'] : null,
            ),
        ]);
    }

    /** Create Salary Slip form — the same columns as a row of the school's salary sheet. */
    public function store(Request $request)
    {
        $data = $this->validatedManual($request);
        $this->assertEmployeeExists($data['employee_type'], $data['employee_id']);
        $this->assertNoDuplicate($data['employee_type'], $data['employee_id'], $data['period']);

        $label = 'Created slip — '.$this->employeeName($data).' ('.$this->periodLabel($data['period']).')';
        $slip = SalaryHistory::batch('manual', $label, fn () => SalarySlip::create($this->slipPayload($data) + [
            'slip_no' => SalarySlip::nextSlipNo(),
            'employee_type' => $data['employee_type'],
            'employee_id' => $data['employee_id'],
            'period' => $data['period'],
            'generated_by_id' => Auth::guard('erp')->id(),
        ]));

        return response()->json($this->present($slip->fresh('employee')), 201);
    }

    public function update(Request $request, SalarySlip $salarySlip)
    {
        $data = $this->validatedManual($request);
        $this->assertEmployeeExists($data['employee_type'], $data['employee_id']);
        $this->assertNoDuplicate($data['employee_type'], $data['employee_id'], $data['period'], $salarySlip->id);

        $label = 'Edited slip '.($salarySlip->slip_no ?: '#'.$salarySlip->id).' — '.$this->employeeName($data).' ('.$this->periodLabel($data['period']).')';
        SalaryHistory::batch('manual', $label, fn () => $salarySlip->update($this->slipPayload($data) + [
            'employee_type' => $data['employee_type'],
            'employee_id' => $data['employee_id'],
            'period' => $data['period'],
        ]));

        return response()->json($this->present($salarySlip->fresh('employee')));
    }

    public function destroy(SalarySlip $salarySlip)
    {
        $salarySlip->loadMissing('employee');
        $label = 'Deleted slip '.($salarySlip->slip_no ?: '#'.$salarySlip->id).' — '.($salarySlip->employee->name ?? '?').' ('.$this->periodLabel($salarySlip->period).')';
        SalaryHistory::batch('manual', $label, fn () => $salarySlip->delete());

        return response()->json(['success' => true]);
    }

    public function downloadPdf(SalarySlip $salarySlip): StreamedResponse
    {
        $salarySlip->loadMissing('employee');

        // Two identical copies (office + employee) on one A4 with a cut line.
        return $this->renderer->streamPdfDuplicateA4('salary_slip', $this->dataBuilder->salarySlip($salarySlip), "{$salarySlip->slip_no}.pdf");
    }

    /** Generate Pending slips for every configured salary structure for a given period. Idempotent — skips employees who already have a slip for that period. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $created = SalaryHistory::batch('generate', 'Generated slips — '.$this->periodLabel($data['period']), function () use ($data) {
            $structures = SalaryStructure::all();
            $existing = SalarySlip::where('period', $data['period'])->get()
                ->map(fn (SalarySlip $s) => $s->employee_type . ':' . $s->employee_id);

            $created = 0;
            foreach ($structures as $structure) {
                if ($existing->contains($structure->employee_type . ':' . $structure->employee_id)) {
                    continue;
                }

                SalarySlip::create([
                    'slip_no' => SalarySlip::nextSlipNo(),
                    'employee_type' => $structure->employee_type,
                    'employee_id' => $structure->employee_id,
                    'period' => $data['period'],
                    'basic_salary' => $structure->basic_salary,
                    'allowances' => $structure->allowances,
                    'deductions' => $structure->deductions,
                    'net_salary' => (float) $structure->basic_salary + (float) $structure->allowances - (float) $structure->deductions,
                    'generated_by_id' => Auth::guard('erp')->id(),
                ]);
                $created++;
            }

            return $created;
        });

        return response()->json(['success' => true, 'generated' => $created]);
    }

    public function markPaid(Request $request, SalarySlip $salarySlip)
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'paid_on' => 'nullable|date',
        ]);
        $bankId = $this->bankAccountFor($data['payment_mode'], $data['bank_account_id'] ?? null, 'Paid');

        $salarySlip->loadMissing('employee');
        $via = $bankId ? (BankAccount::whereKey($bankId)->value('bank_name') ?: 'Bank') : $data['payment_mode'];
        $label = 'Paid '.($salarySlip->employee->name ?? '?').' — '.$this->periodLabel($salarySlip->period).' ('.$via.')';
        SalaryHistory::batch('payment', $label, fn () => $salarySlip->update([
            'status' => 'Paid',
            'payment_mode' => $data['payment_mode'],
            'bank_account_id' => $bankId,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
        ]));

        return response()->json($this->present($salarySlip->fresh('employee')));
    }

    private function validatedManual(Request $request): array
    {
        $data = $request->validate([
            'employee_type' => ['required', Rule::in(['teacher', 'staff', 'driver'])],
            'employee_id' => 'required|integer',
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'basic_salary' => 'required|numeric|min:0|max:99999999',
            'days_in_month' => 'nullable|integer|min:1|max:31',
            'absent' => 'nullable|numeric|min:0|max:31',
            'present' => 'nullable|numeric|min:0|max:31',
            'cl' => 'nullable|numeric|min:0|max:31',
            'advance' => 'nullable|numeric|min:0|max:99999999',
            'earnings' => 'array',
            'earnings.*.label' => 'required|string|max:100',
            'earnings.*.amount' => 'required|numeric|min:0',
            'deduction_items' => 'array',
            'deduction_items.*.label' => 'required|string|max:100',
            'deduction_items.*.amount' => 'required|numeric|min:0',
            'payment_mode' => ['nullable', Rule::in(['Cash', 'Bank'])],
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'paid_on' => 'nullable|date',
            'status' => ['required', Rule::in(['Draft', 'Pending', 'Paid'])],
            'remarks' => 'nullable|string|max:255',
        ]);
        $data['bank_account_id'] = $this->bankAccountFor($data['payment_mode'] ?? null, $data['bank_account_id'] ?? null, $data['status']);

        // Days in month comes from the calendar unless the sheet used something else.
        [$year, $month] = array_map('intval', explode('-', $data['period']));
        $data['days_in_month'] ??= cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $data['absent'] = (float) ($data['absent'] ?? 0);
        $data['cl'] = (float) ($data['cl'] ?? 0);
        // Payable days = Days − Absent. CL is paid leave and does not reduce salary days.
        $data['present'] = max(0, $data['days_in_month'] - $data['absent']);

        if ($data['absent'] > $data['days_in_month'] + 0.001) {
            throw ValidationException::withMessages(['absent' => "Absent cannot be more than the {$data['days_in_month']} days in this month."]);
        }
        if ($data['cl'] > $data['present'] + 0.001) {
            throw ValidationException::withMessages(['cl' => "CL cannot be more than the {$data['present']} payable days in this month."]);
        }

        $excludeSlipId = $request->route('salarySlip') instanceof SalarySlip
            ? (int) $request->route('salarySlip')->id
            : null;

        CasualLeavePolicy::assertValid(
            $data['employee_type'],
            (int) $data['employee_id'],
            $data['period'],
            $data['cl'],
            (int) $data['days_in_month'],
            $excludeSlipId,
        );

        return $data;
    }

    /** Excel row math (see SalaryMonthlyImportController::computeSlip) + the slip's own fields. */
    private function slipPayload(array $data): array
    {
        $earnings = array_values(array_filter($data['earnings'] ?? [], fn ($r) => strtolower(trim($r['label'])) !== 'basic'));
        $deductions = array_values($data['deduction_items'] ?? []);
        $otherEarnings = (float) collect($earnings)->sum(fn ($r) => (float) $r['amount']);
        $otherDeductions = (float) collect($deductions)->sum(fn ($r) => (float) $r['amount']);

        $status = $data['status'];

        return SalaryMonthlyImportController::computeSlip(
            (float) $data['basic_salary'],
            (int) $data['days_in_month'],
            $data['present'],
            $data['absent'],
            $data['cl'],
            (float) ($data['advance'] ?? 0),
            $otherEarnings,
            $otherDeductions,
        ) + [
            'earnings' => array_merge([['label' => 'Basic', 'amount' => (float) $data['basic_salary']]], $earnings),
            'deduction_items' => $deductions,
            'status' => $status,
            'payment_mode' => $data['payment_mode'] ?? null,
            'bank_account_id' => $data['bank_account_id'],
            'paid_on' => $status === 'Paid' ? ($data['paid_on'] ?? now()->toDateString()) : null,
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    /**
     * The bank account a Bank payment comes out of: the one picked, or the school's only account.
     * Paying by Bank needs an account (that's where the salary is debited); Cash never has one.
     */
    private function bankAccountFor(?string $mode, ?int $bankAccountId, string $status): ?int
    {
        if ($mode !== 'Bank') {
            return null;
        }
        if ($bankAccountId) {
            return $bankAccountId;
        }
        $ids = BankAccount::query()->limit(2)->pluck('id');
        if ($ids->count() === 1) {
            return (int) $ids->first();
        }
        if ($status === 'Paid') {
            throw ValidationException::withMessages(['bank_account_id' => $ids->isEmpty()
                ? 'Add a bank account under Finance & Payroll › Bank Accounts first, or pay by Cash.'
                : 'Choose the bank account the salary is paid from.']);
        }

        return null;
    }

    private function assertEmployeeExists(string $employeeType, int $employeeId): void
    {
        if (! $this->modelClass($employeeType)::whereKey($employeeId)->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Selected staff member was not found.']);
        }
    }

    private function assertNoDuplicate(string $employeeType, int $employeeId, string $period, ?int $ignoreId = null): void
    {
        $exists = SalarySlip::where('employee_type', $employeeType)
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['period' => 'A salary slip already exists for this staff member and month — edit that slip instead.']);
        }
    }

    private function employeeName(array $data): string
    {
        return (string) ($this->modelClass($data['employee_type'])::whereKey($data['employee_id'])->value('name') ?? '?');
    }

    /** @return class-string<Teacher|Staff|Driver> */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'teacher' => Teacher::class,
            'driver' => Driver::class,
            default => Staff::class,
        };
    }

    private function periodLabel(string $period): string
    {
        $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
        [$year, $month] = array_pad(explode('-', $period), 2, '');

        return ($months[$month] ?? $month).' '.$year;
    }

    private function present(SalarySlip $slip): array
    {
        $bank = $slip->bank_account_id ? $slip->bankAccount : null;

        return [
            ...$slip->toArray(),
            'employee_name' => $slip->employee->name ?? '—',
            'employee_code' => $slip->employee->employee_id ?? null,
            'bank_account_label' => $bank ? trim($bank->bank_name.' — '.$bank->account_name) : null,
        ];
    }
}
