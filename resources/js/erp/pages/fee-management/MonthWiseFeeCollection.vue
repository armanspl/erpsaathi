<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Month-wise Fee Collection</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    See cash collected by <strong class="font-medium text-slate-700 dark:text-slate-200">Payment Month</strong>
                    (when money was received) and which <strong class="font-medium text-slate-700 dark:text-slate-200">Fee Month(s)</strong>
                    each payment cleared.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline" :disabled="exporting || !rows.length" @click="runExport('csv')">
                    {{ exporting === 'csv' ? 'Exporting…' : 'CSV' }}
                </button>
                <button type="button" class="btn-primary" :disabled="exporting || !rows.length" @click="runExport('xlsx')">
                    {{ exporting === 'xlsx' ? 'Exporting…' : 'Export Excel' }}
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label class="form-label">Academic Year</label>
                    <select v-model="filters.academic_session_id" class="form-input">
                        <option :value="null">Header session</option>
                        <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Payment Month</label>
                    <select v-model="filters.payment_month" class="form-input">
                        <option value="">All months</option>
                        <option v-for="m in months" :key="m.key" :value="m.key">{{ m.label || m.key }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fee Month</label>
                    <select v-model="filters.fee_month" class="form-input">
                        <option value="">All fee months</option>
                        <option v-for="m in months" :key="'f-'+m.key" :value="m.key">{{ m.label || m.key }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="filters.school_class_id" class="form-input" @change="filters.section_id = null">
                        <option :value="null">All classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="filters.section_id" class="form-input" :disabled="!filters.school_class_id">
                        <option :value="null">{{ filters.school_class_id ? 'All sections' : 'Select class first' }}</option>
                        <option v-for="s in filterSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Payment Mode</label>
                    <select v-model="filters.payment_mode" class="form-input">
                        <option value="">All modes</option>
                        <option v-for="m in paymentModes" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Payment Date From</label>
                    <input v-model="filters.payment_from" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Payment Date To</label>
                    <input v-model="filters.payment_to" type="date" class="form-input" />
                </div>
                <div class="lg:col-span-2">
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Student name / admission no" />
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn-outline !py-1.5 !text-xs" @click="resetFilters">Reset</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ totals.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Receipts</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ totals.receipts }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Line items</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ totals.line_items }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Total collected</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">₹{{ money(totals.total_collected) }}</p>
            </div>
        </div>

        <!-- Month-wise summary -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Month-wise summary (by Payment Month)</h2>
                <p class="text-xs text-slate-400">How much was collected in each month, and how many students paid.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Month</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Students</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Receipts</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total Collected</th>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!summaries.length && !loading">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">No collections match your filters.</td>
                        </tr>
                        <tr
                            v-for="s in summaries"
                            :key="s.payment_month"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40"
                            :class="expandedMonth === s.payment_month && 'bg-primary-50/40 dark:bg-primary-500/5'"
                            @click="toggleMonth(s.payment_month)"
                        >
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.payment_month_label }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ s.students }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ s.receipts }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">₹{{ money(s.total_collected) }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-400">{{ expandedMonth === s.payment_month ? 'Hide ▲' : 'Students ▼' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detail for selected / all months -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ expandedMonth ? `Students who paid in ${monthLabel(expandedMonth)}` : 'Collection detail' }}
                </h2>
                <p class="text-xs text-slate-400">
                    Payment Month = when money was received · Fee Month = dues the payment cleared.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Adm No</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Date</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Month</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee Month</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee Head</th>
                            <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loading">
                            <td colspan="11" class="px-4 py-10 text-center text-slate-400">Loading…</td>
                        </tr>
                        <tr v-else-if="!visibleRows.length">
                            <td colspan="11" class="px-4 py-10 text-center text-slate-400">No rows to show.</td>
                        </tr>
                        <tr v-for="(r, idx) in pagedRows" :key="`${r.payment_id}-${r.fee_month}-${r.fee_head_name}-${idx}`" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ r.receipt_no }}</td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ r.admission_no }}</td>
                            <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ r.student_name }}</td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ r.school_class || '—' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ r.section || '—' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ formatDate(r.payment_date) }}</td>
                            <td class="px-3 py-2.5">
                                <span class="rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ r.payment_month_label }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ r.fee_month_label }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ r.fee_head_name }}</td>
                            <td class="px-3 py-2.5 text-right font-medium tabular-nums text-slate-800 dark:text-slate-100">₹{{ money(r.amount) }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ r.payment_mode }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="visibleRows.length > perPage" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                <span>Showing {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, visibleRows.length) }} of {{ visibleRows.length }}</span>
                <div class="flex gap-2">
                    <button type="button" class="btn-outline !py-1 !text-xs" :disabled="page <= 1" @click="page--">Prev</button>
                    <button type="button" class="btn-outline !py-1 !text-xs" :disabled="page * perPage >= visibleRows.length" @click="page++">Next</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const exporting = ref(null);
const classes = ref([]);
const sections = ref([]);
const months = ref([]);
const paymentModes = ref(['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque']);
const rows = ref([]);
const summaries = ref([]);
const totals = reactive({ students: 0, receipts: 0, line_items: 0, total_collected: 0 });
const expandedMonth = ref(null);
const page = ref(1);
const perPage = 40;

const sessions = computed(() => (erpStore.sessionRecords || []).filter((s) => s?.id && s?.name));

const filters = reactive({
    academic_session_id: null,
    payment_month: '',
    fee_month: '',
    school_class_id: null,
    section_id: null,
    payment_mode: '',
    payment_from: '',
    payment_to: '',
    search: '',
});

const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
const visibleRows = computed(() => {
    if (!expandedMonth.value) return rows.value;
    return rows.value.filter((r) => r.payment_month === expandedMonth.value);
});
const pagedRows = computed(() => visibleRows.value.slice((page.value - 1) * perPage, page.value * perPage));

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function monthLabel(ym) {
    const hit = months.value.find((m) => m.key === ym);
    if (hit?.label) return hit.label;
    const s = summaries.value.find((m) => m.payment_month === ym);
    return s?.payment_month_label || ym;
}

function toggleMonth(ym) {
    expandedMonth.value = expandedMonth.value === ym ? null : ym;
    page.value = 1;
}

function currentParams() {
    const p = {};
    if (filters.academic_session_id) p.academic_session_id = filters.academic_session_id;
    if (filters.payment_month) p.payment_month = filters.payment_month;
    if (filters.fee_month) p.fee_month = filters.fee_month;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.payment_mode) p.payment_mode = filters.payment_mode;
    if (filters.payment_from) p.payment_from = filters.payment_from;
    if (filters.payment_to) p.payment_to = filters.payment_to;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

function resetFilters() {
    Object.assign(filters, {
        academic_session_id: null,
        payment_month: '',
        fee_month: '',
        school_class_id: null,
        section_id: null,
        payment_mode: '',
        payment_from: '',
        payment_to: '',
        search: '',
    });
    expandedMonth.value = null;
    reload();
}

async function reload() {
    loading.value = true;
    page.value = 1;
    try {
        const { data } = await client.get('/fee-management/month-wise-collection', { params: currentParams() });
        rows.value = data.rows || [];
        summaries.value = data.summaries || [];
        Object.assign(totals, data.totals || { students: 0, receipts: 0, line_items: 0, total_collected: 0 });
        if (expandedMonth.value && !summaries.value.some((s) => s.payment_month === expandedMonth.value)) {
            expandedMonth.value = null;
        }
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not load report.', 'error');
    } finally {
        loading.value = false;
    }
}

async function runExport(format) {
    exporting.value = format;
    try {
        const response = await client.get('/fee-management/month-wise-collection/export', {
            params: { ...currentParams(), format },
            responseType: 'blob',
        });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : `month-wise-fee-collection.${format}`;
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
        pushToast('Export downloaded.', 'success');
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = null;
    }
}

watch(() => erpStore.currentSession, () => {
    if (!filters.academic_session_id) scheduleReload(0);
});

let reloadTimer = null;
let ready = false;
function scheduleReload(delay = 250) {
    if (!ready) return;
    if (reloadTimer) clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        reload();
    }, delay);
}

watch(
    () => [
        filters.academic_session_id,
        filters.payment_month,
        filters.fee_month,
        filters.school_class_id,
        filters.section_id,
        filters.payment_mode,
        filters.payment_from,
        filters.payment_to,
    ],
    () => scheduleReload(0),
);
watch(() => filters.search, () => scheduleReload(300));

onMounted(async () => {
    const lookups = await fetchAcademicsLookups();
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    const { data } = await client.get('/fee-management/month-wise-collection/meta');
    months.value = data.months || [];
    if (data.payment_modes?.length) paymentModes.value = data.payment_modes;
    await reload();
    ready = true;
});
</script>
