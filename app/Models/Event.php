<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['title', 'description', 'venue', 'event_date', 'start_time', 'end_time', 'status'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }
}
