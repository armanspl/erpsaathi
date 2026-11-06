<template>
    <!-- Discount / Waiver breakdown under the student's Fees tab (from the fee due response). -->
    <div class="overflow-hidden rounded-xl border border-sky-200 bg-white dark:border-sky-900/50 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sky-100 bg-sky-50/60 px-4 py-2.5 dark:border-sky-900/40 dark:bg-sky-500/5">
            <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Discount / Waiver details</h4>
            <span class="text-sm font-bold text-sky-600 dark:text-sky-400">₹{{ money(due.total_discount) }}</span>
        </div>

        <p v-if="percent < 100" class="border-b border-slate-100 px-4 py-2 text-xs text-amber-700 dark:border-slate-800 dark:text-amber-300">
            Pays {{ percent }}% of the fee structure ({{ percentLabel }}). The fee amounts above are already reduced — that reduction is not counted in this discount.
        </p>

        <div v-if="groups.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-slate-500">
                    <tr class="border-b border-slate-100 dark:border-slate-800">
                        <th class="px-4 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Fee Head</th>
                        <th class="px-3 py-2 font-semibold">Month</th>
                        <th class="px-4 py-2 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="g in groups" :key="g.source">
                        <tr v-for="(r, i) in g.rows" :key="`${g.source}-${i}`" class="border-b border-slate-50 last:border-b-0 dark:border-slate-800/60">
                            <td class="px-4 py-2">
                                <span v-if="i === 0" class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="g.badge">{{ g.label }}</span>
                            </td>
                            <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ r.fee_head }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ monthLabel(r.month) }}</td>
                            <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-200">₹{{ money(r.amount) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100 bg-slate-50/60 text-xs dark:border-slate-800 dark:bg-slate-800/30">
                            <td colspan="3" class="px-4 py-1.5 text-slate-500">{{ g.label }} total</td>
                            <td class="px-4 py-1.5 text-right font-semibold text-slate-700 dark:text-slate-200">₹{{ money(g.total) }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p v-else class="px-4 py-4 text-sm text-slate-400">No discount or waiver up to this month in {{ due.session?.name || 'this session' }}.</p>

        <div v-if="records.length" class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Concessions on record</p>
            <ul class="space-y-1 text-sm">
                <li v-for="(d, i) in records" :key="i" class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-slate-700 dark:text-slate-200">
                        {{ d.fee_head }}
                        <span v-if="d.reason" class="text-xs text-slate-400">· {{ d.reason }}</span>
                    </span>
                    <span class="font-semibold text-slate-600 dark:text-slate-300">{{ d.type === 'percentage' ? `${money(d.value)}%` : `₹${money(d.value)}` }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ due: { type: Object, required: true } });

const SOURCES = {
    waiver: { label: 'Discount month (waiver)', badge: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' },
    concession: { label: 'Concession', badge: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' },
    receipt: { label: 'Discount on receipt', badge: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' },
};

const groups = computed(() => {
    const out = [];
    for (const [source, meta] of Object.entries(SOURCES)) {
        const rows = (props.due.discount_details || []).filter((d) => d.source === source);
        if (rows.length) out.push({ source, ...meta, rows, total: rows.reduce((s, r) => s + Number(r.amount || 0), 0) });
    }
    return out;
});
const records = computed(() => props.due.discount_records || []);
const percent = computed(() => Number(props.due.payment_percentage ?? 100));
const percentLabel = computed(() => (percent.value === 0 ? 'Full Free' : percent.value === 50 ? 'Half Free' : `${100 - percent.value}% off`));

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function monthLabel(key) {
    if (!key) return 'Annual / one-time';
    const [y, m] = String(key).split('-');
    return new Date(Number(y), Number(m) - 1, 1).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' });
}
</script>
