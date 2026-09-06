<?php

namespace App\Models;

use App\Models\Concerns\HasAttendance;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasAttendance;

    protected $table = 'staff';

    protected $fillable = [
        'employee_id',
        'name',
        'department',
        'phone',
        'email',
        'status',
        'salary',
        'custom_field_values',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'custom_field_values' => 'array',
        ];
    }
}
