<?php

namespace App\Models;

use App\Models\Concerns\HasAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasAttendance;

    protected $fillable = [
        'employee_id',
        'name',
        'phone',
        'license_no',
        'vehicle_no',
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

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
