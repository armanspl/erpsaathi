<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookExpenseItem extends Model
{
    protected $fillable = ['book_expense_id', 'store_book_id', 'title', 'price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function bookExpense(): BelongsTo
    {
        return $this->belongsTo(BookExpense::class);
    }

    public function storeBook(): BelongsTo
    {
        return $this->belongsTo(StoreBook::class);
    }
}
