<?php

namespace App\Services;

/**
 * Credits multi-month fee lines to earliest months first (FIFO),
 * so a follow-up that clears Apr remainder + May full does not
 * split evenly and leave May under-paid.
 */
class FeeMonthAllocator
{
    /**
     * @param  list<string>  $months  Y-m keys (any order)
     * @param  array<string, float>  $alreadyCoveredByMonth  paid+discount already applied per month
     * @return array<string, array{paid: float, discount: float}>
     */
    public static function distribute(
        float $paid,
        float $discount,
        array $months,
        float $unitCharge,
        array $alreadyCoveredByMonth = [],
        string $frequency = 'monthly'
    ): array {
        $freq = strtolower(str_replace(' ', '_', $frequency));
        $sorted = collect($months)
            ->map(fn ($m) => substr((string) $m, 0, 7))
            ->filter(fn ($k) => (bool) preg_match('/^\d{4}-\d{2}$/', $k))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($sorted === []) {
            return [];
        }

        // Annual / one-time: attribute the whole payment to the earliest tagged month.
        if (in_array($freq, ['annual', 'one_time'], true)) {
            $first = $sorted[0];

            return [
                $first => [
                    'paid' => round($paid, 4),
                    'discount' => round($discount, 4),
                ],
            ];
        }

        $applicable = [];
        foreach ($sorted as $monthKey) {
            if ($freq === 'quarterly') {
                $monthNum = (int) substr($monthKey, 5, 2);
                if (! in_array($monthNum, [4, 7, 10, 1], true)) {
                    continue;
                }
            }
            $applicable[] = $monthKey;
        }

        if ($applicable === []) {
            $applicable = $sorted;
        }

        // Unknown unit charge: fall back to equal split (legacy behaviour).
        if ($unitCharge <= 0.0001) {
            $count = count($applicable);
            $paidShare = $paid / $count;
            $discShare = $discount / $count;
            $out = [];
            foreach ($applicable as $monthKey) {
                $out[$monthKey] = [
                    'paid' => round($paidShare, 4),
                    'discount' => round($discShare, 4),
                ];
            }

            return $out;
        }

        $paidLeft = $paid;
        $discLeft = $discount;
        $out = [];
        $covered = $alreadyCoveredByMonth;

        foreach ($applicable as $monthKey) {
            $already = (float) ($covered[$monthKey] ?? 0);
            $need = max(0.0, $unitCharge - $already);
            if ($need <= 0.0001) {
                continue;
            }

            $discTake = min($discLeft, $need);
            $discLeft -= $discTake;
            $needAfterDisc = $need - $discTake;
            $paidTake = min($paidLeft, $needAfterDisc);
            $paidLeft -= $paidTake;

            if ($paidTake <= 0.0001 && $discTake <= 0.0001) {
                continue;
            }

            $out[$monthKey] = [
                'paid' => round($paidTake, 4),
                'discount' => round($discTake, 4),
            ];
            $covered[$monthKey] = $already + $paidTake + $discTake;
        }

        // Overpayment / leftover → last applicable month.
        if (($paidLeft > 0.0001 || $discLeft > 0.0001) && $applicable !== []) {
            $last = $applicable[array_key_last($applicable)];
            $out[$last] = $out[$last] ?? ['paid' => 0.0, 'discount' => 0.0];
            $out[$last]['paid'] = round($out[$last]['paid'] + $paidLeft, 4);
            $out[$last]['discount'] = round($out[$last]['discount'] + $discLeft, 4);
        }

        return $out;
    }
}
