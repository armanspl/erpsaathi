<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Driver;
use App\Models\SalaryAdvance;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Teacher;
use App\Services\DocumentDataBuilder;
use App\Services\Payroll\SalaryHistory;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Salary advances: money paid to a teacher / staff / driver before their salary, against a given
 * salary month — half the basic, the full basic, or any amount. The month's total advance is
 * deducted as ADV on that month's salary slip (Create Salary Slip pre-fills it).
 */
class SalaryAdvanceController extends Controller
{
    public function __construct(private DocumentDataBuilder $dataBuilder) {}

    public function index(Request $request)
    {
        $query = SalaryAdvance::with(['employee', 'bankAccount']);

        foreach (['employee_type', 'period'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json(
            $query->orderByDesc('paid_on')->orderByDesc('id')->get()->map(fn (SalaryAdvance $a) => $this->present($a))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $advance = SalaryAdvance::create($data + [
            'advance_no' => SalaryAdvance::nextAdvanceNo(),
            'created_by_id' => Auth::guard('erp')->id(),
        ]);
        $this->syncSlipAdvance($advance->employee_type, $advance->employee_id, $advance->period);

        return response()->json($this->present($advance->fresh(['employee', 'bankAccount'])), 201);
    }

    public function update(Request $request, SalaryAdvance $salaryAdvance)
    {
        $data = $this->validated($request, $salaryAdvance);
        $before = [$salaryAdvance->employee_type, $salaryAdvance->employee_id, $salaryAdvance->period];

        $salaryAdvance->update($data);
        $this->syncSlipAdvance(...$before);
        $this->syncSlipAdvance($salaryAdvance->employee_type, $salaryAdvance->employee_id, $salaryAdvance->period);

        return response()->json($this->present($salaryAdvance->fresh(['employee', 'bankAccount'])));
    }

    public function destroy(SalaryAdvance $salaryAdvance)
    {
        $this->assertSlipNotPaid($salaryAdvance->employee_type, $salaryAdvance->employee_id, $salaryAdvance->period);
        $salaryAdvance->delete();
        $this->syncSlipAdvance($salaryAdvance->employee_type, $salaryAdvance->employee_id, $salaryAdvance->period);

        return response()->json(['success' => true]);
    }

    /** Printable receipt for one advance (school header, who, which month, amount in words, signatures). */
    public function receipt(SalaryAdvance $salaryAdvance): StreamedResponse
    {
        $d = $this->dataBuilder->salaryAdvance($salaryAdvance);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $logo = str_starts_with((string) $d['school_logo'], 'data:') ? '<img src="'.$d['school_logo'].'" style="height:44pt">' : '';
        $contact = implode(' · ', array_filter([$d['school_phone'], $d['school_email']]));
        $row = fn ($label, $value) => '<tr><th>'.$e($label).'</th><td>'.$e($value).'</td></tr>';

        $copy = fn (string $title) => '
            <div class="copy">
                <table class="head"><tr>
                    <td style="width:60pt">'.$logo.'</td>
                    <td class="school">
                        <div class="name">'.$e($d['school_name']).'</div>
                        <div>'.$e($d['school_address']).'</div>
                        '.($contact ? '<div>'.$e($contact).'</div>' : '').'
                    </td>
                    <td style="width:60pt"></td>
                </tr></table>
                <div class="title">SALARY ADVANCE RECEIPT <span>('.$e($title).')</span></div>
                <table class="meta"><tr>
                    <td>Receipt No: <b>'.$e($d['advance_no']).'</b></td>
                    <td style="text-align:right">Date: <b>'.$e($d['paid_on']).'</b></td>
                </tr></table>
                <table class="grid">
                    '.$row('Employee name', $d['employee_name']).'
                    '.$row('Employee ID', $d['employee_code'] ?: '—').'
                    '.$row('Staff type', $d['employee_type']).'
                    '.$row('Advance against salary of', $d['period']).'
                    '.$row('Basic salary', 'Rs. '.$d['basic_salary']).'
                    '.$row('Payment mode', $d['payment_mode']).'
                    '.($d['remarks'] !== '' ? $row('Remarks', $d['remarks']) : '').'
                    <tr class="amount"><th>Advance paid</th><td>Rs. '.$e($d['amount']).'</td></tr>
                </table>
                <p class="words">Amount in words: <b>'.$e($d['amount_in_words']).'</b></p>
                <p class="note">This advance will be deducted from the salary of '.$e($d['period']).'.</p>
                <table class="sign"><tr>
                    <td>Receiver\'s Signature</td>
                    <td style="text-align:right">'.$e($d['principal_signature'] ?: 'Authorised Signatory').'</td>
                </tr></table>
            </div>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page { margin: 10mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
            .copy { border: 0.8pt solid #333; padding: 10pt 12pt; }
            .copy + .cut { border-top: 0.8pt dashed #888; margin: 4mm 0; }
            table { width: 100%; border-collapse: collapse; }
            .head td { vertical-align: middle; }
            .school { text-align: center; font-size: 8pt; color: #333; }
            .school .name { font-size: 14pt; font-weight: bold; color: #111; margin-bottom: 2pt; }
            .title { text-align: center; font-weight: bold; font-size: 11pt; margin: 6pt 0 4pt; letter-spacing: 1pt; border-top: 0.6pt solid #333; border-bottom: 0.6pt solid #333; padding: 3pt 0; }
            .title span { font-size: 8pt; font-weight: normal; letter-spacing: 0; }
            .meta td { padding: 2pt 0 6pt; }
            .grid th, .grid td { border: 0.5pt solid #999; padding: 3pt 6pt; text-align: left; }
            .grid th { width: 42%; background: #f1f1f1; font-weight: normal; }
            .grid .amount th, .grid .amount td { font-weight: bold; font-size: 10.5pt; }
            .words { margin: 7pt 0 2pt; }
            .note { margin: 0; font-size: 8pt; color: #555; }
            .sign td { padding-top: 24pt; font-size: 8.5pt; }
        </style></head><body>'.$copy('School Copy').'<div class="cut"></div>'.$copy('Employee Copy').'</body></html>';

        $filename = $salaryAdvance->advance_no.'.pdf';

        return response()->streamDownload(function () use ($html) {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();
            echo $dompdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function validated(Request $request, ?SalaryAdvance $existing = null): array
    {
        $data = $request->validate([
            'employee_type' => ['required', Rule::in(['teacher', 'staff', 'driver'])],
            'employee_id' => 'required|integer',
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'basic_salary' => 'required|numeric|min:0|max:99999999',
            'amount' => 'required|numeric|min:1|max:99999999',
            'paid_on' => 'required|date',
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'remarks' => 'nullable|string|max:255',
        ]);

        $class = match ($data['employee_type']) {
            'teacher' => Teacher::class,
            'driver' => Driver::class,
            default => Staff::class,
        };
        if (! $class::whereKey($data['employee_id'])->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Selected staff member was not found.']);
        }

        if ($data['payment_mode'] === 'Bank') {
            if (empty($data['bank_account_id'])) {
                $ids = BankAccount::query()->limit(2)->pluck('id');
                if ($ids->count() !== 1) {
                    throw ValidationException::withMessages(['bank_account_id' => $ids->isEmpty()
                        ? 'Add a bank account under Finance & Payroll › Bank Accounts first, or pay by Cash.'
                        : 'Choose the bank account the advance is paid from.']);
                }
                $data['bank_account_id'] = (int) $ids->first();
            }
        } else {
            $data['bank_account_id'] = null;
        }

        $this->assertSlipNotPaid($data['employee_type'], (int) $data['employee_id'], $data['period']);
        if ($existing) {
            $this->assertSlipNotPaid($existing->employee_type, $existing->employee_id, $existing->period);
        }

        // An advance can't be more than the month's basic salary (all advances for that month together).
        $already = SalaryAdvance::totalFor($data['employee_type'], (int) $data['employee_id'], $data['period'], $existing?->id);
        if ((float) $data['basic_salary'] > 0 && $already + (float) $data['amount'] > (float) $data['basic_salary'] + 0.001) {
            $left = max(0, (float) $data['basic_salary'] - $already);
            throw ValidationException::withMessages(['amount' => 'Advance is more than the basic salary for this month — '
                .number_format($already, 2).' already advanced, at most '.number_format($left, 2).' more can be paid.']);
        }

        return $data;
    }

    /** Once that month's salary is paid, its advances are settled and can no longer change. */
    private function assertSlipNotPaid(string $employeeType, int $employeeId, string $period): void
    {
        $paid = SalarySlip::where('employee_type', $employeeType)->where('employee_id', $employeeId)
            ->where('period', $period)->where('status', 'Paid')->exists();
        if ($paid) {
            throw ValidationException::withMessages(['period' => 'The salary for this month is already paid — its advance is settled and can\'t be changed.']);
        }
    }

    /**
     * An unpaid slip already made for that month picks up the new advance total (ADV column), with
     * its net pay re-worked (net = this month salary + extra earnings − extra deductions − ADV).
     */
    private function syncSlipAdvance(string $employeeType, int $employeeId, string $period): void
    {
        $slip = SalarySlip::where('employee_type', $employeeType)->where('employee_id', $employeeId)
            ->where('period', $period)->where('status', '!=', 'Paid')->first();
        if (! $slip) {
            return;
        }

        $total = SalaryAdvance::totalFor($employeeType, $employeeId, $period);
        $oldAdvance = (float) $slip->advance;
        if (abs($total - $oldAdvance) < 0.005) {
            return;
        }

        $label = 'Advance updated on slip '.($slip->slip_no ?: '#'.$slip->id).' — ADV '.number_format($total, 2);
        SalaryHistory::batch('manual', $label, fn () => $slip->update([
            'advance' => $total,
            'net_salary' => round((float) $slip->net_salary + $oldAdvance - $total, 2),
        ]));
    }

    private function present(SalaryAdvance $advance): array
    {
        $bank = $advance->bank_account_id ? $advance->bankAccount : null;

        return [
            ...$advance->toArray(),
            'employee_name' => $advance->employee->name ?? '—',
            'employee_code' => $advance->employee->employee_id ?? null,
            'bank_account_label' => $bank ? trim($bank->bank_name.' — '.$bank->account_name) : null,
        ];
    }
}
