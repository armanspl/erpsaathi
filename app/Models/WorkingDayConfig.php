<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingDayConfig extends Model
{
    protected $fillable = [
        'weekly_off_days',
    ];

    protected function casts(): array
    {
        return [
            'weekly_off_days' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['weekly_off_days' => ['Sunday']]);
    }
}
