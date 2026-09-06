<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalaryStructure extends Model
{
    protected $fillable = ['employee_type', 'employee_id', 'basic_salary', 'allowances', 'deductions'];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
        ];
    }

    public function employee(): MorphTo
    {
        return $this->morphTo();
    }
}
