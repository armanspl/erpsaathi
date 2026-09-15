<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicCalendarEntry extends Model
{
    public const CATEGORY_HOLIDAY = 'holiday';

    public const CATEGORY_EXAMINATION = 'examination';

    public const CATEGORY_PROGRAMME = 'programme';

    public const CATEGORY_DEADLINE = 'deadline';

    public const CATEGORIES = [
        self::CATEGORY_HOLIDAY,
        self::CATEGORY_EXAMINATION,
        self::CATEGORY_PROGRAMME,
        self::CATEGORY_DEADLINE,
    ];

    protected $fillable = [
        'academic_session_id',
        'category',
        'title',
        'month_label',
        'date_label',
        'start_date',
        'end_date',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
