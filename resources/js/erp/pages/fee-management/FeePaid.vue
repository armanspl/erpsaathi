<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Paid</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Students who have cleared fees till the current month, or paid in advance for future months.
                </p>
            </div>
            <Dropdown align="right">
                <template #trigger>
                    <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting || !rows.length">
                        {{ exporting ? 'Exporting…' : 'Export ▾' }}
                    </button>
                </template>
                <template #panel="{ close }">
                    <div class="w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('xlsx'); close()">📊 Excel (.xlsx)</button>
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('csv'); close()">📄 CSV</button>
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('pdf'); close()">📕 PDF</button>
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
                    <select v-model="filters.school_class_id" class="form-input">
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
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission (auto)" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">View</p>
            <p class="mt-0.5 text-xs text-slate-400">Choose which paid students to list.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="view === 'till_current' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    @click="view = 'till_current'"
                >
                    Till current month
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="view === 'advance' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    @click="view = 'advance'"
                >
                    Advance months
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ summary.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ view === 'advance' ? 'Advance charge' : 'Charged' }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.charged) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ view === 'advance' ? 'Advance paid' : 'Paid' }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ money(view === 'advance' ? summary.advance_paid : summary.paid) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Concession</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.concession) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                        {{ view === 'advance' ? 'Advance Fee Paid Students' : 'Fee Paid Till Current Month' }}
                    </h2>
                    <p class="text-xs text-slate-400">
                        {{ view === 'advance'
                            ? 'Students with payments applied to months after the current month.'
                            : 'Students with zero due for all months up to the current month (same math as Fee Due).' }}
                    </p>
                </div>
                <span class="text-xs text-slate-400">{{ rows.length }} student(s)</span>
            </div>
            <div v-if="loading" class="px-4 py-16 text-center text-sm text-slate-400">Loading…</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Admission ID</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Student</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Roll No</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Father</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Mother</th>
                            <template v-if="view === 'advance'">
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Advance months</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Advance paid</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Paid through</th>
                            </template>
                            <template v-else>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Charge</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Paid</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Concession</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Paid through</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!rows.length">
                            <td :colspan="view === 'advance' ? 8 : 9" class="px-4 py-12 text-center text-slate-400">
                                No fee-paid students match these filters.
                            </td>
                        </tr>
                        <tr v-for="r in pagedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                            <td class="px-3 py-3">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                                <p class="text-xs text-slate-400">{{ [r.branch, r.school_class, r.section].filter(Boolean).join(' · ') }}</p>
                                <p v-if="r.scope_label" class="text-[11px] text-slate-400">{{ r.scope_label }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-500">{{ r.roll_no || '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ r.father || '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ r.mother || '—' }}</td>
                            <template v-if="view === 'advance'">
                                <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ r.advance_months_label || '—' }}</td>
                                <td class="px-3 py-3 font-semibold text-emerald-600">₹{{ money(r.advance_paid) }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ r.paid_through_label || '—' }}</td>
                            </template>
                            <template v-else>
                                <td class="px-3 py-3 text-slate-600">₹{{ money(r.total_fee) }}</td>
                                <td class="px-3 py-3 font-semibold text-emerald-600">₹{{ money(r.total_paid) }}</td>
                                <td class="px-3 py-3 text-slate-600">₹{{ money(r.total_discount) }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ r.paid_through_label || '—' }}</td>
                            </template>
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
const view = ref('till_current');
const summary = reactive({ students: 0, charged: 0, paid: 0, concession: 0, advance_paid: 0 });

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);

const filters = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
});

const perPage = 25;
const page = ref(1);
const filterSections = computed(() =>
    sections.value.filter((s) => !filters.school_class_id || s.school_class_id === filters.school_class_id),
);
const pagedRows = computed(() => rows.value.slice((page.value - 1) * perPage, page.value * perPage));

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function currentParams() {
    const p = { view: view.value };
    if (filters.branch_id) p.branch_id = filters.branch_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

async function reload() {
    loading.value = true;
    page.value = 1;
    try {
        const { data } = await client.get('/fee-management/fee-paid', { params: currentParams() });
        rows.value = data.rows || [];
        Object.assign(summary, data.summary || {});
    } catch {
        rows.value = [];
        pushToast('Could not load fee paid list.', 'error');
    } finally {
        loading.value = false;
    }
}

async function runExport(format) {
    exporting.value = true;
    try {
        const response = await client.get('/fee-management/fee-paid/export', {
            params: { ...currentParams(), format },
            responseType: 'blob',
        });
        const ext = format === 'xlsx' ? 'xlsx' : format;
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `fee-paid-${view.value}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
        pushToast('Export ready.', 'success');
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

watch(() => filters.school_class_id, () => {
    filters.section_id = null;
});

let loadTimer;
watch([filters, view], () => {
    clearTimeout(loadTimer);
    loadTimer = setTimeout(reload, 250);
}, { deep: true });

onMounted(async () => {
    const data = await fetchAcademicsLookups();
    branches.value = data.branches || [];
    classes.value = data.classes || [];
    sections.value = data.sections || [];
    await reload();
});
</script>
