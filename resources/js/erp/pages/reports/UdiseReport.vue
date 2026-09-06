<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">UDISE</h1>
                <Breadcrumb :items="['Dashboard', 'Reports', 'UDISE']" class="mt-1" />
                <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                    Student UDISE profile report — personal, schooling, and facilities details from student records.
                </p>
            </div>
            <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="!rows.length" @click="openExportModal">
                Export
            </button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
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
                <div class="lg:col-span-2">
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission / PEN" />
                </div>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading…</div>

        <div v-else class="grid gap-5 xl:grid-cols-[minmax(0,22rem)_1fr]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Students</h2>
                    <span class="text-xs text-slate-400">{{ rows.length }}</span>
                </div>
                <div class="max-h-[70vh] overflow-y-auto">
                    <button
                        v-for="r in rows"
                        :key="r.id"
                        type="button"
                        class="flex w-full flex-col gap-0.5 border-b border-slate-100 px-4 py-3 text-left transition dark:border-slate-800"
                        :class="selectedId === r.id ? 'bg-primary-50 dark:bg-primary-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'"
                        @click="selectedId = r.id"
                    >
                        <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ r.header.student_name }}</span>
                        <span class="truncate text-xs text-slate-500">{{ r.header.class_section }}</span>
                        <span class="truncate text-[11px] text-slate-400">PEN {{ r.header.pen }} · Aadhaar {{ r.header.aadhaar_status }}</span>
                    </button>
                    <p v-if="!rows.length" class="px-4 py-12 text-center text-sm text-slate-400">No students match these filters.</p>
                </div>
            </div>

            <div v-if="selected" class="space-y-4">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/50">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ selected.header.student_name }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ selected.header.class_section }} · {{ selected.header.academic_year }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <template v-if="!editing">
                                <button type="button" class="btn-primary !py-1.5 !text-xs" @click="startEdit">Edit</button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="saving" @click="cancelEdit">Cancel</button>
                                <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="saving" @click="saveEdit">
                                    {{ saving ? 'Saving…' : 'Save' }}
                                </button>
                            </template>
                        </div>
                    </div>
                    <dl class="grid gap-px bg-slate-100 sm:grid-cols-2 dark:bg-slate-800">
                        <div v-for="item in headerItems" :key="item.label" class="bg-white px-4 py-3 dark:bg-slate-900">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ display(item.value) }}</dd>
                        </div>
                    </dl>
                </div>

                <section
                    v-for="block in detailBlocks"
                    :key="block.title"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ block.title }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="(field, idx) in block.fields" :key="field.key" class="align-top">
                                    <th class="w-[48%] px-4 py-2.5 text-xs font-medium text-slate-500">{{ idx + 1 }}. {{ field.label }}</th>
                                    <td class="px-4 py-2.5 text-slate-800 dark:text-slate-100">
                                        <template v-if="editing && !isReadOnly(field.key)">
                                            <select
                                                v-if="isYesNo(field.key)"
                                                v-model="editValues[field.key]"
                                                class="form-input !py-1.5 !text-sm"
                                            >
                                                <option value="">—</option>
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                            </select>
                                            <select
                                                v-else-if="field.key === 'gender'"
                                                v-model="editValues[field.key]"
                                                class="form-input !py-1.5 !text-sm"
                                            >
                                                <option value="">—</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <input
                                                v-else-if="isDate(field.key)"
                                                v-model="editValues[field.key]"
                                                type="date"
                                                class="form-input !py-1.5 !text-sm"
                                            />
                                            <input
                                                v-else-if="isNumber(field.key)"
                                                v-model="editValues[field.key]"
                                                type="number"
                                                step="any"
                                                class="form-input !py-1.5 !text-sm"
                                            />
                                            <textarea
                                                v-else-if="field.key === 'address'"
                                                v-model="editValues[field.key]"
                                                rows="2"
                                                class="form-input !py-1.5 !text-sm"
                                            />
                                            <input
                                                v-else
                                                v-model="editValues[field.key]"
                                                type="text"
                                                class="form-input !py-1.5 !text-sm"
                                            />
                                        </template>
                                        <template v-else>{{ display(editing ? editValues[field.key] : field.value) }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 px-4 py-24 text-center text-sm text-slate-400 dark:border-slate-700">
                Select a student to view the UDISE profile.
            </div>
        </div>

        <!-- Column selection modal -->
        <div v-if="exportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="exportModalOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Export columns</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Choose which fields to include. Excel will use only the selected columns, in this order.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="exportModalOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <input v-model="columnSearch" type="search" class="form-input" placeholder="Search fields…" />
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="selectAllColumns">Select all</button>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="deselectAllColumns">Deselect all</button>
                        <span class="ml-auto text-xs text-slate-400">{{ selectedColumnKeys.length }} of {{ allColumnKeys.length }} selected</span>
                    </div>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div v-for="group in filteredColumnGroups" :key="group.key">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</h3>
                            <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="toggleGroup(group)">
                                {{ groupAllSelected(group) ? 'Clear group' : 'Select group' }}
                            </button>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-2">
                            <label
                                v-for="field in group.fields"
                                :key="field.key"
                                class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60"
                            >
                                <input v-model="selectedColumnKeys" type="checkbox" :value="field.key" class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                                <span>{{ field.label }}</span>
                            </label>
                        </div>
                    </div>
                    <p v-if="!filteredColumnGroups.length" class="py-8 text-center text-sm text-slate-400">No fields match your search.</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !selectedColumnKeys.length" @click="runExport('xlsx')">📊 Excel (.xlsx)</button>
                        <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !selectedColumnKeys.length" @click="runExport('csv')">📄 CSV</button>
                        <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !selectedColumnKeys.length" @click="runExport('pdf')">📕 PDF</button>
                    </div>
                    <button type="button" class="btn-outline !py-1.5 !text-xs" @click="exportModalOpen = false">Cancel</button>
                </div>
                <p v-if="exporting" class="px-5 pb-3 text-xs text-slate-400">Exporting…</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const exporting = ref(false);
const rows = ref([]);
const selectedId = ref(null);
const editing = ref(false);
const saving = ref(false);
const editValues = reactive({});

const YES_NO_KEYS = new Set([
    'bpl_beneficiary', 'aay_beneficiary', 'ews_disadvantaged', 'cwsn', 'indian_nationality',
    'out_of_school_child', 'disability_certificate', 'facilities_provided', 'ncc', 'nss', 'scouts_guides', 'olympiads',
]);
const DATE_KEYS = new Set(['dob', 'admission_date']);
const NUMBER_KEYS = new Set(['height_cm', 'weight_kg', 'prev_marks_percent', 'prev_attendance_days', 'disability_percentage', 'rte_amount_claimed']);
const READONLY_KEYS = new Set(['class_section_roll']);

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);

const filters = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
});

const exportModalOpen = ref(false);
const columnSearch = ref('');
const columnGroups = ref([]);
const allColumnKeys = ref([]);
const selectedColumnKeys = ref([]);

const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
const selected = computed(() => rows.value.find((r) => r.id === selectedId.value) || null);

const headerItems = computed(() => {
    if (!selected.value) return [];
    const h = selected.value.header;
    return [
        { label: 'Student Name', value: h.student_name },
        { label: 'Class & Section', value: h.class_section },
        { label: 'Academic Year', value: h.academic_year },
        { label: 'Permanent Education Number (PEN)', value: h.pen },
        { label: 'Aadhaar Status', value: h.aadhaar_status },
    ];
});

const detailBlocks = computed(() => {
    if (!selected.value) return [];
    return [
        { title: 'Basic Student Information', fields: selected.value.personal },
        { title: 'Admission / Academic Information', fields: selected.value.schooling },
        { title: 'Facilities / Other Information', fields: selected.value.facilities },
    ];
});

const filteredColumnGroups = computed(() => {
    const q = columnSearch.value.trim().toLowerCase();
    return columnGroups.value
        .map((group) => ({
            ...group,
            fields: q
                ? group.fields.filter((f) => f.label.toLowerCase().includes(q) || f.key.toLowerCase().includes(q))
                : group.fields,
        }))
        .filter((group) => group.fields.length);
});

function display(value) {
    if (value === null || value === undefined || value === '') return '—';
    return value;
}

function isYesNo(key) {
    return YES_NO_KEYS.has(key);
}
function isDate(key) {
    return DATE_KEYS.has(key);
}
function isNumber(key) {
    return NUMBER_KEYS.has(key);
}
function isReadOnly(key) {
    return READONLY_KEYS.has(key);
}

function toDateInput(value) {
    if (!value) return '';
    const raw = String(value).trim();
    // Already yyyy-mm-dd
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return '';
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
}

function clearEditValues() {
    Object.keys(editValues).forEach((k) => delete editValues[k]);
}

function startEdit() {
    if (!selected.value) return;
    clearEditValues();
    const values = { ...(selected.value.values || {}) };
    for (const key of DATE_KEYS) {
        if (key in values) values[key] = toDateInput(values[key]);
    }
    Object.assign(editValues, values);
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    clearEditValues();
}

async function saveEdit() {
    if (!selected.value) return;
    saving.value = true;
    try {
        const { data } = await client.put(`/reports/udise/${selected.value.id}`, {
            values: { ...editValues },
        });
        const idx = rows.value.findIndex((r) => r.id === selected.value.id);
        if (idx !== -1 && data.row) {
            rows.value[idx] = data.row;
        }
        editing.value = false;
        clearEditValues();
        pushToast('Student UDISE profile updated.', 'success');
    } catch (e) {
        pushToast(e.response?.data?.message || 'Could not save changes.', 'error');
    } finally {
        saving.value = false;
    }
}

function currentParams() {
    const p = {};
    if (filters.branch_id) p.branch_id = filters.branch_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

function applyExportColumns(payload) {
    const groups = payload?.groups || [];
    const keys = payload?.all_keys || groups.flatMap((g) => g.fields.map((f) => f.key));
    columnGroups.value = groups;
    allColumnKeys.value = keys;
    if (!selectedColumnKeys.value.length) {
        selectedColumnKeys.value = [...keys];
    }
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await client.get('/reports/udise', { params: currentParams() });
        rows.value = data.rows || [];
        if (data.export_columns) applyExportColumns(data.export_columns);
        if (!rows.value.some((r) => r.id === selectedId.value)) {
            selectedId.value = rows.value[0]?.id ?? null;
        }
    } finally {
        loading.value = false;
    }
}

function openExportModal() {
    if (!allColumnKeys.value.length && columnGroups.value.length) {
        allColumnKeys.value = columnGroups.value.flatMap((g) => g.fields.map((f) => f.key));
    }
    if (!selectedColumnKeys.value.length) {
        selectedColumnKeys.value = [...allColumnKeys.value];
    }
    columnSearch.value = '';
    exportModalOpen.value = true;
}

function selectAllColumns() {
    selectedColumnKeys.value = [...allColumnKeys.value];
}

function deselectAllColumns() {
    selectedColumnKeys.value = [];
}

function groupAllSelected(group) {
    return group.fields.every((f) => selectedColumnKeys.value.includes(f.key));
}

function toggleGroup(group) {
    const keys = group.fields.map((f) => f.key);
    if (groupAllSelected(group)) {
        selectedColumnKeys.value = selectedColumnKeys.value.filter((k) => !keys.includes(k));
    } else {
        selectedColumnKeys.value = [...new Set([...selectedColumnKeys.value, ...keys])];
    }
}

async function runExport(format) {
    if (!selectedColumnKeys.value.length) {
        pushToast('Select at least one column.', 'error');
        return;
    }
    // Keep catalog order
    const ordered = allColumnKeys.value.filter((k) => selectedColumnKeys.value.includes(k));
    exporting.value = true;
    try {
        const response = await client.get('/reports/udise', {
            params: {
                ...currentParams(),
                format,
                columns: ordered,
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
        const ext = format === 'xlsx' ? 'xlsx' : format;
        const filename = match ? match[1] : `report-udise.${ext}`;
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
        exportModalOpen.value = false;
        pushToast(`Exported ${ordered.length} column(s) as ${format.toUpperCase()}.`, 'success');
    } catch {
        pushToast('Could not export UDISE report.', 'error');
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

watch(() => selectedId.value, () => {
    if (editing.value) cancelEdit();
});

watch(() => [filters.branch_id, filters.school_class_id, filters.section_id], () => scheduleReload(0));
watch(() => filters.search, () => scheduleReload(300));

onMounted(async () => {
    const lookups = await fetchAcademicsLookups();
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    await reload();
});
</script>
