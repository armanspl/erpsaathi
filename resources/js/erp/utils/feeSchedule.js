/**
 * Which of the selected months a monthly / quarterly fee line is charged in — mirrors
 * App\Support\FeeSchedule. A line with its own "Apply in months" (Fee Structure, e.g.
 * Examination Fee in Sep + Mar) is charged only in those months; otherwise monthly lines in
 * every month and quarterly lines in Apr / Jul / Oct / Jan (or the first selected month).
 *
 * @param {{ frequency?: string, months?: number[] }} item  fee breakdown row
 * @param {string[]} monthKeys  'YYYY-MM' keys
 * @returns {string[]}
 */
export function chargeMonthsFor(item, monthKeys) {
    const freq = String(item?.frequency || 'monthly').toLowerCase().replace(' ', '_');
    const chosen = Array.isArray(item?.months) ? item.months.map(Number).filter((n) => n >= 1 && n <= 12) : [];
    const monthNum = (key) => Number(String(key).slice(5, 7));

    if (chosen.length) return monthKeys.filter((k) => chosen.includes(monthNum(k)));
    if (freq === 'quarterly') {
        const q = monthKeys.filter((k) => [4, 7, 10, 1].includes(monthNum(k)));
        return q.length ? q : monthKeys.slice(0, 1);
    }
    return monthKeys;
}

export const MONTH_OPTIONS = [
    { value: 4, label: 'Apr' }, { value: 5, label: 'May' }, { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' }, { value: 8, label: 'Aug' }, { value: 9, label: 'Sep' },
    { value: 10, label: 'Oct' }, { value: 11, label: 'Nov' }, { value: 12, label: 'Dec' },
    { value: 1, label: 'Jan' }, { value: 2, label: 'Feb' }, { value: 3, label: 'Mar' },
];

/** "Sep, Mar" in session order (Apr → Mar). */
export function monthsLabel(months) {
    const set = new Set((months || []).map(Number));
    return MONTH_OPTIONS.filter((m) => set.has(m.value)).map((m) => m.label).join(', ');
}
