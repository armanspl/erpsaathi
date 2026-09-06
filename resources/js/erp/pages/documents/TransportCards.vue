<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Transport Cards</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate bus passes for students using school transport.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!filters.branch_id || zipScope === 'all'" @click="downloadZip('all')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'all' ? 'Zipping...' : 'Download all ZIP' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!rows.length || zipScope === 'filtered'" @click="downloadZip('filtered')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'filtered' ? 'Zipping...' : 'Download filtered ZIP' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!selectedIds.size || zipScope === 'selected'" @click="downloadZip('selected')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'selected' ? 'Zipping...' : 'Download selected' }}
            </button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input" @change="onBranchChange">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Route</label>
                        <select v-model="filters.route_id" class="form-input" @change="load">
                            <option :value="null">All routes</option>
                            <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id" @change="onClassChange">
                            <option :value="null">{{ filters.branch_id ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input" :disabled="!filters.branch_id" @change="load">
                            <option :value="null">All sections</option>
                            <option v-for="s in sectionsForClass(filters.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <div class="flex gap-1.5">
                            <input v-model.trim="searchInput" type="text" class="form-input" placeholder="Name, admission ID..." @keyup.enter="applySearch" />
                            <button type="button" class="btn-outline shrink-0" @click="applySearch">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!allFiltersSet" class="text-sm text-slate-500 dark:text-slate-400">Select a branch to load students.</div>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <button
                    v-for="mode in viewModes"
                    :key="mode.id"
                    type="button"
                    class="rounded-md p-1.5 transition"
                    :class="viewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    :title="mode.label"
                    @click="viewMode = mode.id"
                >
                    <span v-html="mode.icon" />
                </button>
            </div>

            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!rows.length" class="px-6 py-16 text-center text-sm text-slate-400">No students match this selection.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('admission_no')">Admission ID {{ sortArrow('admission_no') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Name {{ sortArrow('name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('school_class_name')">Class {{ sortArrow('school_class_name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('section_name')">Section {{ sortArrow('section_name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('route_name')">Route {{ sortArrow('route_name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('stop_name')">Pickup {{ sortArrow('stop_name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('driver_name')">Driver {{ sortArrow('driver_name') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in sortedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedIds.has(r.student_id)" @change="toggleOne(r.student_id, $event.target.checked)" /></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.school_class_name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.section_name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.route_name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.stop_name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.driver_name || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Edit transport assignment" @click="openEditAssignment(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.5-9.5a2.121 2.121 0 013 3L12 16l-4 1 1-4 8.5-8.5z"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download" :disabled="downloadingId === r.student_id" @click="downloadStudentPdf(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in sortedRows" :key="r.student_id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ r.name }}</div>
                        <p class="mt-1 text-xs text-slate-400">{{ r.admission_no }} · {{ r.school_class_name }} ({{ r.section_name }})</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Route: {{ r.route_name || '—' }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Pickup: {{ r.stop_name || '—' }} · Driver: {{ r.driver_name || '—' }}</p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="openEditAssignment(r)">Edit</button>
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="downloadStudentPdf(r)">Download</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Transport Assignment -->
        <div v-if="assignmentRow" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="assignmentRow = null" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Edit Transport Assignment</h2>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ assignmentRow.name }} · {{ assignmentRow.admission_no }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="assignmentRow = null">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Route</label>
                        <select v-model="assignmentForm.route_id" class="form-input" @change="onAssignmentRouteChange">
                            <option :value="null">Select route</option>
                            <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Pickup Stop</label>
                        <select v-model="assignmentForm.route_stop_id" class="form-input" :disabled="!assignmentForm.route_id">
                            <option :value="null">{{ assignmentForm.route_id ? 'Select stop' : 'Select route first' }}</option>
                            <option v-for="s in assignmentStops" :key="s.id" :value="s.id">{{ s.stop_name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Start Date</label>
                            <input v-model="assignmentForm.start_date" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="assignmentForm.status" class="form-input">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="assignmentRow = null">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!assignmentForm.route_id || !assignmentForm.route_stop_id || savingAssignment" @click="saveAssignment">{{ savingAssignment ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];
const viewMode = ref('table');
const filtersOpen = ref(true);

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const routes = ref([]);

const filters = reactive({ branch_id: null, route_id: null, school_class_id: null, section_id: null, search: '' });
const searchInput = ref('');
const rows = ref([]);
const loading = ref(false);
const selectedIds = ref(new Set());
const zipScope = ref(null);
const downloadingId = ref(null);
const sortKey = ref('name');
const sortDir = ref('asc');

const allFiltersSet = computed(() => !!filters.branch_id);

function sectionsForClass(classId) {
    if (!classId) return sections.value;
    return sections.value.filter((s) => s.school_class_id === classId);
}

async function loadLookups() {
    const [academics, routesRes] = await Promise.all([fetchAcademicsLookups(), client.get('/transport/routes')]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    routes.value = routesRes.data;
}
loadLookups();

function onBranchChange() {
    filters.school_class_id = null;
    filters.section_id = null;
    load();
}
function onClassChange() {
    filters.section_id = null;
    load();
}
function applySearch() {
    filters.search = searchInput.value;
    load();
}

async function load() {
    selectedIds.value.clear();
    if (!allFiltersSet.value) {
        rows.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await client.get('/documents/transport-cards', {
            params: {
                branch_id: filters.branch_id,
                ...(filters.school_class_id ? { school_class_id: filters.school_class_id } : {}),
                ...(filters.section_id ? { section_id: filters.section_id } : {}),
                route_id: filters.route_id,
                search: filters.search || null,
            },
        });
        rows.value = data;
    } finally {
        loading.value = false;
    }
}

const sortedRows = computed(() =>
    [...rows.value].sort((a, b) => {
        let av = a[sortKey.value] ?? '';
        let bv = b[sortKey.value] ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    }),
);
function toggleSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = key; sortDir.value = 'asc'; }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

const allSelected = computed(() => rows.value.length > 0 && rows.value.every((r) => selectedIds.value.has(r.student_id)));
function toggleAll(checked) {
    if (checked) rows.value.forEach((r) => selectedIds.value.add(r.student_id));
    else selectedIds.value.clear();
}
function toggleOne(id, checked) {
    if (checked) selectedIds.value.add(id);
    else selectedIds.value.delete(id);
}

async function downloadStudentPdf(row) {
    downloadingId.value = row.student_id;
    try {
        await downloadPdf(
            `/documents/transport-cards/${row.student_id}/pdf`,
            `transport-card-${row.admission_no}.pdf`,
        );
    } finally {
        downloadingId.value = null;
    }
}

async function downloadZip(scope) {
    zipScope.value = scope;
    try {
        let params = {};
        if (scope === 'all') params = { branch_id: filters.branch_id };
        else if (scope === 'filtered') {
            params = {
                branch_id: filters.branch_id,
                ...(filters.school_class_id ? { school_class_id: filters.school_class_id } : {}),
                ...(filters.section_id ? { section_id: filters.section_id } : {}),
                route_id: filters.route_id,
                search: filters.search || null,
            };
        }
        else params = { student_ids: [...selectedIds.value] };

        const response = await client.get('/documents/transport-cards/zip', { params, responseType: 'blob' });
        triggerBlobDownload(new Blob([response.data], { type: 'application/zip' }), 'transport-cards.zip');
    } catch (e) {
        pushToast('Could not generate ZIP for the selected scope.', 'error');
    } finally {
        zipScope.value = null;
    }
}

// --- Edit Transport Assignment ---
const assignmentRow = ref(null);
const assignmentStops = ref([]);
const savingAssignment = ref(false);
const assignmentForm = reactive({ route_id: null, route_stop_id: null, start_date: new Date().toISOString().slice(0, 10), status: 'Active' });

async function onAssignmentRouteChange() {
    assignmentForm.route_stop_id = null;
    assignmentStops.value = [];
    if (!assignmentForm.route_id) return;
    const { data } = await client.get('/transport/stops', { params: { route_id: assignmentForm.route_id } });
    assignmentStops.value = data;
}

async function openEditAssignment(row) {
    assignmentRow.value = row;
    Object.assign(assignmentForm, {
        route_id: row.route_id || null,
        route_stop_id: row.route_stop_id || null,
        start_date: row.start_date || new Date().toISOString().slice(0, 10),
        status: row.status || 'Active',
    });
    assignmentStops.value = [];
    if (assignmentForm.route_id) {
        const { data } = await client.get('/transport/stops', { params: { route_id: assignmentForm.route_id } });
        assignmentStops.value = data;
    }
}

async function saveAssignment() {
    if (!assignmentRow.value) return;
    savingAssignment.value = true;
    try {
        if (assignmentRow.value.student_transport_id) {
            await client.put(`/transport/student-transport/${assignmentRow.value.student_transport_id}`, assignmentForm);
        } else {
            await client.post('/transport/student-transport', { ...assignmentForm, student_id: assignmentRow.value.student_id });
        }
        pushToast('Transport assignment updated.', 'success');
        assignmentRow.value = null;
        await load();
    } finally {
        savingAssignment.value = false;
    }
}
</script>
