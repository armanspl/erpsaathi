<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookExpense extends Model
{
    protected $fillable = [
        'expense_no', 'student_id', 'branch_id', 'school_class_id', 'section_id',
        'date', 'notes', 'total_amount', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookExpenseItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'created_by_id');
    }
}
