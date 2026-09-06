<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['room_no', 'type', 'capacity', 'monthly_fee', 'status'];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
        ];
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
