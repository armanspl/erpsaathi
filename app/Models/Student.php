<?php

namespace App\Models;

use App\Models\Concerns\HasAttendance;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Implements Authenticatable so the `student` guard (config/auth.php) can log a
 * Student in directly. Login is admission_no + dob, not password-based — see
 * StudentAuthController — so the inherited password/remember-token machinery is
 * never actually exercised, it's only present to satisfy the guard contract.
 */
class Student extends Model implements AuthenticatableContract
{
    use Authenticatable, HasAttendance;

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
        'is_rte',
        'rte_from_session_id',
        'payment_percentage',
    ];

    /** @var array<int, string|null>|null student_id => RTE-from session start (Y-m-d), per request */
    private static ?array $rteFrom = null;

    /** @var array<int, float>|null student_id => payment % for students not paying 100%, per request */
    private static ?array $paymentPercent = null;

    private static ?bool $paymentPercentReady = null;

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'admission_date' => 'date',
            're_admission' => 'boolean',
            'custom_field_values' => 'array',
            'permanent_same_as_current' => 'boolean',
            'is_rte' => 'boolean',
            'payment_percentage' => 'float',
        ];
    }

    /** True once the RTE migration has run (the fee code works unchanged before that). */
    public static function rteReady(): bool
    {
        static $ready;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('students', 'is_rte');
    }

    /**
     * Is this student RTE in $session? RTE students pay no school fees, only transport.
     * Applies to the marked "from" session and every later one — earlier sessions keep their fees.
     */
    public static function isRteIn(int $studentId, ?AcademicSession $session): bool
    {
        if (! self::rteReady()) {
            return false;
        }
        self::$rteFrom ??= self::query()
            ->where('is_rte', true)
            ->leftJoin('academic_sessions as rte_s', 'rte_s.id', '=', 'students.rte_from_session_id')
            ->pluck('rte_s.start_date', 'students.id')
            ->map(fn ($d) => $d ? substr((string) $d, 0, 10) : null)
            ->all();
        if (! array_key_exists($studentId, self::$rteFrom)) {
            return false;
        }
        $from = self::$rteFrom[$studentId];

        return $from === null || ! $session?->start_date || $session->start_date->format('Y-m-d') >= $from;
    }

    public static function flushRteCache(): void
    {
        self::$rteFrom = null;
        self::$paymentPercent = null;
        self::$paymentPercentReady = null;
    }

    /**
     * Payment Percentage (%) saved on the student: the share of the class fee structure they
     * pay (100 = full fee, 50 = HF, 0 = FF). 100 before the migration has run.
     */
    public static function paymentPercentFor(int $studentId): float
    {
        if (! (self::$paymentPercentReady ??= \Illuminate\Support\Facades\Schema::hasColumn('students', 'payment_percentage'))) {
            return 100.0;
        }
        self::$paymentPercent ??= self::query()
            ->where('payment_percentage', '<>', 100)
            ->pluck('payment_percentage', 'id')
            ->map(fn ($p) => max(0.0, min(100.0, (float) $p)))
            ->all();

        return self::$paymentPercent[$studentId] ?? 100.0;
    }

    /** Fee head names that RTE students still pay. */
    public static function isTransportHeadName(?string $name): bool
    {
        return stripos((string) $name, 'transport') !== false;
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

    /** Session-scoped months where fee is fully waived (not a payment). */
    public function feeDiscountMonths(): HasMany
    {
        return $this->hasMany(StudentFeeDiscountMonth::class);
    }

    /** Optional/elective subjects this student is enrolled in, per academic session — see StudentSubjectEnrollmentService. */
    public function subjectEnrollments(): HasMany
    {
        return $this->hasMany(StudentSubjectEnrollment::class);
    }

    /**
     * Match a selected branch, and also include students with no branch set.
     * Imports/legacy rows often leave branch_id null on single-campus schools.
     */
    public function scopeForBranch($query, ?int $branchId)
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
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
