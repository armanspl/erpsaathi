<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One before/after snapshot of a salary slip, written automatically by SalarySlip's model
 * events (see App\Services\Payroll\SalaryHistory). `before`/`after` hold the raw database row
 * so a rollback can put it back byte-for-byte.
 */
class SalarySlipHistory extends Model
{
    protected $fillable = [
        'batch_id', 'source', 'batch_label', 'action', 'salary_slip_id', 'employee_type', 'employee_id',
        'period', 'before', 'after', 'performed_by_id', 'rolled_back_at', 'rolled_back_by_id',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'rolled_back_at' => 'datetime',
        ];
    }
}
