<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCustomField extends Model
{
    protected $fillable = [
        'label',
        'type',
        'placeholder',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
