<?php

namespace App\Services;

use App\Models\ErpFeeStructure;
use App\Models\ErpFeeStructurePlan;

/**
 * The fee head a session's fee structure charges once a year — the head an imported
 * "ANN" (annual/session fee) payment must land on, so it offsets the same charge
 * FeeCalculator computes. Payments and charges are matched by fee_head_id, which the
 * plan resolves from the item's label, so the label is what has to match.
 */
class AnnualFeeHead
{
    /** Used when the session has no annual charge configured (e.g. older sessions with no plan). */
    public const FALLBACK = 'Session Fee';

    /** @var array<int, string> */
    private static array $cache = [];

    public static function nameFor(?int $sessionId): string
    {
        if (! $sessionId) {
            return self::FALLBACK;
        }

        return self::$cache[$sessionId] ??= self::resolve($sessionId);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function resolve(int $sessionId): string
    {
        $labels = ErpFeeStructurePlan::query()
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->flatMap(fn (ErpFeeStructurePlan $plan) => $plan->items)
            ->filter(fn ($item) => self::isAnnual($item->frequency))
            ->map(fn ($item) => trim((string) $item->label))
            ->merge(
                ErpFeeStructure::query()
                    ->with('feeHead:id,name')
                    ->where('academic_session_id', $sessionId)
                    ->get()
                    ->filter(fn (ErpFeeStructure $s) => self::isAnnual($s->frequency) && $s->feeHead)
                    ->map(fn (ErpFeeStructure $s) => trim((string) $s->feeHead->name))
            )
            ->filter()
            ->unique()
            ->values();

        // Several annual charges (e.g. "Annual Fee" + "Exam Fee"): the one named annual/session.
        return $labels->first(fn (string $l) => (bool) preg_match('/\b(annual|session)\b/i', $l))
            ?? ($labels->count() === 1 ? $labels->first() : self::FALLBACK);
    }

    private static function isAnnual(?string $frequency): bool
    {
        return strtolower(str_replace(' ', '_', trim((string) $frequency))) === 'annual';
    }
}
