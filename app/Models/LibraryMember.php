<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LibraryMember extends Model
{
    protected $fillable = ['member_type', 'member_id', 'library_card_no', 'status', 'max_books', 'joined_date'];

    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
        ];
    }

    public function member(): MorphTo
    {
        return $this->morphTo();
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class);
    }
}
