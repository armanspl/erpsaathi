<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Demand Slip</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Each student's fee paid and dues for the session — the same figures as the
                    <strong class="font-medium text-slate-700 dark:text-slate-200">Stud_Rec_Sum</strong> sheet of the Global Workbook export.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div ref="printWrap" class="relative">
                    <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!rows.length" @click="printMenu = !printMenu">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                        Print ▾
                    </button>
                    <div v-if="printMenu" class="absolute right-0 z-20 mt-1 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <button type="button" class="block w-full px-4 py-2.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="printList">
                            <span class="font-medium text-slate-800 dark:text-slate-100">Print list</span>
                            <span class="block text-xs text-slate-400">Table of the selected columns</span>
                        </button>
                        <button type="button" class="block w-full px-4 py-2.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="printSlips">
                            <span class="font-medium text-slate-800 dark:text-slate-100">Print demand slips</span>
                            <span class="block text-xs text-slate-400">One slip per student, 3 per page</span>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-outline" :disabled="!!exporting || !rows.length" @click="runExport('pdf')">{{ exporting === 'pdf' ? 'Exporting…' : 'PDF' }}</button>
                <button type="button" class="btn-outline" :disabled="!!exporting || !rows.length" @click="runExport('csv')">{{ exporting === 'csv' ? 'Exporting…' : 'CSV' }}</button>
                <button type="button" class="btn-primary" :disabled="!!exporting || !rows.length" @click="runExport('xlsx')">{{ exporting === 'xlsx' ? 'Exporting…' : 'Export Excel' }}</button>
            </div>
        </div>

        <!-- Filters -->
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
                    <label class="form-label">Vehicle</label>
                    <select v-model="filters.vehicle" class="form-input">
                        <option value="">All students</option>
                        <option value="__any">Transport students (any vehicle)</option>
                        <option value="None">No transport</option>
                        <option v-for="v in vehicles" :key="v" :value="v">{{ v }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Dues</label>
                    <select v-model="filters.dues" class="form-input">
                        <option value="all">All students</option>
                        <option value="due">Only with dues</option>
                        <option value="clear">Only fully paid</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name, adm no, father, mobile" />
                </div>
            </div>

            <!-- Column picker -->
            <details class="mt-4 rounded-xl border border-slate-200 p-3 dark:border-slate-700" :open="columnsOpen" @toggle="columnsOpen = $event.target.open">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Columns to show, export &amp; print
                    <span class="font-normal text-slate-400">({{ selectedKeys.length }} of {{ columns.length }})</span>
                </summary>
                <div class="mt-3 space-y-3">
                    <div v-for="group in columnGroups" :key="group.label">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ group.label }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1.5">
                            <label v-for="c in group.columns" :key="c.key" class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="selectedKeys" type="checkbox" :value="c.key" class="rounded border-slate-300" />
                                {{ c.label }}
                            </label>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = columns.map((c) => c.key)">Select all</button>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = [...DUES_PRESET]">Dues only</button>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="selectedKeys = [...PAID_PRESET]">Payments only</button>
                    </div>
                </div>
            </details>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn-outline !py-1.5 !text-xs" @click="resetFilters">Reset filters</button>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ rows.length }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Total paid</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">₹{{ money(totals.tot_pmnt) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-400">Total dues</p>
                <p class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">₹{{ money(totalDue) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students with dues</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ studentsWithDues }}</p>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 py-3 text-xs text-slate-400 dark:border-slate-800">
                {{ sessionName ? `Session ${sessionName}` : '' }} · DUES = paid − expected, so a <span class="font-semibold text-rose-600">negative</span> amount is still owed.
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">#</th>
                            <th
                                v-for="c in visibleColumns"
                                :key="c.key"
                                class="cursor-pointer whitespace-nowrap px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500"
                                :class="c.amount && 'text-right'"
                                @click="toggleSort(c.key)"
                            >
                                {{ c.label }} {{ sortArrow(c.key) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loading">
                            <td :colspan="visibleColumns.length + 1" class="px-4 py-10 text-center text-slate-400">Loading… (working out each student's fees)</td>
                        </tr>
                        <tr v-else-if="!sortedRows.length">
                            <td :colspan="visibleColumns.length + 1" class="px-4 py-10 text-center text-slate-400">No students match your filters.</td>
                        </tr>
                        <template v-else>
                            <tr v-for="(r, i) in pagedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-2 text-xs text-slate-400">{{ (page - 1) * perPage + i + 1 }}</td>
                                <td
                                    v-for="c in visibleColumns"
                                    :key="c.key"
                                    class="px-3 py-2"
                                    :class="c.amount ? ['whitespace-nowrap text-right tabular-nums', Number(r[c.key]) < 0 ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200'] : 'text-slate-700 dark:text-slate-200'"
                                >
                                    {{ c.amount ? money(r[c.key]) : (r[c.key] || '—') }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot v-if="!loading && sortedRows.length" class="border-t-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/50">
                        <tr>
                            <td class="px-3 py-2.5"></td>
                            <td
                                v-for="(c, i) in visibleColumns"
                                :key="c.key"
                                class="whitespace-nowrap px-3 py-2.5"
                                :class="c.amount ? ['text-right tabular-nums', Number(totals[c.key]) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100'] : 'text-slate-800 dark:text-slate-100'"
                            >
                                {{ c.amount ? money(totals[c.key]) : (i === 0 ? `Total (${rows.length})` : '') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div v-if="sortedRows.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                <span>Showing {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, sortedRows.length) }} of {{ sortedRows.length }}</span>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2">
                        Show
                        <select v-model="perPageSelection" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800">
                            <option v-for="opt in [25, 50, 100, 'all']" :key="opt" :value="opt">{{ opt === 'all' ? 'All' : opt }}</option>
                        </select>
                        per page
                    </label>
                    <div v-if="perPageSelection !== 'all'" class="flex gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="page <= 1" @click="page--">Prev</button>
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="page * perPage >= sortedRows.length" @click="page++">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const STORAGE_KEY = 'erp.demandSlip.columns';
const DETAIL_KEYS = ['session', 'admission_no', 'name', 'father_name', 'address', 'mobile', 'class', 'vehicle'];
const DUES_PRESET = ['admission_no', 'name', 'father_name', 'mobile', 'class', 'ann_dues', 'tui_dues', 'tra_dues', 'dues'];
const PAID_PRESET = ['admission_no', 'name', 'class', 'tot_pmnt', 'reg', 'adm', 'ann_pmnt', 'tui_pmnt', 'tra_pmnt'];

const loading = ref(false);
const exporting = ref(null);
const printMenu = ref(false);
const printWrap = ref(null);
const columnsOpen = ref(false);
const classes = ref([]);
const sections = ref([]);
const vehicles = ref([]);
const columns = ref([]);
const rows = ref([]);
const totals = ref({});
const school = ref({ name: '', address: '', phone: '' });
const sessionName = ref('');
const selectedKeys = ref(loadSavedColumns());
const page = ref(1);
const perPageSelection = ref(50);
const sortKey = ref(null);
const sortDir = ref('asc');

const filters = reactive(blankFilters());

function blankFilters() {
    return { academic_session_id: null, school_class_id: null, section_id: null, vehicle: '', dues: 'all', search: '' };
}

function loadSavedColumns() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (Array.isArray(saved) && saved.length) return saved;
    } catch { /* storage unavailable */ }
    return []; // = all columns, filled in once the column list loads
}

watch(selectedKeys, (keys) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(keys)); } catch { /* storage unavailable */ }
}, { deep: true });

const sessions = computed(() => (erpStore.sessionRecords || []).filter((s) => s?.id && s?.name));
const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
// Selected columns, always in Stud_Rec_Sum order.
const visibleColumns = computed(() => columns.value.filter((c) => (selectedKeys.value || []).includes(c.key)));
const columnGroups = computed(() => [
    { label: 'Student details', columns: columns.value.filter((c) => DETAIL_KEYS.includes(c.key)) },
    { label: 'Payments', columns: columns.value.filter((c) => c.amount && !c.key.endsWith('_dues') && c.key !== 'dues') },
    { label: 'Dues', columns: columns.value.filter((c) => c.key.endsWith('_dues') || c.key === 'dues') },
]);

const totalDue = computed(() => -rows.value.reduce((s, r) => s + Math.min(0, Number(r.dues) || 0), 0));
const studentsWithDues = computed(() => rows.value.filter((r) => Number(r.dues) < 0).length);

const sortedRows = computed(() => {
    if (!sortKey.value) return rows.value;
    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...rows.value].sort((a, b) => {
        const av = a[key] ?? '';
        const bv = b[key] ?? '';
        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
        return String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' }) * dir;
    });
});
const perPage = computed(() => (perPageSelection.value === 'all' ? Math.max(sortedRows.value.length, 1) : perPageSelection.value));
const pagedRows = computed(() => {
    if (perPageSelection.value === 'all') return sortedRows.value;
    const start = (page.value - 1) * perPage.value;
    return sortedRows.value.slice(start, start + perPage.value);
});

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    page.value = 1;
}
function sortArrow(key) {
    return sortKey.value === key ? (sortDir.value === 'asc' ? '↑' : '↓') : '';
}

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function currentParams() {
    const p = {};
    if (filters.academic_session_id) p.academic_session_id = filters.academic_session_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.vehicle) p.vehicle = filters.vehicle;
    if (filters.dues !== 'all') p.dues = filters.dues;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

function filterLabels() {
    const cls = classes.value.find((c) => c.id === filters.school_class_id);
    const sec = sections.value.find((s) => s.id === filters.section_id);
    return { class_label: cls?.name, section_label: sec?.name };
}

function resetFilters() {
    Object.assign(filters, blankFilters());
}

async function reload() {
    loading.value = true;
    page.value = 1;
    try {
        const { data } = await client.get('/fee-management/demand-slip', { params: currentParams() });
        columns.value = data.columns || [];
        rows.value = data.rows || [];
        totals.value = data.totals || {};
        vehicles.value = data.vehicles || [];
        school.value = data.school || school.value;
        sessionName.value = data.session?.name || '';
        const known = columns.value.map((c) => c.key);
        const kept = (selectedKeys.value || []).filter((k) => known.includes(k));
        selectedKeys.value = kept.length ? kept : known;
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not load demand slip.', 'error');
    } finally {
        loading.value = false;
    }
}

async function runExport(format) {
    exporting.value = format;
    try {
        const response = await client.get('/fee-management/demand-slip/export', {
            params: { ...currentParams(), ...filterLabels(), format, columns: selectedKeys.value },
            responseType: 'blob',
        });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = match ? match[1] : `demand-slip.${format}`;
        a.click();
        URL.revokeObjectURL(url);
        pushToast('Export downloaded.', 'success');
    } catch (err) {
        pushToast(err?.response?.status === 403 ? 'You do not have permission to export the demand slip.' : 'Export failed.', 'error');
    } finally {
        exporting.value = null;
    }
}

// ---- Printing (in the browser, from the rows on screen) ----
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function openPrint(title, styles, body) {
    printMenu.value = false;
    const win = window.open('', '_blank');
    if (!win) {
        pushToast('Allow pop-ups for this site to print.', 'error');
        return;
    }
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; }
        .num { text-align: right; white-space: nowrap; } .neg { color: #b91c1c; }
        ${styles}
    </style></head><body>${body}<script>window.onload = function () { window.focus(); window.print(); };<\/script></body></html>`);
    win.document.close();
}

function schoolHeader() {
    return `<div class="school"><div class="name">${esc(school.value.name)}</div><div>${esc(school.value.address)}</div></div>`;
}

function printList() {
    const cols = visibleColumns.value;
    const head = `<tr><th>#</th>${cols.map((c) => `<th class="${c.amount ? 'num' : ''}">${esc(c.label)}</th>`).join('')}</tr>`;
    const body = sortedRows.value.map((r, i) => `<tr><td>${i + 1}</td>${cols.map((c) => (c.amount
        ? `<td class="num${Number(r[c.key]) < 0 ? ' neg' : ''}">${money(r[c.key])}</td>`
        : `<td>${esc(r[c.key])}</td>`)).join('')}</tr>`).join('');
    const foot = `<tr><td></td>${cols.map((c, i) => (c.amount
        ? `<td class="num${Number(totals.value[c.key]) < 0 ? ' neg' : ''}">${money(totals.value[c.key])}</td>`
        : `<td>${i === 0 ? `TOTAL (${rows.value.length})` : ''}</td>`)).join('')}</tr>`;

    openPrint('Demand Slip', `
        @page { size: A4 landscape; margin: 8mm; }
        .school { text-align: center; } .school .name { font-size: 16px; font-weight: bold; }
        h1 { font-size: 13px; text-align: center; margin: 6px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
        th { background: #eee; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
        tfoot td { font-weight: bold; background: #f3f3f3; }
    `, `${schoolHeader()}
        <h1>Demand Slip — Stud_Rec_Sum${sessionName.value ? ` (${esc(sessionName.value)})` : ''}</h1>        <table><thead>${head}</thead><tbody>${body}</tbody><tfoot>${foot}</tfoot></table>`);
}

/** One demand slip per student: what was expected, paid and is still due for each fee head. */
function printSlips() {
    const due = (v) => Math.max(0, -(Number(v) || 0));
    const slips = sortedRows.value.map((r) => {
        const lines = [
            ['Annual / Session fee', Number(r.ann_pmnt) - Number(r.ann_dues), r.ann_pmnt, r.ann_dues],
            ['Tuition fee', r.tui_calc, r.tui_pmnt, r.tui_dues],
            ['Transport fee', r.tra_calc, r.tra_pmnt, r.tra_dues],
        ].filter(([, expected, paid]) => Number(expected) || Number(paid));
        const totalDueAmount = due(r.dues);
        return `<div class="slip">
            ${schoolHeader()}
            <div class="title">FEE DEMAND SLIP${sessionName.value ? ` — Session ${esc(sessionName.value)}` : ''}</div>
            <table class="info">
                <tr><td>Adm No: <b>${esc(r.admission_no)}</b></td><td>Name: <b>${esc(r.name)}</b></td><td>Class: <b>${esc(r.class)}${r.section ? ` - ${esc(r.section)}` : ''}</b></td></tr>
                <tr><td>Father: <b>${esc(r.father_name)}</b></td><td>Mobile: <b>${esc(r.mobile)}</b></td><td>Vehicle: <b>${esc(r.vehicle)}</b></td></tr>
            </table>
            <table class="fees">
                <thead><tr><th>Fee head</th><th class="num">Expected</th><th class="num">Paid</th><th class="num">Due</th></tr></thead>
                <tbody>${lines.map(([label, expected, paid, dues]) => `<tr><td>${label}</td><td class="num">${money(expected)}</td><td class="num">${money(paid)}</td><td class="num${due(dues) ? ' neg' : ''}">${money(due(dues))}</td></tr>`).join('')
                    || '<tr><td colspan="4">No fee set for this session.</td></tr>'}</tbody>
                <tfoot><tr><td colspan="3">Total paid this session (incl. REG ${money(r.reg)}, ADM ${money(r.adm)}): ${money(r.tot_pmnt)}</td>
                    <td class="num${totalDueAmount ? ' neg' : ''}">${money(totalDueAmount)}</td></tr></tfoot>
            </table>
            <div class="foot">
                <span>${totalDueAmount ? `Please pay <b>₹${money(totalDueAmount)}</b> at the earliest.` : 'No dues — thank you.'}</span>
                <span class="sign">Accountant / Principal</span>
            </div>
        </div>`;
    }).join('');

    openPrint('Fee Demand Slips', `
        @page { size: A4 portrait; margin: 8mm; }
        .slip { border: 1px solid #333; padding: 8px 10px; height: 91mm; margin-bottom: 3mm; page-break-inside: avoid; font-size: 11px; display: flex; flex-direction: column; }
        .slip:nth-of-type(3n) { page-break-after: always; }
        .school { text-align: center; font-size: 10px; } .school .name { font-size: 14px; font-weight: bold; }
        .title { text-align: center; font-weight: bold; letter-spacing: 1px; border-top: 1px solid #333; border-bottom: 1px solid #333; margin: 4px 0; padding: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 1px 2px; }
        .fees { margin-top: 4px; } .fees th, .fees td { border: 1px solid #999; padding: 2px 5px; } .fees th { background: #eee; }
        .fees tfoot td { font-weight: bold; }
        .foot { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 6px; }
        .sign { border-top: 1px solid #555; padding-top: 2px; min-width: 140px; text-align: center; }
    `, slips);
}

function closePrintMenu(e) {
    if (!printWrap.value?.contains(e.target)) printMenu.value = false;
}

// ---- Reload when filters change ----
let reloadTimer = null;
let ready = false;
function scheduleReload(delay = 0) {
    if (!ready) return;
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(reload, delay);
}

watch(() => [filters.academic_session_id, filters.school_class_id, filters.section_id, filters.vehicle, filters.dues], () => scheduleReload(0));
watch(() => filters.search, () => scheduleReload(350));
watch(() => erpStore.currentSession, () => { if (!filters.academic_session_id) scheduleReload(0); });
watch(perPageSelection, () => { page.value = 1; });

onMounted(async () => {
    document.addEventListener('click', closePrintMenu);
    const lookups = await fetchAcademicsLookups();
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    await reload();
    ready = true;
});
onBeforeUnmount(() => document.removeEventListener('click', closePrintMenu));
</script>
