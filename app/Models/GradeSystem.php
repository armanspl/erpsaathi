<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeSystem extends Model
{
    protected $table = 'grade_systems';

    protected $fillable = [
        'grade',
        'min_percentage',
        'max_percentage',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
        ];
    }

    public static function forPercentage(float $percentage): ?self
    {
        // Ordered by highest min_percentage first, so the first band whose floor the percentage
        // clears is the right one — also requiring max_percentage >= percentage leaves gaps
        // between whole-number bands (e.g. 80-89 then 90-100) that a fractional percentage like
        // 89.87 falls straight through, returning no grade at all.
        return static::where('min_percentage', '<=', $percentage)
            ->orderByDesc('min_percentage')
            ->first();
    }
}
