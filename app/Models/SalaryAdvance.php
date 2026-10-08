<?php

namespace App\Models;

use App\Services\Payroll\SalaryBankSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An advance paid to a teacher / staff / driver against a salary month. The total advanced for a
 * month pre-fills the ADV column of that month's salary slip (deducted from net pay).
 */
class SalaryAdvance extends Model
{
    protected $fillable = [
        'advance_no', 'employee_type', 'employee_id', 'period', 'basic_salary', 'amount',
        'paid_on', 'payment_mode', 'bank_account_id', 'remarks', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    /** An advance paid by Bank keeps its debit on that bank account in step. */
    protected static function booted(): void
    {
        static::saved(fn (SalaryAdvance $advance) => SalaryBankSync::syncAdvance((int) $advance->getKey()));
        static::deleted(fn (SalaryAdvance $advance) => SalaryBankSync::syncAdvance((int) $advance->getKey()));
    }

    public function employee(): MorphTo
    {
        return $this->morphTo();
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'created_by_id');
    }

    /** Total advanced to one person against one salary month. */
    public static function totalFor(string $employeeType, int $employeeId, string $period, ?int $ignoreId = null): float
    {
        return (float) static::query()
            ->where('employee_type', $employeeType)
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->sum('amount');
    }

    /** Next "ADV-<session digits>-00001" (highest existing number + 1, like salary slips). */
    public static function nextAdvanceNo(): string
    {
        $session = AcademicSession::query()->where('is_current', true)->first();
        $sessionDigits = $session ? preg_replace('/\D+/', '', (string) $session->name) : '';
        $token = $sessionDigits !== '' ? $sessionDigits : now()->format('Y');
        $prefix = "ADV-{$token}-";

        $max = 0;
        foreach (static::query()->where('advance_no', 'like', $prefix.'%')->pluck('advance_no') as $no) {
            $max = max($max, (int) substr($no, strlen($prefix)));
        }

        return sprintf('%s%05d', $prefix, $max + 1);
    }
}
