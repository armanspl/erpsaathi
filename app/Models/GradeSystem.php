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
        return static::where('min_percentage', '<=', $percentage)
            ->where('max_percentage', '>=', $percentage)
            ->first();
    }
}
