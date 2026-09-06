<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookIssue extends Model
{
    protected $fillable = [
        'book_id', 'library_member_id', 'issue_date', 'due_date', 'return_date',
        'status', 'fine_amount', 'fine_status', 'fine_paid_at', 'collected_by_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'fine_amount' => 'decimal:2',
            'fine_paid_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(LibraryMember::class, 'library_member_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'collected_by_id');
    }
}
