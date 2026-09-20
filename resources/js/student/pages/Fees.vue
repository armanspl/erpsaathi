<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Fees</h1>

        <div class="flex flex-wrap gap-1.5 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in visibleTabs"
                :key="t.key"
                type="button"
                class="rounded-t-lg px-3.5 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Summary -->
        <template v-if="tab === 'summary'">
            <div v-if="summary" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Charge</p>
                        <p class="mt-1 text-xl font-semibold text-slate-800 dark:text-slate-100">₹{{ fmt(summary.summary?.total_charge) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Concession</p>
                        <p class="mt-1 text-xl font-semibold text-slate-800 dark:text-slate-100">₹{{ fmt(summary.summary?.concession) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Paid</p>
                        <p class="mt-1 text-xl font-semibold text-emerald-600">₹{{ fmt(summary.summary?.paid) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Due</p>
                        <p class="mt-1 text-xl font-semibold text-rose-600">₹{{ fmt(summary.summary?.due) }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr><th class="px-4 py-3">Category</th><th class="px-4 py-3 text-right">Charge</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Due</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(c, idx) in summary.categories" :key="c.category || c.label || idx">
                                <td class="px-4 py-2.5">{{ c.category || c.label }}</td>
                                <td class="px-4 py-2.5 text-right">₹{{ fmt(c.charge) }}</td>
                                <td class="px-4 py-2.5 text-right">₹{{ fmt(c.paid) }}</td>
                                <td class="px-4 py-2.5 text-right">₹{{ fmt(c.due) }}</td>
                            </tr>
                            <tr v-if="!summary.categories?.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">No fee structure set for this session.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p v-else class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No active academic session.</p>
        </template>

        <!-- Pending -->
        <div v-else-if="tab === 'pending'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr><th class="px-4 py-3">Month</th><th class="px-4 py-3 text-right">Charge</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Due</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="(m, idx) in pending" :key="m.month || idx">
                        <td class="px-4 py-2.5">{{ m.month_label || m.month }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ fmt(m.charge) }}</td>
                        <td class="px-4 py-2.5 text-right">₹{{ fmt(m.paid) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-rose-600">₹{{ fmt(m.due) }}</td>
                    </tr>
                    <tr v-if="!pending.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">No pending dues — you're all caught up 🎉</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Paid / History / Receipts share one table shape -->
        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Receipt No.</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Mode</th>
                        <th class="px-4 py-3">Fee Heads</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="r in currentPaymentRows" :key="r.id">
                        <td class="px-4 py-2.5">{{ r.receipt_no }}</td>
                        <td class="px-4 py-2.5">{{ r.payment_date }}</td>
                        <td class="px-4 py-2.5">{{ r.payment_mode }}</td>
                        <td class="px-4 py-2.5">{{ r.fee_heads || '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-medium">₹{{ fmt(r.amount) }}</td>
                        <td class="px-4 py-2.5">{{ r.status || 'Paid' }}</td>
                        <td class="px-4 py-2.5">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="downloadReceipt(r)">Download</button>
                        </td>
                    </tr>
                    <tr v-if="!currentPaymentRows.length"><td colspan="7" class="px-4 py-6 text-center text-slate-400">{{ emptyLabel }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import client from '../api/client';
import { isModuleVisible } from '../store';
import { downloadFile } from '../utils/download';

const ALL_TABS = [
    { key: 'summary', label: 'Fee Summary', moduleKey: 'fee_summary' },
    { key: 'paid', label: 'Paid Fees', moduleKey: 'paid_fees' },
    { key: 'pending', label: 'Pending Fees', moduleKey: 'pending_fees' },
    { key: 'history', label: 'Fee History', moduleKey: 'fee_history' },
    { key: 'receipts', label: 'Receipts', moduleKey: 'receipts' },
];
const visibleTabs = ALL_TABS.filter((t) => isModuleVisible(t.moduleKey));
const tab = ref(visibleTabs[0]?.key || 'summary');

const summary = ref(null);
const paid = ref([]);
const pending = ref([]);
const history = ref([]);
const loaded = { summary: false, paid: false, pending: false, history: false };

const currentPaymentRows = computed(() => (tab.value === 'paid' ? paid.value : history.value));
const emptyLabel = computed(() => ({ paid: 'No payments recorded yet.', history: 'No fee history yet.', receipts: 'No receipts yet.' }[tab.value] || ''));

function fmt(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function downloadReceipt(row) {
    downloadFile(`/fees/receipt/${row.id}`, `${row.receipt_no || 'receipt'}.pdf`);
}

async function loadTab(key) {
    if (loaded[key]) return;
    if (key === 'summary') {
        const { data } = await client.get('/fees/summary');
        summary.value = data.session ? data : null;
        loaded.summary = true;
    } else if (key === 'paid') {
        const { data } = await client.get('/fees/paid');
        paid.value = data;
        loaded.paid = true;
    } else if (key === 'pending') {
        const { data } = await client.get('/fees/pending');
        pending.value = data.months || [];
        loaded.pending = true;
    } else if (key === 'history' || key === 'receipts') {
        if (loaded.history) return;
        const { data } = await client.get('/fees/history');
        history.value = data;
        loaded.history = true;
    }
}

watch(tab, (key) => loadTab(key), { immediate: true });
</script>
