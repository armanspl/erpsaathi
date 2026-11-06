<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePayment extends Model
{
    /** @var array<string, bool> database name => has the totals_snapshot column */
    private static array $snapshotColumn = [];

    /**
     * Whether this school's database has the totals_snapshot column yet. Saving stays safe in a
     * school whose migration hasn't run (the snapshot is simply not kept there).
     */
    public static function hasTotalsSnapshot(): bool
    {
        $model = new static;
        $db = (string) $model->getConnection()->getDatabaseName();

        return self::$snapshotColumn[$db] ??= \Illuminate\Support\Facades\Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), 'totals_snapshot');
    }

    protected $fillable = [
        'receipt_no',
        'student_id',
        'academic_session_id',
        'items',
        'amount',
        'discount_amount',
        'totals_snapshot',
        'fine_amount',
        'payment_mode',
        'bank_account_id',
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
            'totals_snapshot' => 'array',
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

    /** The bank account the money went to — set when PMNT MODE / the receipt names one. */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** True once the bank_account_id migration has run (everything works without it). */
    public static function hasBankAccountColumn(): bool
    {
        static $ready;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('fee_payments', 'bank_account_id');
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
     * Next automatic fee receipt number: a plain number continuing the school's own receipt
     * numbers — the highest plain-number receipt saved + 1 (4481 → 4482, 4483 …), never a
     * prefix like "RCP-". Fee Settings › "Last receipt no." (optional) can only move it further
     * on, never back. A number already held — also as an imported "4482/26-27" — is passed over,
     * so the result is never a duplicate at the time of the call. Save through
     * createWithAutoReceiptNo() to also survive two receipts saved at once.
     *
     * @param  int  $skip  extra steps past the highest number (retry after a clash)
     */
    public static function nextReceiptNo(?AcademicSession $session = null, int $skip = 0): string
    {
        $lastNo = \Illuminate\Support\Facades\Schema::hasColumn('erp_fee_settings', 'receipt_last_no')
            ? (int) ErpFeeSetting::current()->receipt_last_no
            : 0;
        $seq = max($lastNo, static::highestPlainReceiptNo()) + 1 + max(0, $skip);
        while (static::query()->where('receipt_no', (string) $seq)->orWhere('receipt_no', 'like', $seq.'/%')->exists()) {
            $seq++;
        }

        return (string) $seq;
    }

    /** Highest receipt no. that is a plain number ("4684"; not "4684/26-27" or "RCP-0001"). */
    public static function highestPlainReceiptNo(): int
    {
        $max = 0;
        foreach (static::query()->where('receipt_no', 'not like', '%/%')->where('receipt_no', 'not like', 'RCP-%')->pluck('receipt_no') as $no) {
            if (preg_match('/^\d{1,15}$/', (string) $no)) {
                $max = max($max, (int) $no);
            }
        }

        return $max;
    }

    /**
     * Create a fee payment with the next automatic receipt no. If another receipt took the same
     * number in the meantime (receipt_no is unique), take the following number and try again.
     */
    public static function createWithAutoReceiptNo(array $attributes, ?AcademicSession $session = null): self
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return static::create(['receipt_no' => static::nextReceiptNo($session, $attempt)] + $attributes);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt >= 4) {
                    throw $e;
                }
            }
        }
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
