<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = [
        'gate_pass_no',
        'name',
        'phone',
        'purpose',
        'whom_to_meet',
        'check_in_at',
        'check_out_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }
}
