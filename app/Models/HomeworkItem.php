<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HomeworkItem extends Model
{
    protected $fillable = [
        'homework_id',
        'subject_id',
        'content',
        'attachment_path',
        'attachment_name',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeworkItem $item) {
            if ($item->attachment_path) {
                Storage::disk('local')->delete($item->attachment_path);
            }
        });
    }
}
