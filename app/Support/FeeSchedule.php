<?php

namespace App\Support;

/**
 * Which months a recurring fee-structure line is charged in.
 *
 * Monthly lines are charged every month and quarterly lines in Apr / Jul / Oct / Jan — unless the
 * Fee Structure line has its own "Apply in months" list (e.g. Examination Fee in Sep + Mar), in
 * which case it is charged only in those months. Annual / one-time lines are not month-based
 * (they are due once, on the first billable month) and are not handled here.
 */
class FeeSchedule
{
    public const QUARTER_MONTHS = [4, 7, 10, 1];

    public static function frequency(array $row): string
    {
        return strtolower(str_replace(' ', '_', (string) ($row['frequency'] ?? 'monthly')));
    }

    /**
     * The line's chosen months (1–12), or [] for "use the frequency's default months".
     *
     * @return list<int>
     */
    public static function chosenMonths(array $row): array
    {
        return self::normalizeMonths($row['months'] ?? null);
    }

    /** @return list<int> */
    public static function normalizeMonths(mixed $months): array
    {
        if (! is_array($months)) {
            return [];
        }

        $out = [];
        foreach ($months as $m) {
            $n = (int) $m;
            if ($n >= 1 && $n <= 12) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }

    /** Month numbers a recurring line is charged in, or null when it is charged every month. */
    public static function monthNumbersFor(string $frequency, array $chosen): ?array
    {
        if ($chosen !== []) {
            return $chosen;
        }

        return $frequency === 'quarterly' ? self::QUARTER_MONTHS : null;
    }

    /** Is a monthly / quarterly line charged in this month? ($month = 'Y-m' key or 1–12). */
    public static function chargesInMonth(array $row, string|int $month): bool
    {
        $freq = self::frequency($row);
        if (! in_array($freq, ['monthly', 'quarterly'], true)) {
            return false;
        }

        $num = is_int($month) ? $month : (int) substr($month, 5, 2);
        $allowed = self::monthNumbersFor($freq, self::chosenMonths($row));

        return $allowed === null || in_array($num, $allowed, true);
    }
}
