<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FineRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'amount',
        'grace_days',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
