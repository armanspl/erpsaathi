<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $fillable = ['vehicle_id', 'date', 'liters', 'cost', 'odometer_reading', 'remarks'];

    protected function casts(): array
    {
        return [
            'liters' => 'decimal:2',
            'cost' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
