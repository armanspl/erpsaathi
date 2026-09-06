<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = ['route_id', 'stop_name', 'sequence_no', 'fare', 'km', 'pickup_time', 'drop_time'];

    protected function casts(): array
    {
        return [
            'fare' => 'decimal:2',
            'km' => 'decimal:2',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }
}
