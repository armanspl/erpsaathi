<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Driver;
use App\Models\SalarySlip;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
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
        $query = SalarySlip::with('employee');

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
            $query->orderByDesc('period')->orderBy('employee_type')->get()->map(fn (SalarySlip $slip) => [
                ...$slip->toArray(),
                'employee_name' => $slip->employee->name ?? '—',
                'employee_code' => $slip->employee->employee_id ?? null,
            ])
        );
    }

    /** Direct ad-hoc slip creation from the Create Salary Slip form (itemized earnings/deductions). */
    public function store(Request $request)
    {
        $data = $this->validatedManual($request);
        $this->assertEmployeeExists($data['employee_type'], $data['employee_id']);
        $this->assertNoDuplicate($data['employee_type'], $data['employee_id'], $data['period']);

        [$basic, $allowances] = $this->splitEarnings($data['earnings'] ?? []);
        $deductionsTotal = collect($data['deduction_items'] ?? [])->sum(fn ($row) => (float) $row['amount']);

        $slip = SalarySlip::create([
            'slip_no' => $this->nextSlipNo(),
            'employee_type' => $data['employee_type'],
            'employee_id' => $data['employee_id'],
            'period' => $data['period'],
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'earnings' => $data['earnings'] ?? [],
            'deductions' => $deductionsTotal,
            'deduction_items' => $data['deduction_items'] ?? [],
            'net_salary' => $basic + $allowances - $deductionsTotal,
            'status' => $data['status'],
            'payment_mode' => $data['payment_mode'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'generated_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($this->present($slip->fresh('employee')), 201);
    }

    public function update(Request $request, SalarySlip $salarySlip)
    {
        $data = $this->validatedManual($request);
        $this->assertEmployeeExists($data['employee_type'], $data['employee_id']);
        $this->assertNoDuplicate($data['employee_type'], $data['employee_id'], $data['period'], $salarySlip->id);

        [$basic, $allowances] = $this->splitEarnings($data['earnings'] ?? []);
        $deductionsTotal = collect($data['deduction_items'] ?? [])->sum(fn ($row) => (float) $row['amount']);

        $salarySlip->update([
            'employee_type' => $data['employee_type'],
            'employee_id' => $data['employee_id'],
            'period' => $data['period'],
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'earnings' => $data['earnings'] ?? [],
            'deductions' => $deductionsTotal,
            'deduction_items' => $data['deduction_items'] ?? [],
            'net_salary' => $basic + $allowances - $deductionsTotal,
            'status' => $data['status'],
            'payment_mode' => $data['payment_mode'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return response()->json($this->present($salarySlip->fresh('employee')));
    }

    public function destroy(SalarySlip $salarySlip)
    {
        $salarySlip->delete();

        return response()->json(['success' => true]);
    }

    public function downloadPdf(SalarySlip $salarySlip): StreamedResponse
    {
        $salarySlip->loadMissing('employee');

        return $this->renderer->streamPdf('salary_slip', $this->dataBuilder->salarySlip($salarySlip), "{$salarySlip->slip_no}.pdf");
    }

    private function periodLabel(string $period): string
    {
        $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
        [$year, $month] = array_pad(explode('-', $period), 2, '');

        return ($months[$month] ?? $month).' '.$year;
    }

    /** Generate Pending slips for every configured salary structure for a given period. Idempotent — skips employees who already have a slip for that period. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $structures = SalaryStructure::all();
        $existing = SalarySlip::where('period', $data['period'])->get()
            ->map(fn (SalarySlip $s) => $s->employee_type . ':' . $s->employee_id);

        $created = 0;
        foreach ($structures as $structure) {
            if ($existing->contains($structure->employee_type . ':' . $structure->employee_id)) {
                continue;
            }

            SalarySlip::create([
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

        return response()->json(['success' => true, 'generated' => $created]);
    }

    public function markPaid(Request $request, SalarySlip $salarySlip)
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'paid_on' => 'nullable|date',
        ]);

        $salarySlip->update([
            'status' => 'Paid',
            'payment_mode' => $data['payment_mode'],
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
        ]);

        return response()->json($salarySlip);
    }

    private function validatedManual(Request $request): array
    {
        return $request->validate([
            'employee_type' => ['required', Rule::in(['teacher', 'staff', 'driver'])],
            'employee_id' => 'required|integer',
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'earnings' => 'array',
            'earnings.*.label' => 'required|string|max:100',
            'earnings.*.amount' => 'required|numeric|min:0',
            'deduction_items' => 'array',
            'deduction_items.*.label' => 'required|string|max:100',
            'deduction_items.*.amount' => 'required|numeric|min:0',
            'payment_mode' => ['nullable', Rule::in(['Cash', 'Bank'])],
            'status' => ['required', Rule::in(['Draft', 'Pending', 'Paid'])],
            'remarks' => 'nullable|string|max:1000',
        ]);
    }

    private function assertEmployeeExists(string $employeeType, int $employeeId): void
    {
        $modelClass = match ($employeeType) {
            'teacher' => Teacher::class,
            'driver' => Driver::class,
            default => Staff::class,
        };
        if (! $modelClass::whereKey($employeeId)->exists()) {
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
            throw ValidationException::withMessages(['period' => 'A salary slip already exists for this staff member and period.']);
        }
    }

    /** @return array{0: float, 1: float} [basic, allowances] — rows labeled "Basic" (any case) count as basic salary, the rest as allowances. */
    private function splitEarnings(array $earnings): array
    {
        $basic = 0.0;
        $allowances = 0.0;
        foreach ($earnings as $row) {
            if (strtolower(trim($row['label'])) === 'basic') {
                $basic += (float) $row['amount'];
            } else {
                $allowances += (float) $row['amount'];
            }
        }

        return [$basic, $allowances];
    }

    private function present(SalarySlip $slip): array
    {
        return [
            ...$slip->toArray(),
            'employee_name' => $slip->employee->name ?? '—',
            'employee_code' => $slip->employee->employee_id ?? null,
        ];
    }

    private function nextSlipNo(): string
    {
        $session = AcademicSession::query()->where('is_current', true)->first();
        $sessionDigits = $session ? preg_replace('/\D+/', '', (string) $session->name) : '';
        $token = $sessionDigits !== '' ? $sessionDigits : now()->format('Y');
        $prefix = "SLP-{$token}-";
        $count = SalarySlip::where('slip_no', 'like', $prefix.'%')->count() + 1;

        return sprintf('%s%05d', $prefix, $count);
    }
}
