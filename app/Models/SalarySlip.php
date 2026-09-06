<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalarySlip extends Model
{
    protected $fillable = [
        'slip_no', 'employee_type', 'employee_id', 'period', 'basic_salary', 'allowances', 'earnings',
        'deductions', 'deduction_items', 'net_salary', 'status', 'paid_on', 'payment_mode', 'remarks',
        'generated_by_id', 'days_in_month', 'present', 'absent', 'cl', 'total_days', 'per_day_rate',
        'this_month_salary', 'advance',
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

    public function employee(): MorphTo
    {
        return $this->morphTo();
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'generated_by_id');
    }
}
