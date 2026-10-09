<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePayment extends Model
{
    protected $fillable = [
        'receipt_no',
        'student_id',
        'academic_session_id',
        'items',
        'amount',
        'discount_amount',
        'fine_amount',
        'payment_mode',
        'payment_date',
        'remarks',
        'status',
        'refunded_amount',
        'refund_reason',
        'refunded_at',
        'collected_by_id',
        'reference_no',
        'edited_at',
        'edited_by_id',
        'rollback_reason',
        'rolled_back_at',
        'rolled_back_by_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'payment_date' => 'date',
            'refunded_at' => 'datetime',
            'edited_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'collected_by_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'edited_by_id');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'rolled_back_by_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(FeePaymentAudit::class)->latest('id');
    }

    /**
     * Next fee receipt number: RCP-{session}-{month}-{seq}
     * Example: RCP-2026-27-10-0001 (academic year 2026-27, October, sequence 0001).
     */
    public static function nextReceiptNo(?AcademicSession $session = null): string
    {
        $session ??= AcademicSession::query()->where('is_current', true)->first();
        $yearToken = static::sessionYearToken($session);
        $month = now()->format('m');
        $prefix = "RCP-{$yearToken}-{$month}-";

        $max = 0;
        foreach (static::query()->where('receipt_no', 'like', $prefix.'%')->pluck('receipt_no') as $no) {
            if (preg_match('/(\d+)$/', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Academic year as YYYY-YY (e.g. 2026-27). */
    public static function sessionYearToken(?AcademicSession $session): string
    {
        $name = trim((string) ($session?->name ?? ''));

        if (preg_match('/^(\d{4})-(\d{4})$/', $name, $m)) {
            return $m[1].'-'.substr($m[2], -2);
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $name, $m)) {
            return $m[1].'-'.$m[2];
        }

        if ($session?->start_date) {
            $startY = (int) $session->start_date->format('Y');
            $endY = $session->end_date
                ? (int) $session->end_date->format('Y')
                : $startY + 1;

            return sprintf('%04d-%02d', $startY, $endY % 100);
        }

        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');
        // Indian academic year typically starts in April.
        if ($month >= 4) {
            return sprintf('%04d-%02d', $year, ($year + 1) % 100);
        }

        return sprintf('%04d-%02d', $year - 1, $year % 100);
    }
}
