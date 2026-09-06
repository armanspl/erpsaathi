<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    protected $fillable = ['vehicle_id', 'type', 'description', 'cost', 'date', 'next_due_date'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
