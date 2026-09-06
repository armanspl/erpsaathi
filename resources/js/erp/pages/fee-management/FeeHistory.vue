<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee History</h1>
                <p class="mt-1 text-sm text-slate-500">Fee head &amp; month wise totals, received, ledger balance and net.</p>
            </div>
            <Dropdown align="right">
                <template #trigger>
                    <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting || !rows.length">
                        {{ exporting ? 'Exporting…' : 'Export ▾' }}
                    </button>
                </template>
                <template #panel="{ close }">
                    <div class="w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sort export by</p>
                        <select v-model="exportSort" class="form-input mb-3 !text-xs">
                            <option v-for="o in sortOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                        </select>
                        <div class="flex flex-col gap-1">
                            <button type="button" class="rounded-lg px-2.5 py-1.5 text-left text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('xlsx'); close()">📊 Excel (.xlsx)</button>
                            <button type="button" class="rounded-lg px-2.5 py-1.5 text-left text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('csv'); close()">📄 CSV</button>
                            <button type="button" class="rounded-lg px-2.5 py-1.5 text-left text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('pdf'); close()">📕 PDF</button>
                        </div>
                    </div>
                </template>
            </Dropdown>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
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
                    <label class="form-label">Zero balance</label>
                    <select v-model="filters.zero_balance" class="form-input">
                        <option value="all">All students</option>
                        <option value="only_zero">Only zero balance</option>
                        <option value="exclude_zero">Exclude zero balance</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="form-label !mb-0">Fee heads</label>
                        <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="toggleAllHeads">
                            {{ allHeadsSelected ? 'Deselect all' : 'Select all' }}
                        </button>
                    </div>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                        <label v-for="h in feeHeads" :key="h.id" class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <input v-model="filters.fee_head_ids" type="checkbox" :value="h.id" class="rounded border-slate-300 text-primary-600" />
                            <span>{{ h.name }}</span>
                        </label>
                        <p v-if="!feeHeads.length" class="px-1 py-2 text-xs text-slate-400">No fee heads found.</p>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">{{ filters.fee_head_ids.length ? `${filters.fee_head_ids.length} selected` : 'None = all heads' }}</p>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="form-label !mb-0">Months</label>
                        <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="toggleAllMonths">
                            {{ allMonthsSelected ? 'Deselect all' : 'Select all' }}
                        </button>
                    </div>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                        <label v-for="m in months" :key="m.key" class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <input v-model="filters.months" type="checkbox" :value="m.key" class="rounded border-slate-300 text-primary-600" />
                            <span>{{ m.label || m.key }}</span>
                        </label>
                        <p v-if="!months.length" class="px-1 py-2 text-xs text-slate-400">No session months.</p>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">{{ filters.months.length ? `${filters.months.length} selected` : 'None = all months' }}</p>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div class="min-w-[200px] flex-1">
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission / mobile" />
                </div>
                <div>
                    <label class="form-label">Sort list</label>
                    <select v-model="filters.sort" class="form-input">
                        <option v-for="o in sortOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ summary.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.total) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Received</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.received) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ledger Balance</p>
                <p class="mt-1 text-2xl font-bold text-teal-600 dark:text-teal-400">₹{{ money(summary.ledger_balance) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Net</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.net) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Students</h2>
                <span class="text-xs text-slate-400">{{ rows.length }} row(s) · zero balance {{ summary.zero_balance }}</span>
            </div>
            <div v-if="loading" class="px-4 py-16 text-center text-sm text-slate-400">Loading…</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Adm No</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Roll</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Name</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Class</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Section</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Address</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Mobile</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Received</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Ledger Bal.</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!rows.length">
                            <td colspan="11" class="px-4 py-12 text-center text-slate-400">No students match these filters.</td>
                        </tr>
                        <tr v-for="r in pagedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ r.roll_no || '—' }}</td>
                            <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ r.school_class || '—' }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ r.section || '—' }}</td>
                            <td class="max-w-[180px] truncate px-3 py-2.5 text-slate-500" :title="r.address || ''">{{ r.address || '—' }}</td>
                            <td class="px-3 py-2.5 text-slate-500">{{ r.mobile || '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-slate-700 dark:text-slate-200">₹{{ money(r.total) }}</td>
                            <td class="px-3 py-2.5 text-right text-slate-700 dark:text-slate-200">₹{{ money(r.received) }}</td>
                            <td class="px-3 py-2.5 text-right font-medium" :class="r.ledger_balance > 0 ? 'text-rose-600' : 'text-emerald-600'">₹{{ money(r.ledger_balance) }}</td>
                            <td class="px-3 py-2.5 text-right text-slate-700 dark:text-slate-200">₹{{ money(r.net) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination v-if="rows.length > perPage" v-model="page" :per-page="perPage" :total="rows.length" />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Dropdown from '../../components/common/Dropdown.vue';
import Pagination from '../../components/common/Pagination.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const exporting = ref(false);
const rows = ref([]);
const summary = reactive({ students: 0, total: 0, received: 0, ledger_balance: 0, net: 0, zero_balance: 0 });

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const feeHeads = ref([]);
const months = ref([]);
const sortOptions = ref([
    { key: 'class_section_roll_adm', label: 'Class > Section > Roll No > Adm No' },
    { key: 'class_section_name', label: 'Class > Section > Name' },
    { key: 'roll_adm_name', label: 'Roll No > Admission No > Name' },
    { key: 'admission_no', label: 'Admission No' },
    { key: 'name', label: 'Name' },
    { key: 'address', label: 'Address' },
    { key: 'mobile', label: 'Mobile' },
]);

const filters = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
    fee_head_ids: [],
    months: [],
    zero_balance: 'all',
    sort: 'class_section_roll_adm',
});
const exportSort = ref('class_section_roll_adm');

const perPage = 25;
const page = ref(1);
const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
const pagedRows = computed(() => rows.value.slice((page.value - 1) * perPage, page.value * perPage));
const allHeadsSelected = computed(() => feeHeads.value.length > 0 && filters.fee_head_ids.length === feeHeads.value.length);
const allMonthsSelected = computed(() => months.value.length > 0 && filters.months.length === months.value.length);

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function toggleAllHeads() {
    filters.fee_head_ids = allHeadsSelected.value ? [] : feeHeads.value.map((h) => h.id);
}
function toggleAllMonths() {
    filters.months = allMonthsSelected.value ? [] : months.value.map((m) => m.key);
}

function currentParams() {
    const p = { zero_balance: filters.zero_balance, sort: filters.sort };
    if (filters.branch_id) p.branch_id = filters.branch_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.search.trim()) p.search = filters.search.trim();
    if (filters.fee_head_ids.length) p.fee_head_ids = filters.fee_head_ids;
    if (filters.months.length) p.months = filters.months;
    return p;
}

async function reload() {
    loading.value = true;
    page.value = 1;
    try {
        const { data } = await client.get('/fee-management/fee-history', {
            params: currentParams(),
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
        rows.value = data.rows || [];
        Object.assign(summary, data.summary || {});
    } finally {
        loading.value = false;
    }
}

async function runExport(format) {
    exporting.value = true;
    try {
        const response = await client.get('/fee-management/fee-history/export', {
            params: { ...currentParams(), format, sort: exportSort.value },
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
        const filename = match ? match[1] : `fee-history.${format}`;
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

let reloadTimer = null;
function scheduleReload(delay = 250) {
    if (reloadTimer) clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        reload();
    }, delay);
}

watch(
    () => [filters.branch_id, filters.school_class_id, filters.section_id, filters.zero_balance, filters.sort, filters.fee_head_ids.slice(), filters.months.slice()],
    () => scheduleReload(0),
);
watch(() => filters.search, () => scheduleReload(300));

onMounted(async () => {
    const lookups = await fetchAcademicsLookups();
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    const { data } = await client.get('/fee-management/fee-history/meta');
    feeHeads.value = data.fee_heads || [];
    months.value = data.months || [];
    if (data.sort_options?.length) sortOptions.value = data.sort_options;
    filters.fee_head_ids = feeHeads.value.map((h) => h.id);
    filters.months = months.value.map((m) => m.key);
    await reload();
});
</script>
