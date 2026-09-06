<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelVisitor extends Model
{
    protected $fillable = ['student_id', 'visitor_name', 'relation', 'purpose', 'visit_date', 'in_time', 'out_time'];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
