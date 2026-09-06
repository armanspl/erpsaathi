<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Marks Management</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select branch, class, section, term, and exam to view and enter marks.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="router.push('/exam-management/exam-results')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36-6.36l-2.12 2.12M8.76 15.24l-2.12 2.12m0-10.72l2.12 2.12m8.48 8.48l-2.12-2.12"/><circle cx="12" cy="12" r="3"/></svg>
                    Live results
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openTemplateModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h1M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                    Download template
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openImportModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 3v12m0 0l-4-4m4 4l4-4"/></svg>
                    Import
                </button>
                <template v-if="sheet">
                    <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-rose-600" @click="deleteSheet">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                        Delete sheet
                    </button>
                    <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="exportSheet">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        Export
                    </button>
                    <button type="button" class="btn-primary inline-flex items-center gap-1.5" :disabled="saving" @click="saveMarks">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ saving ? 'Saving...' : 'Save marks' }}
                    </button>
                </template>
            </div>
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
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id" @change="onClassChange">
                            <option :value="null">{{ filters.branch_id ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input" :disabled="!filters.school_class_id" @change="onSectionChange">
                            <option :value="null">{{ filters.school_class_id ? 'Select section' : 'Select class first' }}</option>
                            <option v-for="s in sectionsForClass(filters.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Term</label>
                        <select v-model="filters.term_id" class="form-input" :disabled="!filters.section_id" @change="onTermChange">
                            <option :value="null">All / no term</option>
                            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="filters.exam_id" class="form-input" :disabled="!filters.section_id" @change="loadSheet">
                            <option :value="null">{{ filters.section_id ? 'Select exam' : 'Select section first' }}</option>
                            <option v-for="e in filteredExams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">Session {{ currentSessionName || '—' }}</p>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>

        <div v-else-if="!sheet" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
            Select branch, class, section, and exam to view and enter marks.
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ sheet.label }}</p>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-xs" @click="openAddSubject">
                    <span class="text-base leading-none">+</span> Add subject
                </button>
            </div>

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

                <div v-if="!sheet.students.length" class="px-6 py-16 text-center text-sm text-slate-400">No active students in this class/section.</div>

                <div v-else class="overflow-x-auto">
                    <table v-if="viewMode === 'table'" class="w-full min-w-[860px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('admission_no')">Admission ID {{ sortArrow('admission_no') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('roll_no')">Roll No {{ sortArrow('roll_no') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Student Name {{ sortArrow('name') }}</th>
                                <th v-for="subj in sheet.subjects" :key="subj.id" class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ subj.name }}
                                    <button type="button" class="ml-1 text-rose-400 hover:text-rose-600" title="Remove subject" @click="removeSubject(subj)">×</button>
                                </th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('total')">Total {{ sortArrow('total') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('percentage')">% {{ sortArrow('percentage') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('grade')">Grade {{ sortArrow('grade') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in sortedStudents" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedIds.has(row.id)" @change="toggleOne(row.id, $event.target.checked)" /></td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ row.admission_no }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ row.roll_no ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ row.name }}</td>
                                <td v-for="subj in sheet.subjects" :key="subj.id" class="px-4 py-3">
                                    <input
                                        v-model.number="draft[row.id][subj.subject_id]"
                                        type="number" min="0" :max="subj.max_marks"
                                        class="form-input !w-20 !py-1"
                                        @input="recompute(row.id)"
                                    />
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ computedRows[row.id]?.total ?? 0 }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ computedRows[row.id]?.percentage ?? 0 }}%</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ computedRows[row.id]?.grade || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="row in sortedStudents" :key="row.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ row.name }}</div>
                            <p class="mt-1 text-xs text-slate-400">{{ row.admission_no }} · Roll {{ row.roll_no ?? '—' }}</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Total {{ computedRows[row.id]?.total ?? 0 }} · {{ computedRows[row.id]?.percentage ?? 0 }}% · Grade {{ computedRows[row.id]?.grade || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Add subject modal -->
        <div v-if="addSubjectOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="addSubjectOpen = false" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Add subject</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Subject</label>
                        <select v-model="subjectForm.subject_id" class="form-input">
                            <option :value="null">Select subject</option>
                            <option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Max marks</label>
                        <input v-model.number="subjectForm.max_marks" type="number" min="1" class="form-input" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="addSubjectOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="subjectSaving" @click="saveSubject">{{ subjectSaving ? 'Adding...' : 'Add' }}</button>
                </div>
            </div>
        </div>

        <!-- Download template modal -->
        <div v-if="templateOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="templateOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Download marks template</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Select the exam context. The template includes session, branch, class, section, student roster, and blank subject mark columns.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="templateOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="contextForm.exam_id" class="form-input">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Session year</label>
                        <input :value="currentSessionName" type="text" class="form-input bg-slate-50 dark:bg-slate-800/50" readonly />
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="contextForm.branch_id" class="form-input" @change="contextForm.school_class_id = null; contextForm.section_id = null">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="contextForm.school_class_id" class="form-input" :disabled="!contextForm.branch_id" @change="contextForm.section_id = null">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Section</label>
                        <select v-model="contextForm.section_id" class="form-input" :disabled="!contextForm.school_class_id">
                            <option :value="null">Select section</option>
                            <option v-for="s in sectionsForClass(contextForm.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="templateOpen = false">Cancel</button>
                    <button type="button" class="btn-outline" :disabled="!contextComplete" @click="downloadTemplate('csv')">CSV</button>
                    <button type="button" class="btn-primary inline-flex items-center gap-1.5" :disabled="!contextComplete" @click="downloadTemplate('xlsx')">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h1M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Import modal -->
        <div v-if="importOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="importOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Import marks</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Upload a filled-in marks template (CSV or Excel) for this exam context.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="importOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="contextForm.exam_id" class="form-input">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="contextForm.branch_id" class="form-input" @change="contextForm.school_class_id = null; contextForm.section_id = null">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="contextForm.school_class_id" class="form-input" :disabled="!contextForm.branch_id" @change="contextForm.section_id = null">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="contextForm.section_id" class="form-input" :disabled="!contextForm.school_class_id">
                            <option :value="null">Select section</option>
                            <option v-for="s in sectionsForClass(contextForm.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">File</label>
                        <input ref="importFileInput" type="file" accept=".csv,.xlsx,.xls" class="form-input" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="importOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!contextComplete || importing" @click="runImport">{{ importing ? 'Importing...' : 'Import' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const router = useRouter();

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
const subjects = ref([]);
const exams = ref([]);
const terms = ref([]);
const currentSessionName = ref('');

const filters = reactive({ branch_id: null, school_class_id: null, section_id: null, term_id: null, exam_id: null });
const loading = ref(false);
const sheet = ref(null);
const draft = reactive({});
const computedRows = reactive({});
const saving = ref(false);
const selectedIds = ref(new Set());
const sortKey = ref('roll_no');
const sortDir = ref('asc');

function sectionsForClass(classId) {
    return sections.value.filter((s) => s.school_class_id === classId);
}

const filteredExams = computed(() => {
    if (filters.term_id == null) return exams.value;
    return exams.value.filter((e) => e.academic_term_id === filters.term_id);
});

const sortedStudents = computed(() => {
    if (!sheet.value) return [];
    return [...sheet.value.students].sort((a, b) => {
        let av;
        let bv;
        if (sortKey.value === 'total' || sortKey.value === 'percentage' || sortKey.value === 'grade') {
            av = computedRows[a.id]?.[sortKey.value] ?? 0;
            bv = computedRows[b.id]?.[sortKey.value] ?? 0;
        } else {
            av = a[sortKey.value] ?? '';
            bv = b[sortKey.value] ?? '';
        }
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

function toggleSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = key; sortDir.value = 'asc'; }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}
const allSelected = computed(() => sheet.value?.students.length > 0 && sheet.value.students.every((r) => selectedIds.value.has(r.id)));
function toggleAll(checked) {
    if (!sheet.value) return;
    if (checked) sheet.value.students.forEach((r) => selectedIds.value.add(r.id));
    else selectedIds.value.clear();
}
function toggleOne(id, checked) {
    if (checked) selectedIds.value.add(id);
    else selectedIds.value.delete(id);
}

async function loadLookups() {
    const [academics, sessionsRes, examsRes, termsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/exams'),
        client.get('/exams/terms').catch(() => ({ data: [] })),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    subjects.value = academics.subjects || [];
    exams.value = examsRes.data;
    terms.value = termsRes.data || [];
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
}
loadLookups();

function onBranchChange() {
    filters.school_class_id = null;
    filters.section_id = null;
    filters.term_id = null;
    filters.exam_id = null;
    sheet.value = null;
}
function onClassChange() {
    filters.section_id = null;
    filters.term_id = null;
    filters.exam_id = null;
    sheet.value = null;
}
function onSectionChange() {
    filters.term_id = null;
    filters.exam_id = null;
    sheet.value = null;
}
function onTermChange() {
    filters.exam_id = null;
    sheet.value = null;
}

function recompute(studentId) {
    if (!sheet.value) return;
    const marks = draft[studentId] || {};
    let total = 0;
    let maxTotal = 0;
    let any = false;
    sheet.value.subjects.forEach((subj) => {
        maxTotal += Number(subj.max_marks);
        const v = marks[subj.subject_id];
        if (v !== null && v !== '' && v !== undefined) {
            total += Number(v);
            any = true;
        }
    });
    const percentage = maxTotal > 0 ? Math.round((total / maxTotal) * 10000) / 100 : 0;
    computedRows[studentId] = {
        total: any ? total : 0,
        percentage: any ? percentage : 0,
        grade: computedRows[studentId]?.grade ?? null,
    };
}

async function loadSheet() {
    if (!filters.branch_id || !filters.school_class_id || !filters.section_id || !filters.exam_id) {
        sheet.value = null;
        return;
    }
    loading.value = true;
    try {
        const { data } = await client.get('/exams/marks/sheet', { params: filters });
        sheet.value = data;
        Object.keys(draft).forEach((k) => delete draft[k]);
        Object.keys(computedRows).forEach((k) => delete computedRows[k]);
        data.students.forEach((row) => {
            draft[row.id] = { ...row.marks };
            computedRows[row.id] = { total: row.total, percentage: row.percentage, grade: row.grade };
        });
        selectedIds.value.clear();
    } finally {
        loading.value = false;
    }
}

async function saveMarks() {
    if (!sheet.value) return;
    saving.value = true;
    try {
        const records = sheet.value.students.map((row) => ({
            student_id: row.id,
            marks: draft[row.id] || {},
        }));
        await client.post('/exams/marks/save', {
            exam_id: filters.exam_id,
            school_class_id: filters.school_class_id,
            records,
        });
        pushToast('Marks saved.', 'success');
        await loadSheet();
    } finally {
        saving.value = false;
    }
}

async function deleteSheet() {
    if (!window.confirm('Delete this entire marks sheet? This removes every subject and mark entered for this exam and class (all sections).')) return;
    await client.delete('/exams/marks/sheet', { data: { exam_id: filters.exam_id, school_class_id: filters.school_class_id } });
    pushToast('Marks sheet deleted.', 'success');
    await loadSheet();
}

async function exportSheet() {
    const response = await client.get('/exams/marks/export', { params: filters, responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'marks-export.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// --- Add subject ---
const addSubjectOpen = ref(false);
const subjectSaving = ref(false);
const subjectForm = reactive({ subject_id: null, max_marks: 100 });
const availableSubjects = computed(() => {
    if (!sheet.value) return subjects.value;
    const used = new Set(sheet.value.subjects.map((s) => s.subject_id));
    return subjects.value.filter((s) => !used.has(s.id));
});

function defaultMaxForSelectedExam() {
    const exam = exams.value.find((e) => e.id === filters.exam_id);
    const fromPolicy = Number(exam?.max_marks || exam?.total_marks || 0);
    return fromPolicy > 0 ? fromPolicy : 100;
}

function openAddSubject() {
    Object.assign(subjectForm, { subject_id: null, max_marks: defaultMaxForSelectedExam() });
    addSubjectOpen.value = true;
}

async function saveSubject() {
    if (!subjectForm.subject_id) {
        pushToast('Select a subject.', 'error');
        return;
    }
    subjectSaving.value = true;
    try {
        await client.post('/exams/marks/subjects', {
            exam_id: filters.exam_id,
            school_class_id: filters.school_class_id,
            subject_id: subjectForm.subject_id,
            max_marks: subjectForm.max_marks,
        });
        pushToast('Subject added.', 'success');
        addSubjectOpen.value = false;
        await loadSheet();
    } finally {
        subjectSaving.value = false;
    }
}

async function removeSubject(subj) {
    if (!window.confirm(`Remove "${subj.name}" from this sheet? Any marks entered for it will be deleted.`)) return;
    await client.delete(`/exams/marks/subjects/${subj.id}`);
    pushToast('Subject removed.', 'success');
    await loadSheet();
}

// --- Context modal (shared shape by Template + Import) ---
const contextForm = reactive({ exam_id: null, branch_id: null, school_class_id: null, section_id: null });
const contextComplete = computed(() => contextForm.exam_id && contextForm.branch_id && contextForm.school_class_id && contextForm.section_id);

function syncContextFromFilters() {
    Object.assign(contextForm, { ...filters });
}

const templateOpen = ref(false);
function openTemplateModal() {
    syncContextFromFilters();
    templateOpen.value = true;
}

async function downloadTemplate(format) {
    const response = await client.get('/exams/marks/template', { params: { ...contextForm, format }, responseType: 'blob' });
    const mime = format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';
    const url = URL.createObjectURL(new Blob([response.data], { type: mime }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `marks-template.${format}`;
    a.click();
    URL.revokeObjectURL(url);
    templateOpen.value = false;
}

const importOpen = ref(false);
const importing = ref(false);
const importFileInput = ref(null);
function openImportModal() {
    syncContextFromFilters();
    importOpen.value = true;
}

async function runImport() {
    const file = importFileInput.value?.files?.[0];
    if (!file) {
        pushToast('Choose a file to import.', 'error');
        return;
    }
    importing.value = true;
    try {
        const form = new FormData();
        Object.entries(contextForm).forEach(([k, v]) => { if (v !== null) form.append(k, v); });
        form.append('file', file);
        const { data } = await client.post('/exams/marks/import', form);
        pushToast(`Imported ${data.imported} student(s)${data.skipped ? `, skipped ${data.skipped} unmatched row(s).` : '.'}`, 'success');
        importOpen.value = false;
        if (JSON.stringify(contextForm) === JSON.stringify(filters)) {
            await loadSheet();
        }
    } finally {
        importing.value = false;
    }
}
</script>
