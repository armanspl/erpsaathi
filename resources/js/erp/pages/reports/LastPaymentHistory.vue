<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Last Payment History</h1>
                <Breadcrumb :items="['Dashboard', 'Reports', 'Last Payment History']" class="mt-1" />
                <p v-if="sessionName" class="mt-1 text-sm text-slate-500">Session {{ sessionName }} · due is up to the current month</p>
            </div>
            <button type="button" class="btn-primary" :disabled="exporting || !rows.length" @click="exportOpen = true">
                {{ exporting ? 'Exporting…' : 'Export' }}
            </button>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
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
                    <label class="form-label">Payment</label>
                    <select v-model="filters.has_payment" class="form-input">
                        <option value="all">All students</option>
                        <option value="yes">With last payment</option>
                        <option value="no">Never paid</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Dues</label>
                    <select v-model="filters.dues" class="form-input">
                        <option value="all">All</option>
                        <option value="due">Only with dues</option>
                        <option value="clear">Only clear</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name, adm no, father, mobile" />
                </div>
            </div>

            <details class="mt-4 rounded-xl border border-slate-200 p-3 dark:border-slate-700" :open="columnsOpen" @toggle="columnsOpen = $event.target.open">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Columns to show &amp; export
                    <span class="font-normal text-slate-400">({{ selectedKeys.length }} of {{ columns.length }})</span>
                </summary>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = columns.map((c) => c.key)">Select all</button>
                    <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = [...DEFAULT_KEYS]">Default</button>
                    <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = []">Clear</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
                    <label v-for="c in columns" :key="c.key" class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="selectedKeys" type="checkbox" :value="c.key" class="rounded border-slate-300" />
                        {{ c.label }}
                    </label>
                </div>
            </details>
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-400">Students</p>
                <p class="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100">{{ totals.students }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-400">With payment</p>
                <p class="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100">{{ totals.with_payment }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-400">Last paid total</p>
                <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">₹{{ money(totals.last_paid_amount) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-400">Total due</p>
                <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300">₹{{ money(totals.due) }}</p>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading…</div>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th
                                v-for="c in visibleColumns"
                                :key="c.key"
                                class="cursor-pointer whitespace-nowrap px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
                                :class="isAmount(c.key) ? 'text-right' : ''"
                                @click="toggleSort(c.key)"
                            >
                                {{ c.label }} {{ sortArrow(c.key) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!sortedRows.length">
                            <td :colspan="Math.max(visibleColumns.length, 1)" class="px-4 py-12 text-center text-slate-400">No students match these filters.</td>
                        </tr>
                        <tr v-for="r in pagedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td
                                v-for="c in visibleColumns"
                                :key="c.key"
                                class="px-3 py-2.5"
                                :class="cellClass(c.key, r)"
                            >
                                {{ formatCell(c.key, r) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-4 py-2 sm:flex-row dark:border-slate-800">
                <label class="flex items-center gap-2 text-xs text-slate-500">
                    Show
                    <select v-model="perPageSelection" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800">
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt === 'all' ? 'All' : opt }}</option>
                    </select>
                    per page
                </label>
                <Pagination v-if="perPageSelection !== 'all'" v-model="page" :per-page="perPage" :total="sortedRows.length" class="!border-t-0 !p-0" />
                <p v-else class="text-xs text-slate-500">Showing all {{ sortedRows.length }} entries</p>
            </div>
        </div>

        <!-- Export modal: pick columns + format -->
        <div v-if="exportOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="exportOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Export Last Payment History</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Choose columns and format. PDF, Excel, and CSV are available.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="exportOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="form-label">Format</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="f in FORMATS"
                                :key="f.id"
                                type="button"
                                class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                                :class="exportForm.format === f.id ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="exportForm.format = f.id"
                            >{{ f.label }}</button>
                        </div>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="form-label !mb-0">Columns <span class="font-normal text-slate-400">({{ exportForm.columns.length }} of {{ columns.length }})</span></p>
                            <div class="flex gap-2">
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="exportForm.columns = columns.map((c) => c.key)">Select all</button>
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="exportForm.columns = [...selectedKeys]">Match table</button>
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="exportForm.columns = []">Clear</button>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1.5">
                            <label v-for="c in columns" :key="c.key" class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="exportForm.columns" type="checkbox" :value="c.key" class="rounded border-slate-300" />
                                {{ c.label }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <p class="text-xs text-rose-600">{{ !exportForm.columns.length ? 'Pick at least one column.' : (!rows.length ? 'No rows to export.' : '') }}</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" @click="exportOpen = false">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="exporting || !exportForm.columns.length || !rows.length" @click="confirmExport">
                            {{ exporting ? 'Exporting…' : `Download ${exportForm.format.toUpperCase()}` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import Pagination from '../../components/common/Pagination.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const FORMATS = [
    { id: 'pdf', label: '📕 PDF' },
    { id: 'xlsx', label: '📊 Excel (.xlsx)' },
    { id: 'csv', label: '📄 CSV' },
];

const DEFAULT_KEYS = [
    'admission_no', 'name', 'class', 'address', 'last_paid_month', 'last_paid_date', 'last_paid_amount', 'paid_for', 'due',
];

const columns = ref([]);
const selectedKeys = ref([...DEFAULT_KEYS]);
const columnsOpen = ref(false);
const rows = ref([]);
const totals = reactive({ students: 0, with_payment: 0, last_paid_amount: 0, due: 0 });
const sessionName = ref('');
const loading = ref(false);
const exporting = ref(false);
const exportOpen = ref(false);
const exportForm = reactive({ format: 'pdf', columns: [...DEFAULT_KEYS] });

const classes = ref([]);
const sections = ref([]);
const filters = reactive({
    school_class_id: null,
    section_id: null,
    has_payment: 'all',
    dues: 'all',
    search: '',
});

const sortKey = ref('name');
const sortDir = ref('asc');
const page = ref(1);
const perPageOptions = [25, 50, 100, 'all'];
const perPageSelection = ref(50);
const perPage = computed(() => (perPageSelection.value === 'all' ? Math.max(sortedRows.value.length, 1) : perPageSelection.value));

const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
const visibleColumns = computed(() => {
    const byKey = Object.fromEntries(columns.value.map((c) => [c.key, c]));
    const picked = selectedKeys.value.map((k) => byKey[k]).filter(Boolean);
    return picked.length ? picked : columns.value;
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function isAmount(key) {
    return key === 'last_paid_amount' || key === 'due';
}

function formatCell(key, row) {
    const v = row[key];
    if (key === 'last_paid_date') {
        if (!v) return '—';
        const [y, m, d] = String(v).split('-');
        return d && m && y ? `${d}-${m}-${y}` : v;
    }
    if (isAmount(key)) {
        if (v === null || v === undefined || v === '') return '—';
        return `₹${money(v)}`;
    }
    return v || '—';
}

function cellClass(key, row) {
    if (key === 'name') return 'font-medium text-slate-800 dark:text-slate-100';
    if (key === 'admission_no') return 'font-mono text-xs text-slate-500';
    if (key === 'paid_for') return 'max-w-[280px] text-slate-600 dark:text-slate-300';
    if (key === 'last_paid_amount') return 'text-right font-semibold text-emerald-700 dark:text-emerald-300';
    if (key === 'due') return `text-right font-semibold ${Number(row.due) > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-400'}`;
    if (isAmount(key)) return 'text-right';
    return 'text-slate-500';
}

function toggleSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    page.value = 1;
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

const sortedRows = computed(() => {
    const list = [...rows.value];
    const key = sortKey.value;
    const dir = sortDir.value;
    list.sort((a, b) => {
        let av = a[key];
        let bv = b[key];
        if (isAmount(key)) {
            av = Number(av ?? -1);
            bv = Number(bv ?? -1);
        } else {
            av = String(av ?? '').toLowerCase();
            bv = String(bv ?? '').toLowerCase();
        }
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });
    return list;
});

const pagedRows = computed(() => {
    if (perPageSelection.value === 'all') return sortedRows.value;
    const start = (page.value - 1) * perPage.value;
    return sortedRows.value.slice(start, start + perPage.value);
});

function currentParams() {
    const p = {};
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.has_payment !== 'all') p.has_payment = filters.has_payment;
    if (filters.dues !== 'all') p.dues = filters.dues;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

async function reload() {
    loading.value = true;
    page.value = 1;
    try {
        const { data } = await client.get('/reports/last-payment-history', { params: currentParams() });
        columns.value = data.columns || [];
        rows.value = data.rows || [];
        Object.assign(totals, data.totals || { students: 0, with_payment: 0, last_paid_amount: 0, due: 0 });
        sessionName.value = data.session?.name || '';
        const known = columns.value.map((c) => c.key);
        selectedKeys.value = (selectedKeys.value.length ? selectedKeys.value : DEFAULT_KEYS).filter((k) => known.includes(k));
        if (!selectedKeys.value.length) selectedKeys.value = [...DEFAULT_KEYS].filter((k) => known.includes(k));
    } catch {
        pushToast('Could not load last payment history.', 'error');
    } finally {
        loading.value = false;
    }
}

async function confirmExport() {
    if (!exportForm.columns.length || !rows.value.length) return;
    exporting.value = true;
    try {
        const response = await client.get('/reports/last-payment-history', {
            params: {
                ...currentParams(),
                format: exportForm.format,
                columns: exportForm.columns,
            },
            responseType: 'blob',
            paramsSerializer: {
                serialize: (p) => {
                    const parts = [];
                    Object.entries(p).forEach(([key, val]) => {
                        if (Array.isArray(val)) {
                            val.forEach((v) => parts.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(v)}`));
                        } else if (val !== undefined && val !== null && val !== '') {
                            parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(val)}`);
                        }
                    });
                    return parts.join('&');
                },
            },
        });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : `last-payment-history.${exportForm.format}`;
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        exportOpen.value = false;
        pushToast(`Downloaded ${exportForm.format.toUpperCase()}.`, 'success');
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

watch(exportOpen, (open) => {
    if (open) exportForm.columns = selectedKeys.value.length ? [...selectedKeys.value] : columns.value.map((c) => c.key);
});

let reloadTimer = null;
watch(filters, () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(reload, 250);
}, { deep: true });
watch(() => erpStore.currentSession, reload);
watch(perPageSelection, () => { page.value = 1; });

onMounted(async () => {
    const lookups = await fetchAcademicsLookups();
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    await reload();
});
</script>
