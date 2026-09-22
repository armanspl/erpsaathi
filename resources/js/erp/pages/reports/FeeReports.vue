<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fee Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Reports', 'Fee Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <p v-if="!report.session" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                No academic session is available — set one in Settings to see fee figures.
            </p>
            <template v-else>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Total Fee" :value="`₹${report.total_fee.toLocaleString('en-IN')}`" color="indigo" icon="🧾" />
                    <StatCard label="Collected" :value="`₹${report.total_paid.toLocaleString('en-IN')}`" color="emerald" icon="✅" />
                    <StatCard label="Due" :value="`₹${report.total_due.toLocaleString('en-IN')}`" color="rose" icon="⏳" />
                    <StatCard label="Collection Rate" :value="`${report.collection_rate}%`" color="sky" icon="📈" />
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <StatCard label="Discount Given" :value="`₹${report.total_discount.toLocaleString('en-IN')}`" color="amber" icon="🏷️" />
                    <StatCard label="Defaulters" :value="report.defaulters_count" color="rose" icon="⚠️" />
                    <StatCard label="Collected This Month" :value="`₹${report.collected_this_month.toLocaleString('en-IN')}`" color="violet" icon="📅" />
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Payment Mode Breakdown</div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('mode')">Mode {{ sortArrow('mode') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('amount')">Amount {{ sortArrow('amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!modeRows.length">
                                <td class="px-4 py-6 text-center text-slate-400">No payments recorded yet.</td>
                            </tr>
                            <tr v-for="row in modeRows" :key="row.mode" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ row.mode }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">₹{{ Number(row.amount).toLocaleString('en-IN') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { erpStore } from '../../store';

const report = ref(null);

async function load() {
    const { data } = await client.get('/reports/fees');
    report.value = data;
}
load();
watch(() => erpStore.currentSession, load);

const sortKey = ref('mode');
const sortDir = ref('asc');
function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}
const modeRows = computed(() => {
    const rows = Object.entries(report.value?.payment_mode_breakdown || {}).map(([mode, amount]) => ({ mode, amount }));
    return rows.sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});
</script>
