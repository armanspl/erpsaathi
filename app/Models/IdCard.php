<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdCard extends Model
{
    protected $fillable = ['card_no', 'holder_type', 'holder_id', 'issued_date', 'valid_until', 'status'];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
