<?php

namespace App\Models;

use App\Services\Payroll\SalaryBankSync;
use App\Services\Payroll\SalaryHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalarySlip extends Model
{
    protected $fillable = [
        'slip_no', 'employee_type', 'employee_id', 'period', 'basic_salary', 'allowances', 'earnings',
        'deductions', 'deduction_items', 'net_salary', 'status', 'paid_on', 'payment_mode', 'bank_account_id', 'remarks',
        'generated_by_id', 'days_in_month', 'present', 'absent', 'cl', 'total_days', 'per_day_rate',
        'this_month_salary', 'advance', 'sheet_name', 'sheet_block', 'sheet_row',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'earnings' => 'array',
            'deductions' => 'decimal:2',
            'deduction_items' => 'array',
            'net_salary' => 'decimal:2',
            'paid_on' => 'date',
            'present' => 'decimal:2',
            'absent' => 'decimal:2',
            'cl' => 'decimal:2',
            'total_days' => 'decimal:2',
            'per_day_rate' => 'decimal:2',
            'this_month_salary' => 'decimal:2',
            'advance' => 'decimal:2',
        ];
    }

    /**
     * Every change is snapshotted for Salary History / rollback, and a slip paid by Bank keeps
     * its debit on that bank account in step (SalaryBankSync).
     */
    protected static function booted(): void
    {
        static::created(fn (SalarySlip $slip) => SalaryHistory::record('created', $slip));
        static::updated(fn (SalarySlip $slip) => SalaryHistory::record('updated', $slip));
        static::deleted(fn (SalarySlip $slip) => SalaryHistory::record('deleted', $slip));
        static::saved(fn (SalarySlip $slip) => SalaryBankSync::sync((int) $slip->getKey()));
        static::deleted(fn (SalarySlip $slip) => SalaryBankSync::sync((int) $slip->getKey()));
    }

    public function employee(): MorphTo
    {
        return $this->morphTo();
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'generated_by_id');
    }

    /**
     * Next "SLP-<session digits>-00001". Uses the highest existing number, not a row count, so a
     * deleted or rolled-back slip can never make the next number collide with an existing one.
     */
    public static function nextSlipNo(): string
    {
        $session = AcademicSession::query()->where('is_current', true)->first();
        $sessionDigits = $session ? preg_replace('/\D+/', '', (string) $session->name) : '';
        $token = $sessionDigits !== '' ? $sessionDigits : now()->format('Y');
        $prefix = "SLP-{$token}-";

        $max = 0;
        foreach (static::query()->where('slip_no', 'like', $prefix.'%')->pluck('slip_no') as $no) {
            $max = max($max, (int) substr($no, strlen($prefix)));
        }

        return sprintf('%s%05d', $prefix, $max + 1);
    }
}
