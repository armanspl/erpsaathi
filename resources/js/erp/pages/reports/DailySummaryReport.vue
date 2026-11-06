<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Daily Summary</h1>
                <Breadcrumb :items="['Dashboard', 'Reports', 'Daily Summary']" class="mt-1" />
            </div>
            <Dropdown align="right">
                <template #trigger>
                    <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting || !days.length">
                        {{ exporting ? 'Exporting…' : 'Export ▾' }}
                    </button>
                </template>
                <template #panel="{ close }">
                    <div class="w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <p class="px-3 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ formatDate(selectedDate) }}</p>
                        <button v-for="f in FORMATS" :key="`d-${f.id}`" type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportReport(f.id, true); close()">{{ f.label }}</button>
                        <p class="border-t border-slate-100 px-3 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:border-slate-700">All dates</p>
                        <button v-for="f in FORMATS" :key="`a-${f.id}`" type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportReport(f.id, false); close()">{{ f.label }}</button>
                    </div>
                </template>
            </Dropdown>
        </div>

        <!-- Date -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="selectedDate" type="date" class="form-input" :max="todayKey" />
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        class="!py-2 !text-sm"
                        :class="showAll ? 'btn-primary' : 'btn-outline'"
                        :aria-pressed="showAll"
                        @click="showAll = !showAll"
                    >
                        {{ showAll ? 'Hide all dates' : 'All dates' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading…</div>

        <template v-else>
            <!-- Picked date -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase text-slate-400">Cash</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">₹{{ money(day.cash) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase text-slate-400">Bank</p>
                    <p class="mt-1 text-xl font-bold text-sky-700 dark:text-sky-300">₹{{ money(day.bank) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase text-slate-400">Total income</p>
                    <p class="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(day.income) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase text-slate-400">Receipts</p>
                    <p class="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100">{{ dayEntries.length }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ formatDate(selectedDate) }} <span class="ml-1 text-xs font-normal text-slate-400">{{ weekday(selectedDate) }}</span></h2>
                    <span class="text-xs text-slate-400">{{ dayEntries.length }} receipt(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold uppercase">Adm No</th>
                                <th class="px-3 py-2.5 font-semibold uppercase">Name</th>
                                <th class="px-3 py-2.5 font-semibold uppercase">Class</th>
                                <th class="px-3 py-2.5 font-semibold uppercase">Receipt</th>
                                <th class="px-3 py-2.5 font-semibold uppercase">Remarks</th>
                                <th class="px-3 py-2.5 font-semibold uppercase">Mode</th>
                                <th class="px-3 py-2.5 text-right font-semibold uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!dayEntries.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">No income on {{ formatDate(selectedDate) }}.</td></tr>
                            <tr v-for="(e, i) in dayEntries" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ e.admission_no || '—' }}</td>
                                <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">{{ e.name || 'Misc income' }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ e.class || '—' }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ e.receipt || '—' }}</td>
                                <td class="max-w-[220px] truncate px-3 py-2 text-slate-500" :title="e.remarks">{{ e.remarks || '—' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="e.is_cash ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'">{{ e.mode || 'Cash' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-semibold text-slate-800 dark:text-slate-100">₹{{ money(e.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="dayEntries.length" class="border-t-2 border-slate-200 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-800/50">
                            <tr>
                                <td colspan="6" class="px-3 py-2.5 text-slate-700 dark:text-slate-200">Total</td>
                                <td class="px-3 py-2.5 text-right text-slate-800 dark:text-slate-100">₹{{ money(day.income) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- All dates (shown with the "All dates" button) -->
            <div v-if="showAll" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">All dates</h2>
                    <span class="text-xs text-slate-400">{{ days.length }} day(s) with income · click a date to open it</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-3 py-3 text-xs font-semibold uppercase text-slate-500" @click="toggleSort('date')">Date {{ sortArrow('date') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500" @click="toggleSort('cash')">Cash {{ sortArrow('cash') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500" @click="toggleSort('bank')">Bank {{ sortArrow('bank') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500" @click="toggleSort('income')">Income {{ sortArrow('income') }}</th>
                                <th class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Receipts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!days.length"><td colspan="5" class="px-4 py-12 text-center text-slate-400">No income recorded yet.</td></tr>
                            <tr
                                v-for="d in pagedDays"
                                :key="d.date"
                                class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40"
                                :class="d.date === selectedDate ? 'bg-primary-50/60 dark:bg-primary-500/10' : ''"
                                @click="openDate(d.date)"
                            >
                                <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">
                                    {{ formatDate(d.date) }}
                                    <span class="ml-1 text-xs font-normal text-slate-400">{{ weekday(d.date) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right text-emerald-700 dark:text-emerald-300">₹{{ money(d.cash) }}</td>
                                <td class="px-3 py-2.5 text-right text-sky-700 dark:text-sky-300">₹{{ money(d.bank) }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-slate-800 dark:text-slate-100">₹{{ money(d.income) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-500">{{ d.entries }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="days.length" class="border-t-2 border-slate-200 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-800/50">
                            <tr>
                                <td class="px-3 py-2.5 text-slate-700 dark:text-slate-200">Total</td>
                                <td class="px-3 py-2.5 text-right text-emerald-700 dark:text-emerald-300">₹{{ money(totals.cash) }}</td>
                                <td class="px-3 py-2.5 text-right text-sky-700 dark:text-sky-300">₹{{ money(totals.bank) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-800 dark:text-slate-100">₹{{ money(totals.income) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-500">{{ totalEntries }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row dark:border-slate-800">
                    <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        Show
                        <select v-model="perPage" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800">
                            <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt === 'all' ? 'All' : opt }}</option>
                        </select>
                        per page
                    </label>
                    <Pagination v-if="perPage !== 'all' && days.length > perPage" v-model="page" :per-page="perPage" :total="days.length" class="!border-t-0 !p-0" />
                    <p v-else-if="perPage === 'all'" class="text-xs text-slate-500 dark:text-slate-400">Showing all {{ days.length }} days</p>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import Dropdown from '../../components/common/Dropdown.vue';
import Pagination from '../../components/common/Pagination.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const FORMATS = [
    { id: 'pdf', label: '📕 PDF' },
    { id: 'xlsx', label: '📊 Excel (.xlsx)' },
    { id: 'csv', label: '📄 CSV' },
];

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const todayKey = ymd(new Date());

const loading = ref(false);
const exporting = ref(false);
// Every day with income (one request) — the picked date's figures and receipts come from it.
const days = ref([]);
const entries = ref({});
const totals = reactive({ cash: 0, bank: 0, income: 0 });
const selectedDate = ref(todayKey);
const showAll = ref(false);

const day = computed(() => days.value.find((d) => d.date === selectedDate.value) || { cash: 0, bank: 0, income: 0, entries: 0 });
const dayEntries = computed(() => entries.value[selectedDate.value] || []);

const perPageOptions = [10, 25, 50, 100, 'all'];
const perPage = ref(25);
const page = ref(1);

const sortKey = ref('date');
const sortDir = ref('desc');
function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}
const sortedDays = computed(() => [...days.value].sort((a, b) => {
    const av = a[sortKey.value];
    const bv = b[sortKey.value];
    if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
    if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
    return 0;
}));
const pagedDays = computed(() => (perPage.value === 'all'
    ? sortedDays.value
    : sortedDays.value.slice((page.value - 1) * perPage.value, page.value * perPage.value)));
const totalEntries = computed(() => days.value.reduce((s, d) => s + (d.entries || 0), 0));

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}
function formatDate(d) {
    if (!d) return '';
    const [y, m, dd] = d.split('-');
    return `${dd}-${m}-${y}`;
}
function weekday(d) {
    return d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-IN', { weekday: 'short' }) : '';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/reports/daily-summary');
        days.value = data.days || [];
        entries.value = data.entries || {};
        Object.assign(totals, data.totals || { cash: 0, bank: 0, income: 0 });
        page.value = 1;
    } catch {
        pushToast('Could not load the daily summary.', 'error');
    } finally {
        loading.value = false;
    }
}

async function exportReport(format, onlyDate) {
    exporting.value = true;
    try {
        const params = { format, ...(onlyDate ? { from: selectedDate.value, to: selectedDate.value } : {}) };
        const response = await client.get('/reports/daily-summary', { params, responseType: 'blob' });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = match ? match[1] : `daily-summary.${format}`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

// A date clicked in "All dates" opens at the top.
function openDate(d) {
    selectedDate.value = d;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

watch(selectedDate, (d) => {
    if (!d) selectedDate.value = todayKey;
});
watch(perPage, () => { page.value = 1; });
load();
</script>
