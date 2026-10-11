<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'multi_session',
        'session_fines',
    ];

    /** True on a read-only per-session view made by forSession() — never persisted. */
    public bool $isSessionSlice = false;

    protected static function booted(): void
    {
        static::saving(fn (FeePayment $payment) => ! $payment->isSessionSlice);
    }

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'multi_session' => 'boolean',
            'session_fines' => 'array',
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
     * Payments holding money for any of $sessionIds: their own session, or (multi-session
     * receipts) an item tagged with one of them. Combine with forSession() to count only the
     * part that belongs to a session.
     *
     * @param  list<int>  $sessionIds
     */
    public function scopeTouchingSessions(Builder $query, array $sessionIds): Builder
    {
        $ids = array_values(array_unique(array_map('intval', $sessionIds)));

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('academic_session_id', $ids)
                ->orWhere(function (Builder $q) use ($ids) {
                    $q->where('multi_session', true)->where(function (Builder $q) use ($ids) {
                        foreach ($ids as $id) {
                            $q->orWhereJsonContains('items', ['academic_session_id' => $id]);
                        }
                    });
                });
        });
    }

    /** Session an item's money belongs to: its own tag on a multi-session receipt, else the payment's. */
    public function itemSessionId(array $item): int
    {
        return (int) (($this->multi_session ? ($item['academic_session_id'] ?? null) : null) ?? $this->academic_session_id);
    }

    /**
     * This payment as seen from one academic session: only that session's items, with amount,
     * fine, discount and refunds scaled to them. Ordinary (single-session) payments are returned
     * unchanged for their own session and as null for any other. The result is read-only.
     */
    public function forSession(int $sessionId): ?static
    {
        $own = (int) $this->academic_session_id === $sessionId;
        if (! $this->multi_session) {
            return $own ? $this : null;
        }

        $items = array_values(array_filter($this->items ?? [], fn ($item) => $this->itemSessionId($item) === $sessionId));
        $fines = $this->session_fines ?? [];
        $fine = array_key_exists((string) $sessionId, $fines) || array_key_exists($sessionId, $fines)
            ? (float) ($fines[$sessionId] ?? $fines[(string) $sessionId])
            : ($own && $fines === [] ? (float) $this->fine_amount : 0.0);
        $amount = round(array_sum(array_map(fn ($i) => (float) ($i['amount'] ?? 0), $items)) + $fine, 2);
        if ($items === [] && $fine <= 0) {
            return null;
        }

        $gross = (float) $this->amount;
        $ratio = $gross > 0 ? $amount / $gross : 0.0;
        $slice = clone $this;
        $slice->isSessionSlice = true;
        $slice->setAttribute('items', $items);
        $slice->setAttribute('amount', $amount);
        $slice->setAttribute('fine_amount', round($fine, 2));
        $slice->setAttribute('discount_amount', round((float) $this->discount_amount * $ratio, 2));
        $slice->setAttribute('refunded_amount', round((float) $this->refunded_amount * $ratio, 2));

        return $slice;
    }

    /**
     * Expands payments into per-session slices for $sessionIds (multi-session receipts appear
     * once per session they touch; ordinary payments pass through unchanged).
     *
     * @param  iterable<FeePayment>  $payments
     * @param  list<int>  $sessionIds
     * @return \Illuminate\Support\Collection<int, FeePayment>
     */
    public static function slicesFor(iterable $payments, array $sessionIds): \Illuminate\Support\Collection
    {
        $out = collect();
        foreach ($payments as $payment) {
            if (! $payment->multi_session) {
                if (in_array((int) $payment->academic_session_id, array_map('intval', $sessionIds), true)) {
                    $out->push($payment);
                }

                continue;
            }
            foreach ($sessionIds as $sid) {
                if ($slice = $payment->forSession((int) $sid)) {
                    $slice->setAttribute('academic_session_id', (int) $sid);
                    $out->push($slice);
                }
            }
        }

        return $out;
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
