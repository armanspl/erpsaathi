<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Exam Admit Cards</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate admit cards by branch, class, section, and exam.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openInstructions">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h3M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                General Instructions
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!filters.exam_id || !filters.branch_id || zipScope === 'all'" @click="downloadBatch('all')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'all' ? 'Zipping...' : 'Download all ZIP' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!allFiltersSet || zipScope === 'section'" @click="downloadBatch('section')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'section' ? 'Zipping...' : 'Download section ZIP' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!selectedIds.size || zipScope === 'selected'" @click="downloadBatch('selected')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'selected' ? 'Zipping...' : 'Download selected' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!selectedIds.size || printing" @click="printSelected">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 14H4a1 1 0 01-1-1v-3a1 1 0 011-1h16a1 1 0 011 1v3a1 1 0 01-1 1h-2M6 14h12v6H6v-6z"/></svg>
                {{ printing ? 'Preparing...' : 'Print Selected' }}
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
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="filters.exam_id" class="form-input" @change="load">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input" @change="onBranchChange">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
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
                </div>
                <div v-if="selectedExam" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <ExamPdfColorPicker :exam="selectedExam" @update="onExamColorUpdate" />
                    <p class="mt-1 text-xs text-slate-400">Applies to every admit card downloaded for {{ selectedExam.name }}.</p>
                </div>
            </div>
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">Session {{ currentSessionName || '—' }}</p>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
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
            <div v-else-if="!rows.length" class="px-6 py-16 text-center text-sm text-slate-400">
                {{ allFiltersSet ? 'No active students found for this selection.' : 'Select exam and branch to generate admit cards.' }}
            </div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200" @click="toggleSort('roll_no')">
                                    Roll No<span class="text-[10px] opacity-70">{{ sortArrow('roll_no') }}</span>
                                </button>
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200" @click="toggleSort('admission_no')">
                                    Admission ID<span class="text-[10px] opacity-70">{{ sortArrow('admission_no') }}</span>
                                </button>
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Father Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Mother Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in displayedRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedIds.has(r.student_id)" @change="toggleOne(r.student_id, $event.target.checked)" /></td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.roll_no ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.father_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.mother_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Change fields for download" @click="openOverride(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.5-9.5a2.121 2.121 0 013 3L12 16l-4 1 1-4 8.5-8.5z"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download" @click="downloadStudentPdf(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in displayedRows" :key="r.student_id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ r.name }}</div>
                        <p class="mt-1 text-xs text-slate-400">{{ r.admission_no }} · Roll {{ r.roll_no ?? '—' }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Father: {{ r.father_name ?? '—' }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Mother: {{ r.mother_name ?? '—' }}</p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="openOverride(r)">Edit fields</button>
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="downloadStudentPdf(r)">Download</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change fields for download -->
        <div v-if="overrideRow" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Change fields for download</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="overrideRow = null">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Overrides apply only to the next PDF download and are not saved to the database.</p>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Student name</label>
                        <input v-model="overrideForm.name" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Roll number</label>
                        <input v-model="overrideForm.roll_no" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Admission ID</label>
                        <input v-model="overrideForm.admission_no" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Father name</label>
                        <input v-model="overrideForm.father_name" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Mother name</label>
                        <input v-model="overrideForm.mother_name" type="text" class="form-input" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="overrideRow = null">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="applyingOverride" @click="applyOverride">{{ applyingOverride ? 'Downloading...' : 'Apply for download' }}</button>
                </div>
            </div>
        </div>

        <!-- General Instructions -->
        <div v-if="instructionsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="instructionsOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">General Instructions</h2>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Printed near the bottom of every admit card for this exam, below the schedule. Same text for all branches, classes, and sections · Session {{ currentSessionName || '—' }} · {{ instructionsExamName || '—' }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="instructionsOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-4">
                    <label class="form-label">Exam</label>
                    <select v-model="instructionsExamId" class="form-input" @change="loadInstructions">
                        <option :value="null">Select exam</option>
                        <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
                <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800">
                        <button type="button" class="rounded px-2 py-1 text-xs font-bold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Bold" @click="formatInstructions('bold')">B</button>
                        <button type="button" class="rounded px-2 py-1 text-xs italic text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Italic" @click="formatInstructions('italic')">I</button>
                        <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Numbered list" @click="formatInstructions('insertOrderedList')">1.</button>
                        <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Bulleted list" @click="formatInstructions('insertUnorderedList')">-</button>
                        <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Clear formatting" @click="formatInstructions('removeFormat')">Tx</button>
                    </div>
                    <div
                        ref="instructionsEditor"
                        class="min-h-[160px] px-3 py-2 text-sm text-slate-800 outline-none empty:before:text-slate-400 empty:before:italic empty:before:content-[attr(data-placeholder)] dark:text-slate-100"
                        contenteditable="true"
                        data-placeholder="Write the general instructions shown on every admit card for this exam..."
                        @input="syncInstructions"
                    />
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="instructionsOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!instructionsExamId || savingInstructions" @click="saveInstructions">{{ savingInstructions ? 'Saving...' : 'Save instructions' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { erpStore } from '../../store';
import { downloadPdf, openPdfBlob, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import ExamPdfColorPicker from '../../components/ExamPdfColorPicker.vue';

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];
const viewMode = ref('table');
const filtersOpen = ref(true);

const exams = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const currentSessionName = ref('');

const filters = reactive({ exam_id: null, branch_id: null, school_class_id: null, section_id: null });
const rows = ref([]);
const loading = ref(false);
const selectedIds = ref(new Set());
const zipScope = ref(null);
const printing = ref(false);
const sortKey = ref(null);
const sortDir = ref('asc');

const allFiltersSet = computed(() => !!(filters.exam_id && filters.branch_id));
const selectedExam = computed(() => exams.value.find((e) => e.id === filters.exam_id) || null);

const displayedRows = computed(() => {
    if (!sortKey.value) return rows.value;
    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...rows.value].sort((a, b) => {
        const av = normalizeSortValue(a[key]);
        const bv = normalizeSortValue(b[key]);
        if (av < bv) return -1 * dir;
        if (av > bv) return 1 * dir;
        return 0;
    });
});

function normalizeSortValue(value) {
    if (value == null || value === '') return '';
    const str = String(value).trim();
    // Natural-ish numeric compare when both sides are numbers (roll nos like "12").
    const num = Number(str);
    if (str !== '' && !Number.isNaN(num) && /^-?\d+(\.\d+)?$/.test(str)) return num;
    return str.toLowerCase();
}

function toggleSort(key) {
    if (sortKey.value !== key) {
        sortKey.value = key;
        sortDir.value = 'asc';
        return;
    }
    if (sortDir.value === 'asc') {
        sortDir.value = 'desc';
        return;
    }
    sortKey.value = null;
    sortDir.value = 'asc';
}

function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

function onExamColorUpdate(updated) {
    const idx = exams.value.findIndex((e) => e.id === updated.id);
    if (idx !== -1) exams.value[idx] = { ...exams.value[idx], ...updated };
}

function sectionsForClass(classId) {
    if (!classId) return sections.value;
    return sections.value.filter((s) => s.school_class_id === classId);
}

async function loadLookups() {
    const [academics, sessionsRes, examsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/exams'),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    exams.value = examsRes.data;
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
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

async function load() {
    selectedIds.value.clear();
    sortKey.value = null;
    sortDir.value = 'asc';
    if (!allFiltersSet.value) {
        rows.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await client.get(`/exams/${filters.exam_id}/admit-cards`, {
            params: {
                branch_id: filters.branch_id,
                ...(filters.school_class_id ? { school_class_id: filters.school_class_id } : {}),
                ...(filters.section_id ? { section_id: filters.section_id } : {}),
            },
        });
        rows.value = data;
    } finally {
        loading.value = false;
    }
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

async function downloadStudentPdf(row, overrides = {}) {
    await downloadPdf(
        `/exams/${filters.exam_id}/admit-cards/${row.student_id}/pdf`,
        `admit-card-${row.admission_no}.pdf`,
        overrides,
    );
}

function batchParams(scope) {
    const params = { branch_id: filters.branch_id };
    if (scope === 'section') {
        if (filters.school_class_id) params.school_class_id = filters.school_class_id;
        if (filters.section_id) params.section_id = filters.section_id;
    } else if (scope === 'selected') {
        delete params.branch_id;
        params.student_ids = [...selectedIds.value];
    } else if (scope === 'class' || scope === 'branch') {
        if (filters.school_class_id) params.school_class_id = filters.school_class_id;
        if (filters.section_id) params.section_id = filters.section_id;
    }
    return params;
}

async function downloadBatch(scope) {
    if (!filters.exam_id) return;
    zipScope.value = scope;
    try {
        const response = await client.get(`/exams/${filters.exam_id}/admit-cards/zip`, {
            params: batchParams(scope),
            responseType: 'blob',
        });
        triggerBlobDownload(new Blob([response.data], { type: 'application/zip' }), `admit-cards-${filters.exam_id}.zip`);
    } catch (e) {
        pushToast('Could not generate ZIP for the selected scope.', 'error');
    } finally {
        zipScope.value = null;
    }
}

async function printSelected() {
    if (!filters.exam_id || !selectedIds.value.size) return;
    // Open synchronously so the browser doesn't block the popup after await.
    const tab = window.open('about:blank', '_blank');
    if (tab) {
        try {
            tab.document.title = 'Loading PDF...';
        } catch {
            // ignore
        }
    }
    printing.value = true;
    try {
        const response = await client.post(
            `/exams/${filters.exam_id}/admit-cards/print`,
            { student_ids: [...selectedIds.value] },
            { responseType: 'blob', timeout: 0 },
        );
        openPdfBlob(response.data, tab);
    } catch (e) {
        if (tab && !tab.closed) tab.close();
        pushToast('Could not prepare admit cards for printing.', 'error');
    } finally {
        printing.value = false;
    }
}

// --- Change fields for download ---
const overrideRow = ref(null);
const overrideForm = reactive({ name: '', roll_no: '', admission_no: '', father_name: '', mother_name: '' });
const applyingOverride = ref(false);

function openOverride(row) {
    overrideRow.value = row;
    Object.assign(overrideForm, {
        name: row.name || '',
        roll_no: row.roll_no || '',
        admission_no: row.admission_no || '',
        father_name: row.father_name || '',
        mother_name: row.mother_name || '',
    });
}

async function applyOverride() {
    applyingOverride.value = true;
    try {
        await downloadStudentPdf(overrideRow.value, { ...overrideForm });
        overrideRow.value = null;
    } finally {
        applyingOverride.value = false;
    }
}

// --- General Instructions ---
const instructionsOpen = ref(false);
const instructionsExamId = ref(null);
const instructionsEditor = ref(null);
const instructionsHtml = ref('');
const savingInstructions = ref(false);
const instructionsExamName = computed(() => exams.value.find((e) => e.id === instructionsExamId.value)?.name || '');

function openInstructions() {
    instructionsExamId.value = filters.exam_id;
    instructionsOpen.value = true;
    loadInstructions();
}

async function loadInstructions() {
    instructionsHtml.value = '';
    if (instructionsEditor.value) instructionsEditor.value.innerHTML = '';
    if (!instructionsExamId.value) return;
    const { data } = await client.get(`/exams/${instructionsExamId.value}/admit-cards/instructions`);
    instructionsHtml.value = data.instructions || '';
    await nextTick();
    if (instructionsEditor.value) instructionsEditor.value.innerHTML = instructionsHtml.value;
}

function syncInstructions() {
    instructionsHtml.value = instructionsEditor.value?.innerHTML || '';
}

function formatInstructions(command) {
    instructionsEditor.value?.focus();
    document.execCommand(command, false);
    syncInstructions();
}

async function saveInstructions() {
    if (!instructionsExamId.value) return;
    savingInstructions.value = true;
    try {
        await client.put(`/exams/${instructionsExamId.value}/admit-cards/instructions`, { instructions: instructionsHtml.value });
        pushToast('General instructions saved.', 'success');
        instructionsOpen.value = false;
    } finally {
        savingInstructions.value = false;
    }
}
</script>
