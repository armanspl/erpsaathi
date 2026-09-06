<?php

namespace App\Models;

use App\Models\Concerns\HasAttendance;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasAttendance;

    protected $fillable = [
        'admission_no',
        'roll_no',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'school_class_id',
        'section_id',
        'branch_id',
        'father_id',
        'mother_id',
        'guardian_id',
        'gender',
        'dob',
        'blood_group',
        'category',
        'religion',
        'nationality',
        'aadhar_no',
        'pan_no',
        'mobile',
        'email',
        'address',
        'address_line_2',
        'city',
        'state',
        'pincode',
        'permanent_same_as_current',
        'permanent_address_line_1',
        'permanent_address_line_2',
        'permanent_city',
        'permanent_state',
        'permanent_pincode',
        'status',
        'admission_status',
        'custom_field_values',
        're_admission',
        'admission_date',
        'fee_start_month',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'admission_date' => 'date',
            're_admission' => 'boolean',
            'custom_field_values' => 'array',
            'permanent_same_as_current' => 'boolean',
        ];
    }

    /**
     * First month (Y-m) from which fees are billable.
     * Falls back to admission month when Fee Start Month is not set.
     */
    public function feeStartMonthKey(): ?string
    {
        $raw = $this->fee_start_month;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}/', $raw)) {
            return substr($raw, 0, 7);
        }

        if ($this->admission_date) {
            return $this->admission_date->format('Y-m');
        }

        return null;
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

    public function father(): BelongsTo
    {
        return $this->belongsTo(ParentGuardian::class, 'father_id');
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(ParentGuardian::class, 'mother_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(ParentGuardian::class, 'guardian_id');
    }

    public function additionalDetail(): HasOne
    {
        return $this->hasOne(StudentAdditionalDetail::class);
    }

    public function udiseDetail(): HasOne
    {
        return $this->hasOne(StudentUdiseDetail::class);
    }

    public function documents(): HasOne
    {
        return $this->hasOne(StudentDocument::class);
    }

    public function sessionHistories(): HasMany
    {
        return $this->hasMany(StudentSessionHistory::class);
    }

    /**
     * The most recent session's history row — `session` sorts lexically in chronological
     * order (e.g. "2026-27" > "2025-26"). `latestOfMany()`/`ofMany()` only exist on HasOne
     * (via the CanBeOneOfMany trait) — HasMany doesn't have them, so this must be defined as
     * its own hasOne(), not derived from sessionHistories()'s hasMany().
     */
    public function latestSessionHistory(): HasOne
    {
        return $this->hasOne(StudentSessionHistory::class)->latestOfMany('session');
    }

    /** Computed, not stored — same convention as everywhere else in this app that derives a figure live rather than persisting it. */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->dob ? (int) $this->dob->diffInYears(now()) : null,
        );
    }
}
