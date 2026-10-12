<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Co-Scholastic Areas</h1>
                <Breadcrumb :items="['Dashboard', 'Exam Management', 'Co-Scholastic Areas']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Areas and grades set here print in the <b>CO-SCHOLASTIC AREAS</b> table of the result PDF
                    for session <b>{{ erpStore.currentSession || 'current' }}</b>.
                </p>
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Area</button>
        </div>

        <!-- Areas -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Areas</div>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Area</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Show in PDF</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grades set (this session)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="areasLoading">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!areas.length">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">No areas yet. Add one to show it on the result PDF.</td>
                    </tr>
                    <tr v-for="a in areas" :key="a.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-500">{{ a.sort_order }}</td>
                        <td class="px-4 py-3 font-semibold uppercase text-slate-800 dark:text-slate-100">{{ a.name }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="a.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'"
                                @click="toggleActive(a)"
                            >{{ a.is_active ? 'Shown' : 'Hidden' }}</button>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ a.grades_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(a)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeArea(a)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Grades -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Set Grades (class-wise)</div>
            <div class="grid gap-4 px-5 py-4" :class="branches.length > 1 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                <div v-if="branches.length > 1">
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input" @change="loadGrades">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="filters.school_class_id" class="form-input" @change="onClassChange">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="filters.section_id" class="form-input" :disabled="!filters.school_class_id" @change="loadGrades">
                        <option :value="null">All sections</option>
                        <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="!filters.school_class_id" class="border-t border-slate-100 px-6 py-12 text-center text-sm text-slate-400 dark:border-slate-800">
                Select a class to set grades.
            </div>
            <div v-else-if="gradesLoading" class="border-t border-slate-100 px-6 py-12 text-center text-sm text-slate-400 dark:border-slate-800">Loading...</div>
            <div v-else-if="!students.length" class="border-t border-slate-100 px-6 py-12 text-center text-sm text-slate-400 dark:border-slate-800">No active students in this class.</div>
            <div v-else-if="!activeAreas.length" class="border-t border-slate-100 px-6 py-12 text-center text-sm text-slate-400 dark:border-slate-800">No areas are shown in the PDF. Add or show an area above.</div>
            <template v-else>
                <div class="overflow-x-auto border-t border-slate-100 dark:border-slate-800">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adm No</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                                <th v-for="a in activeAreas" :key="a.id" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <div>{{ a.name }}</div>
                                    <div class="mt-1 flex items-center gap-1 normal-case">
                                        <input v-model="fillAll[a.id]" :list="datalistId" class="form-input !h-7 w-16 !px-2 !py-0 text-xs" placeholder="All" />
                                        <button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-white dark:border-slate-600 dark:text-slate-300" @click="applyToAll(a.id)">Set all</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="s in students" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-2 text-slate-500">{{ s.roll_no || '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ s.admission_no }}</td>
                                <td class="px-4 py-2 font-medium text-slate-800 dark:text-slate-100">
                                    {{ s.name }}
                                    <span v-if="s.section" class="ml-1 text-xs text-slate-400">({{ s.section }})</span>
                                </td>
                                <td v-for="a in activeAreas" :key="a.id" class="px-3 py-2">
                                    <input
                                        v-model="draft[cellKey(s.id, a.id)]"
                                        :list="datalistId"
                                        class="form-input !h-8 w-20 !px-2 !py-0 uppercase"
                                        :class="isDirty(s.id, a.id) ? '!border-amber-400 !bg-amber-50 dark:!bg-amber-900/20' : ''"
                                        placeholder="—"
                                        maxlength="16"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-sm dark:border-slate-800">
                    <span class="text-slate-500">
                        {{ students.length }} students · {{ dirtyCount }} unsaved change{{ dirtyCount === 1 ? '' : 's' }}.
                        Empty = not set (built-in areas use the automatic grade).
                    </span>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" :disabled="!dirtyCount || savingGrades" @click="resetDraft">Undo changes</button>
                        <button type="button" class="btn-primary" :disabled="!dirtyCount || savingGrades" @click="saveGrades">
                            {{ savingGrades ? 'Saving...' : 'Save grades' }}
                        </button>
                    </div>
                </div>
            </template>
            <datalist :id="datalistId">
                <option v-for="g in gradeOptions" :key="g" :value="g" />
            </datalist>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Area' : 'Add Area'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Area name</label>
                <input v-model="form.name" type="text" class="form-input" placeholder="Work Education, Music, Discipline..." maxlength="100" />
            </div>
            <div>
                <label class="form-label">Order in PDF</label>
                <input v-model.number="form.sort_order" type="number" min="0" class="form-input" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                Show in result PDF
            </label>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="savingArea || !form.name.trim()" @click="saveArea">{{ savingArea ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const DEFAULT_GRADES = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'E'];
const datalistId = 'co-scholastic-grade-options';

const areas = ref([]);
const areasLoading = ref(true);
const drawerOpen = ref(false);
const editing = ref(null);
const savingArea = ref(false);
const form = reactive({ name: '', sort_order: null, is_active: true });

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const filters = reactive({ branch_id: null, school_class_id: null, section_id: null });
const students = ref([]);
const gradeOptions = ref(DEFAULT_GRADES);
const gradesLoading = ref(false);
const savingGrades = ref(false);
const draft = reactive({});
const original = reactive({});
const fillAll = reactive({});

const activeAreas = computed(() => areas.value.filter((a) => a.is_active));
const sectionsForClass = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));

const errMsg = (e, fallback) => e?.response?.data?.message || e?.message || fallback;
const cellKey = (studentId, areaId) => `${studentId}|${areaId}`;
const norm = (v) => String(v ?? '').trim().toUpperCase();
const isDirty = (studentId, areaId) => norm(draft[cellKey(studentId, areaId)]) !== norm(original[cellKey(studentId, areaId)]);
const dirtyCount = computed(() => Object.keys(draft).filter((k) => norm(draft[k]) !== norm(original[k])).length);

async function loadAreas() {
    areasLoading.value = true;
    try {
        const { data } = await client.get('/exams/co-scholastic/areas');
        areas.value = data;
    } catch (e) {
        pushToast(errMsg(e, 'Could not load areas.'), 'error');
    } finally {
        areasLoading.value = false;
    }
}

async function loadLookups() {
    const academics = await fetchAcademicsLookups();
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
}

function clearGrid() {
    students.value = [];
    for (const obj of [draft, original, fillAll]) Object.keys(obj).forEach((k) => delete obj[k]);
}

async function loadGrades() {
    if (!filters.school_class_id) {
        clearGrid();
        return;
    }
    gradesLoading.value = true;
    try {
        const { data } = await client.get('/exams/co-scholastic/grades', {
            params: {
                school_class_id: filters.school_class_id,
                section_id: filters.section_id || undefined,
                branch_id: filters.branch_id || undefined,
            },
        });
        clearGrid();
        students.value = data.students || [];
        const fromSystem = (data.grade_options || []).filter(Boolean);
        gradeOptions.value = [...new Set([...fromSystem, ...DEFAULT_GRADES])];
        for (const s of students.value) {
            for (const a of areas.value) {
                const k = cellKey(s.id, a.id);
                const g = s.grades?.[a.id] ?? '';
                original[k] = g;
                draft[k] = g;
            }
        }
    } catch (e) {
        pushToast(errMsg(e, 'Could not load students.'), 'error');
    } finally {
        gradesLoading.value = false;
    }
}

function onClassChange() {
    filters.section_id = null;
    loadGrades();
}

function applyToAll(areaId) {
    const value = norm(fillAll[areaId]);
    for (const s of students.value) draft[cellKey(s.id, areaId)] = value;
}

function resetDraft() {
    Object.keys(original).forEach((k) => { draft[k] = original[k]; });
}

async function saveGrades() {
    const records = Object.keys(draft)
        .filter((k) => norm(draft[k]) !== norm(original[k]))
        .map((k) => {
            const [studentId, areaId] = k.split('|').map(Number);
            return { student_id: studentId, area_id: areaId, grade: norm(draft[k]) };
        });
    if (!records.length) return;
    savingGrades.value = true;
    try {
        await client.post('/exams/co-scholastic/grades', { records });
        pushToast(`Saved ${records.length} grade${records.length === 1 ? '' : 's'}.`, 'success');
        await Promise.all([loadGrades(), loadAreas()]);
    } catch (e) {
        pushToast(errMsg(e, 'Could not save grades.'), 'error');
    } finally {
        savingGrades.value = false;
    }
}

function openAdd() {
    editing.value = null;
    const next = areas.value.reduce((m, a) => Math.max(m, Number(a.sort_order) || 0), 0) + 1;
    Object.assign(form, { name: '', sort_order: next, is_active: true });
    drawerOpen.value = true;
}

function openEdit(area) {
    editing.value = area;
    Object.assign(form, { name: area.name, sort_order: area.sort_order, is_active: !!area.is_active });
    drawerOpen.value = true;
}

async function saveArea() {
    savingArea.value = true;
    try {
        const payload = { name: form.name.trim(), sort_order: form.sort_order ?? 0, is_active: !!form.is_active };
        if (editing.value) {
            await client.put(`/exams/co-scholastic/areas/${editing.value.id}`, payload);
            pushToast('Area updated.', 'success');
        } else {
            await client.post('/exams/co-scholastic/areas', payload);
            pushToast('Area added.', 'success');
        }
        drawerOpen.value = false;
        await loadAreas();
        if (filters.school_class_id) await loadGrades();
    } catch (e) {
        pushToast(errMsg(e, 'Could not save area.'), 'error');
    } finally {
        savingArea.value = false;
    }
}

async function toggleActive(area) {
    try {
        await client.put(`/exams/co-scholastic/areas/${area.id}`, { name: area.name, sort_order: area.sort_order, is_active: !area.is_active });
        await loadAreas();
    } catch (e) {
        pushToast(errMsg(e, 'Could not update area.'), 'error');
    }
}

async function removeArea(area) {
    const extra = area.grades_count ? ` Its ${area.grades_count} saved grade(s) for this session (and any other session) will also be removed.` : '';
    if (!window.confirm(`Delete area "${area.name}"?${extra}\n\nTip: use "Hidden" instead to keep the grades but hide it from the PDF.`)) return;
    try {
        await client.delete(`/exams/co-scholastic/areas/${area.id}`);
        pushToast(`Area "${area.name}" deleted.`, 'success');
        await loadAreas();
        if (filters.school_class_id) await loadGrades();
    } catch (e) {
        pushToast(errMsg(e, 'Could not delete area.'), 'error');
    }
}

loadAreas();
loadLookups().catch((e) => pushToast(errMsg(e, 'Lookup failed'), 'error'));

watch(() => erpStore.currentSession, () => {
    loadAreas();
    loadGrades();
});
</script>
