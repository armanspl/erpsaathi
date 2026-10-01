<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Class Routine</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Class Routine']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Build the weekly class timetable, import it from an Excel sheet, or export it with the same colours.</p>
            </div>
            <div v-if="view === 'list'" class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openImportModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Import Excel
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Routine
                </button>
            </div>
        </div>

        <!-- LIST VIEW -->
        <template v-if="view === 'list'">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="sheetsLoading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!sheets.length" class="px-6 py-20 text-center text-sm text-slate-400">No class routine yet — create one or import an Excel file.</div>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entries</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in sheets" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.title || 'Class Routine' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.academic_session?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.branch?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.entries_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(s.updated_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Open" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Export" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openExportModal(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- EDIT VIEW -->
        <template v-else>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-xs" @click="closeEdit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editingSheet ? 'Edit Class Routine' : 'Create Class Routine' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ sheetSubtitle }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="editingSheet" type="button" class="btn-outline !text-xs" @click="openImportModal">Re-import Excel</button>
                        <button v-if="editingSheet" type="button" class="btn-outline !text-xs" @click="openExportModal(editingSheet)">Export ▾</button>
                        <button type="button" class="btn-primary" :disabled="sheetSaving" @click="saveSheet">{{ sheetSaving ? 'Saving...' : 'Save' }}</button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="form-label">Academic Session</label>
                        <select v-model.number="form.academic_session_id" class="form-input" :disabled="!!editingSheet">
                            <option :value="null">Select session</option>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model.number="form.branch_id" class="form-input" :disabled="!!editingSheet">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Title</label>
                        <input v-model="form.title" type="text" class="form-input" placeholder="e.g. Class Routine 2026-27" />
                    </div>
                    <div>
                        <label class="form-label">Periods / day</label>
                        <input v-model.number="form.periods_per_day" type="number" min="1" max="20" class="form-input" @change="onPeriodsChange" />
                    </div>
                </div>
            </div>

            <div v-if="importWarnings" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Review before saving — this routine was pre-filled from an Excel import</p>
                    <button type="button" class="shrink-0 text-xs font-medium text-amber-700 hover:underline dark:text-amber-400" @click="importWarnings = null">Dismiss</button>
                </div>
                <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300">
                    <li v-if="importWarnings.sheets_skipped?.length">Sheet(s) not imported (not a Mon.../Thu... day-group grid): {{ importWarnings.sheets_skipped.join(', ') }}</li>
                    <li v-if="importWarnings.unmatched_classes?.length">These class labels don't match any class in your system, so their rows were skipped: {{ importWarnings.unmatched_classes.join(', ') }}. Add the class first, then re-import.</li>
                    <li v-if="importWarnings.unmatched_subjects?.length">{{ importWarnings.unmatched_subjects.length }} subject text(s) couldn't be matched to your subject list — shown as plain text in the grid (marked ⚠), pick the right one from the dropdown or add it under Academics → Subjects: {{ importWarnings.unmatched_subjects.join(', ') }}</li>
                    <li v-if="importWarnings.unmatched_teachers?.length">{{ importWarnings.unmatched_teachers.length }} teacher name(s) couldn't be matched to your teacher list — shown as plain text (marked ⚠), pick the right one or add them under People → Teachers: {{ importWarnings.unmatched_teachers.join(', ') }}</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <nav class="flex flex-wrap gap-1">
                        <button
                            v-for="d in DAYS"
                            :key="d.dow"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeDay === d.dow ? 'bg-primary-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                            @click="activeDay = d.dow"
                        >
                            {{ d.label }}
                        </button>
                    </nav>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-slate-400">Copy {{ dayLabel(activeDay) }} to:</span>
                        <select v-model="copyTargetDay" class="form-input !py-1 !text-xs">
                            <option :value="null">Select day</option>
                            <option v-for="d in DAYS.filter((x) => x.dow !== activeDay)" :key="d.dow" :value="d.dow">{{ d.label }}</option>
                        </select>
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="!copyTargetDay" @click="copyDay">Copy</button>
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full border-collapse text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Class</th>
                                <th v-for="p in periodsRange" :key="p" class="whitespace-nowrap px-2 py-2 text-center font-semibold text-slate-500">P{{ p }}</th>
                                <th class="whitespace-nowrap px-2 py-2 font-semibold text-slate-500">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!classes.length">
                                <td :colspan="periodsRange.length + 2" class="px-3 py-6 text-center text-slate-400">No classes found — add classes under Academics → Classes &amp; Sections first.</td>
                            </tr>
                            <tr v-for="c in classes" :key="c.id">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 align-top font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ c.name }}</td>
                                <td v-for="p in periodsRange" :key="p" class="min-w-[130px] px-1.5 py-1.5 align-top">
                                    <div
                                        class="space-y-1 rounded-lg border p-1.5"
                                        :class="cellHasUnmatched(c.id, p) ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'"
                                    >
                                        <select v-model="cell(c.id, p).subject_id" class="form-input !py-1 !text-[11px]" @change="cell(c.id, p).subject_label = null">
                                            <option :value="null">{{ cell(c.id, p).subject_label ? `⚠ ${cell(c.id, p).subject_label}` : '— subject —' }}</option>
                                            <option v-for="sub in subjects" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                                        </select>
                                        <select v-model="cell(c.id, p).teacher_id" class="form-input !py-1 !text-[11px]" @change="onTeacherPick(c.id, p)">
                                            <option :value="null">{{ cell(c.id, p).teacher_label ? `⚠ ${cell(c.id, p).teacher_label}` : '— teacher —' }}</option>
                                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                        <div v-if="cell(c.id, p).teacher_id || cell(c.id, p).teacher_label" class="flex items-center gap-1">
                                            <input v-model="cell(c.id, p).color" type="color" class="h-5 w-6 shrink-0 cursor-pointer rounded border-0 p-0" title="Badge colour for this cell" />
                                            <span
                                                class="flex-1 truncate rounded px-1 py-0.5 text-center text-[10px] font-semibold"
                                                :style="{ background: cell(c.id, p).color || '#e2e8f0', color: contrastColor(cell(c.id, p).color) }"
                                            >{{ teacherLabelFor(c.id, p) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="min-w-[140px] px-1.5 py-1.5 align-top">
                                    <input v-model="classRemarks[c.id]" type="text" class="form-input !py-1 !text-[11px]" placeholder="Remarks" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Import Excel modal -->
        <div v-if="importModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Import Class Routine from Excel</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Upload a workbook with a sheet named starting "MON" (applied to Mon/Tue/Wed) and/or "THU" (applied to Thu/Fri/Sat) — same layout as the school's printed routine. It's parsed and used to pre-fill the grid below for review — nothing is saved until you click Save there.
                </p>

                <div v-if="view === 'list'" class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Academic Session</label>
                        <select v-model.number="importForm.academic_session_id" class="form-input">
                            <option :value="null">Select session</option>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model.number="importForm.branch_id" class="form-input">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="form-label">Select / Upload Excel File</label>
                    <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onImportFileSelected" />
                </div>

                <p v-if="importError" class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">{{ importError }}</p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" class="btn-outline" :disabled="downloadingTemplate" @click="downloadTemplate">
                        {{ downloadingTemplate ? 'Downloading...' : 'Download Template' }}
                    </button>
                    <button type="button" class="btn-outline flex-1" :disabled="importing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="btn-primary flex-1" :disabled="!canRunImport || importing" @click="runImport">
                        {{ importing ? 'Parsing...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Export format modal -->
        <div v-if="exportModalSheet" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="closeExportModal">
            <div class="w-full max-w-xs rounded-xl bg-white p-5 shadow-2xl dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Export Class Routine</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose a format to download.</p>
                <div class="mt-4 space-y-2">
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('xlsx')">
                        📊 Excel (.xlsx) <span class="ml-auto text-[10px] text-slate-400">same colours</span>
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('pdf')">
                        📄 PDF
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('csv')">
                        🧾 CSV
                    </button>
                </div>
                <button type="button" class="btn-outline mt-4 w-full !text-xs" @click="closeExportModal">Cancel</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { triggerBlobDownload } from '../../utils/documentPdf';
import { downloadImportTemplate } from '../../utils/downloadExport';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();

const DAYS = [
    { dow: 1, label: 'Monday' },
    { dow: 2, label: 'Tuesday' },
    { dow: 3, label: 'Wednesday' },
    { dow: 4, label: 'Thursday' },
    { dow: 5, label: 'Friday' },
    { dow: 6, label: 'Saturday' },
];

const view = ref('list');
const sheets = ref([]);
const sheetsLoading = ref(true);
const sessions = ref([]);
const branches = ref([]);
const classes = ref([]);
const subjects = ref([]);
const teachers = ref([]);
const currentSessionName = ref('');

const editingSheet = ref(null);
const sheetSaving = ref(false);
const form = reactive({ academic_session_id: null, branch_id: null, title: '', periods_per_day: 7 });
// grid[dow][classId][period] = { subject_id, subject_label, teacher_id, teacher_label, color, remarks }
const grid = reactive({});
const classRemarks = reactive({});
const activeDay = ref(1);
const copyTargetDay = ref(null);

const periodsRange = computed(() => Array.from({ length: form.periods_per_day || 0 }, (_, i) => i + 1));

function blankCell() {
    return { subject_id: null, subject_label: null, teacher_id: null, teacher_label: null, color: null };
}

function ensureDay(dow) {
    grid[dow] ??= {};
    classes.value.forEach((c) => {
        grid[dow][c.id] ??= {};
        periodsRange.value.forEach((p) => {
            grid[dow][c.id][p] ??= blankCell();
        });
    });
}
function ensureAllDays() {
    DAYS.forEach((d) => ensureDay(d.dow));
}

function cell(classId, period) {
    ensureDay(activeDay.value);
    grid[activeDay.value][classId] ??= {};
    grid[activeDay.value][classId][period] ??= blankCell();
    return grid[activeDay.value][classId][period];
}

function cellHasUnmatched(classId, period) {
    const c = grid[activeDay.value]?.[classId]?.[period];
    return !!(c && (c.subject_label || c.teacher_label));
}

function teacherLabelFor(classId, period) {
    const c = cell(classId, period);
    if (c.teacher_id) return teachers.value.find((t) => t.id === c.teacher_id)?.name || '';
    return c.teacher_label || '';
}

function onTeacherPick(classId, period) {
    const c = cell(classId, period);
    c.teacher_label = null;
    if (c.teacher_id) {
        const t = teachers.value.find((x) => x.id === c.teacher_id);
        if (t?.color && !c.color) c.color = t.color;
    }
}

function contrastColor(hex) {
    if (!hex) return '#1e293b';
    const h = hex.replace('#', '');
    if (h.length !== 6) return '#1e293b';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.6 ? '#000000' : '#ffffff';
}

function dayLabel(dow) {
    return DAYS.find((d) => d.dow === dow)?.label || '';
}

function onPeriodsChange() {
    if (!form.periods_per_day || form.periods_per_day < 1) form.periods_per_day = 1;
    ensureAllDays();
}

function copyDay() {
    if (!copyTargetDay.value) return;
    ensureDay(activeDay.value);
    ensureDay(copyTargetDay.value);
    grid[copyTargetDay.value] = JSON.parse(JSON.stringify(grid[activeDay.value]));
    pushToast(`Copied ${dayLabel(activeDay.value)} to ${dayLabel(copyTargetDay.value)}.`, 'success');
    copyTargetDay.value = null;
}

const sheetSubtitle = computed(() => {
    const session = sessions.value.find((s) => s.id === form.academic_session_id);
    const branch = branches.value.find((b) => b.id === form.branch_id);
    const parts = [session?.name, branch?.name].filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Pick a session and branch to get started.';
});

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadLookups() {
    const [academics, sessionsRes, routineLookups] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/academics/class-routine/lookups'),
    ]);
    branches.value = academics.branches || [];
    classes.value = (academics.classes || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
    subjects.value = academics.subjects || [];
    teachers.value = routineLookups.data.teachers || [];
    sessions.value = sessionsRes.data;
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
}

async function loadSheets() {
    sheetsLoading.value = true;
    try {
        await loadLookups();
        const { data } = await client.get('/academics/class-routine');
        sheets.value = data;
    } finally {
        sheetsLoading.value = false;
    }
}

function resetForm() {
    Object.assign(form, {
        academic_session_id: sessions.value.find((s) => s.is_current)?.id || null,
        branch_id: branches.value.length === 1 ? branches.value[0].id : null,
        title: '',
        periods_per_day: 7,
    });
    Object.keys(grid).forEach((k) => delete grid[k]);
    Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
    ensureAllDays();
}

function openCreate() {
    editingSheet.value = null;
    resetForm();
    importWarnings.value = null;
    activeDay.value = 1;
    view.value = 'edit';
}

function daysFromServer(daysData) {
    Object.keys(grid).forEach((k) => delete grid[k]);
    Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
    ensureAllDays();
    (daysData || []).forEach((day) => {
        (day.entries || []).forEach((entry) => {
            grid[day.day_of_week] ??= {};
            grid[day.day_of_week][entry.school_class_id] ??= {};
            grid[day.day_of_week][entry.school_class_id][entry.period_number] = {
                subject_id: entry.subject_id,
                subject_label: entry.subject_label,
                teacher_id: entry.teacher_id,
                teacher_label: entry.teacher_label,
                color: entry.color,
            };
            if (entry.remarks) classRemarks[entry.school_class_id] = entry.remarks;
        });
    });
}

async function openSheet(sheet) {
    editingSheet.value = sheet;
    sheetsLoading.value = true;
    try {
        await loadLookups();
        const { data } = await client.get(`/academics/class-routine/${sheet.id}`);
        Object.assign(form, {
            academic_session_id: data.academic_session_id,
            branch_id: data.branch_id,
            title: data.title || '',
            periods_per_day: data.periods_per_day || 7,
        });
        ensureAllDays();
        daysFromServer(data.days);
        importWarnings.value = null;
        activeDay.value = 1;
        view.value = 'edit';
    } finally {
        sheetsLoading.value = false;
    }
}

function closeEdit() {
    view.value = 'list';
    editingSheet.value = null;
    importWarnings.value = null;
    loadSheets();
}

async function saveSheet() {
    if (!form.academic_session_id || !form.branch_id) {
        pushToast('Select an academic session and branch.', 'error');
        return;
    }
    sheetSaving.value = true;
    try {
        let sheetId = editingSheet.value?.id;
        if (!sheetId) {
            const { data } = await client.post('/academics/class-routine', {
                academic_session_id: form.academic_session_id,
                branch_id: form.branch_id,
                title: form.title || null,
                periods_per_day: form.periods_per_day,
            });
            sheetId = data.id;
            editingSheet.value = data;
        }

        const days = DAYS.map((d) => ({
            day_of_week: d.dow,
            entries: classes.value.flatMap((c) => periodsRange.value.map((p) => {
                const cellData = grid[d.dow]?.[c.id]?.[p];
                if (!cellData) return null;
                if (!cellData.subject_id && !cellData.subject_label && !cellData.teacher_id && !cellData.teacher_label) return null;
                return {
                    school_class_id: c.id,
                    period_number: p,
                    subject_id: cellData.subject_id || null,
                    subject_label: cellData.subject_id ? null : cellData.subject_label,
                    teacher_id: cellData.teacher_id || null,
                    teacher_label: cellData.teacher_id ? null : cellData.teacher_label,
                    color: cellData.color || null,
                    remarks: classRemarks[c.id] || null,
                };
            }).filter(Boolean)),
        }));

        await client.put(`/academics/class-routine/${sheetId}`, {
            title: form.title || null,
            periods_per_day: form.periods_per_day,
            days,
        });
        pushToast('Class routine saved.', 'success');
        closeEdit();
    } finally {
        sheetSaving.value = false;
    }
}

async function removeSheet(sheet) {
    if (!window.confirm('Delete this class routine?')) return;
    sheets.value = sheets.value.filter((s) => s.id !== sheet.id);
    await client.delete(`/academics/class-routine/${sheet.id}`);
    pushToast('Class routine deleted.', 'success');
}

const EXPORT_EXTENSIONS = { xlsx: 'xlsx', pdf: 'pdf', csv: 'csv' };
async function downloadRoutine(sheet, format = 'xlsx') {
    try {
        const response = await client.get(`/academics/class-routine/${sheet.id}/export`, {
            params: { format },
            responseType: 'blob',
        });
        triggerBlobDownload(response.data, `class-routine-${sheet.id}.${EXPORT_EXTENSIONS[format] || 'xlsx'}`);
        pushToast(`Exported as ${format.toUpperCase()}.`, 'success');
    } catch {
        pushToast('Could not export this routine.', 'error');
    }
}

// A dropdown panel positioned relative to its trigger button got silently clipped by the sheets
// table's `overflow-hidden` card — nothing appeared to click. A modal has no clipping ancestor to
// worry about, so both the list row's export icon and the edit view's Export button open this
// same one instead.
const exportModalSheet = ref(null);
function openExportModal(sheet) {
    exportModalSheet.value = sheet;
}
function closeExportModal() {
    exportModalSheet.value = null;
}
function chooseExportFormat(format) {
    if (exportModalSheet.value) downloadRoutine(exportModalSheet.value, format);
    closeExportModal();
}

const downloadingTemplate = ref(false);
async function downloadTemplate() {
    downloadingTemplate.value = true;
    try {
        await downloadImportTemplate('class-routine');
        pushToast('Template downloaded.', 'success');
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not download template.', 'error');
    } finally {
        downloadingTemplate.value = false;
    }
}

// --- Import ---
const importModalOpen = ref(false);
const importFileInput = ref(null);
const importFile = ref(null);
const importing = ref(false);
const importForm = reactive({ academic_session_id: null, branch_id: null });
const importError = ref('');
const importWarnings = ref(null);
const canRunImport = computed(() => {
    if (!importFile.value) return false;
    if (view.value === 'edit') return true;
    return !!(importForm.academic_session_id && importForm.branch_id);
});

function openImportModal() {
    importForm.academic_session_id = view.value === 'edit' ? form.academic_session_id : (sessions.value.find((s) => s.is_current)?.id || null);
    importForm.branch_id = view.value === 'edit' ? form.branch_id : (branches.value.length === 1 ? branches.value[0].id : null);
    importFile.value = null;
    importError.value = '';
    importModalOpen.value = true;
}
function closeImportModal() {
    importModalOpen.value = false;
    if (importFileInput.value) importFileInput.value.value = '';
}
function onImportFileSelected(event) {
    importFile.value = event.target.files?.[0] || null;
    importError.value = '';
}

async function runImport() {
    if (!canRunImport.value) return;
    importing.value = true;
    importError.value = '';
    try {
        const body = new FormData();
        body.append('file', importFile.value);
        const { data } = await client.post('/academics/class-routine/import-preview', body);

        if (view.value !== 'edit') {
            editingSheet.value = null;
            resetForm();
            form.academic_session_id = importForm.academic_session_id;
            form.branch_id = importForm.branch_id;
        }

        Object.keys(grid).forEach((k) => delete grid[k]);
        Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
        ensureAllDays();
        daysFromServer(data.days);

        // Backfill any still-colourless matched teacher's badge colour from the legend, purely
        // client-side preview convenience — the real backfill happens server-side on Save.
        Object.entries(data.teacher_colors || {}).forEach(([rawName, hex]) => {
            const t = teachers.value.find((x) => x.name.toUpperCase().includes(rawName.toUpperCase()));
            if (t && !t.color) t.color = hex;
        });

        importWarnings.value = data.warnings;
        activeDay.value = 1;
        view.value = 'edit';
        closeImportModal();
        const totalEntries = (data.days || []).reduce((sum, d) => sum + d.entries.length, 0);
        pushToast(`Parsed ${totalEntries} routine entries across ${data.days.length} day(s) — review the warnings below before saving.`, 'success');
    } catch (e) {
        importError.value = e?.response?.data?.message || 'Could not parse this file.';
    } finally {
        importing.value = false;
    }
}

// Reached from Import & Export → Class Routine Import/Export (that hub only lists a link here,
// since a routine's import/export needs a session+branch picked and a review grid, not a blind
// one-shot upload) — auto-open the matching action once the list has loaded.
onMounted(async () => {
    await loadSheets();
    if (route.query.action === 'import') {
        openImportModal();
    } else if (route.query.action === 'export' && sheets.value.length === 1) {
        downloadRoutine(sheets.value[0], 'xlsx');
    }
    if (route.query.action) {
        router.replace({ path: route.path, query: {} });
    }
});
</script>
