<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Annual Report Card</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Combines all terms and assessments for the session. Configure terms under Exam Management &rarr; Terms.
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 px-5 py-4 sm:grid-cols-3">
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
                    <select v-model="filters.section_id" class="form-input" :disabled="!filters.branch_id" @change="loadResults">
                        <option :value="null">All sections</option>
                        <option v-for="s in sectionsForClass(filters.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                <button type="button" class="btn-outline" :disabled="!filters.branch_id || loading" @click="loadResults">
                    {{ loading ? 'Loading...' : 'Load results' }}
                </button>
                <button type="button" class="btn-primary" :disabled="!rows.length || zipping" @click="downloadZip">
                    {{ zipping ? 'Zipping...' : 'Download all ZIP' }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!rows.length" class="px-6 py-16 text-center text-sm text-slate-400">
                {{ filters.branch_id ? 'No annual results. Ensure terms have assessments and marks.' : 'Select a branch to load the annual report.' }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adm No</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Overall</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">%</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Rank</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in rows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500">{{ r.roll_no ?? '\u2014' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 font-semibold">{{ r.obtained }} / {{ r.max_total }}</td>
                            <td class="px-4 py-3">{{ r.percentage }}%</td>
                            <td class="px-4 py-3">{{ r.grade || '\u2014' }}</td>
                            <td class="px-4 py-3">{{ r.rank ?? '\u2014' }}</td>
                            <td class="px-4 py-3">
                                <input
                                    :value="r.remarks || ''"
                                    type="text"
                                    class="form-input min-w-[10rem] text-xs"
                                    placeholder="Remarks"
                                    @change="saveRemark(r, $event.target.value)"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="text-sm font-medium text-primary-600 hover:underline" @click="downloadStudent(r)">PDF</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="rows.length && terms.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Co-Scholastic (selected student)</h2>
            <p class="mt-1 text-xs text-slate-500">Pick a student, then enter Term grades for Work Education / Drawing / Sports.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">Student</label>
                    <select v-model="coStudentId" class="form-input">
                        <option :value="null">Select student</option>
                        <option v-for="r in rows" :key="r.student_id" :value="r.student_id">{{ r.name }} ({{ r.admission_no }})</option>
                    </select>
                </div>
            </div>
            <div v-if="coStudentId" class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800">
                            <th class="px-2 py-2 text-left text-xs uppercase text-slate-500">Area</th>
                            <th v-for="t in terms" :key="t.id" class="px-2 py-2 text-left text-xs uppercase text-slate-500">{{ t.name }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="area in coAreas" :key="area.key" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="px-2 py-2 font-medium">{{ area.label }}</td>
                            <td v-for="t in terms" :key="t.id" class="px-2 py-2">
                                <input
                                    v-model="coDraft[`${coStudentId}|${t.id}|${area.key}`]"
                                    type="text"
                                    class="form-input w-16 text-center"
                                    maxlength="8"
                                    placeholder="A"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn-primary mt-3" :disabled="savingCo" @click="saveCo">
                    {{ savingCo ? 'Saving...' : 'Save co-scholastic' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const coAreas = [
    { key: 'work_education', label: 'Work Education' },
    { key: 'drawing_art', label: 'Drawing & Art' },
    { key: 'sports', label: 'Sports' },
];

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const terms = ref([]);
const filters = reactive({ branch_id: null, school_class_id: null, section_id: null });
const rows = ref([]);
const columns = ref([]);
const loading = ref(false);
const zipping = ref(false);
const coStudentId = ref(null);
const coDraft = reactive({});
const savingCo = ref(false);

function sectionsForClass(classId) {
    if (!classId) return sections.value;
    return sections.value.filter((s) => s.school_class_id === classId);
}

async function loadLookups() {
    const [academics, termsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/exams/terms'),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    terms.value = termsRes.data || [];
}
loadLookups().catch((e) => pushToast(e?.response?.data?.message || e.message || 'Lookup failed', 'error'));

function onBranchChange() {
    filters.school_class_id = null;
    filters.section_id = null;
    rows.value = [];
}
function onClassChange() {
    filters.section_id = null;
    rows.value = [];
}

function hydrateCoDraft() {
    Object.keys(coDraft).forEach((k) => delete coDraft[k]);
    for (const r of rows.value) {
        for (const area of r.co_scholastic || []) {
            for (const t of area.terms || []) {
                const key = `${r.student_id}|${t.term_id}|${area.area}`;
                coDraft[key] = t.grade || '';
            }
        }
    }
}

watch(coStudentId, () => {});

async function loadResults() {
    if (!filters.branch_id) {
        rows.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await client.get('/exams/annual-report', {
            params: {
                branch_id: filters.branch_id,
                ...(filters.school_class_id ? { school_class_id: filters.school_class_id } : {}),
                ...(filters.section_id ? { section_id: filters.section_id } : {}),
            },
        });
        rows.value = data.rows || [];
        columns.value = data.columns || [];
        hydrateCoDraft();
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Failed to load', 'error');
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

async function downloadStudent(row) {
    try {
        await downloadPdf(`/exams/annual-report/${row.student_id}/pdf`, `annual-report-card-${row.admission_no || row.student_id}.pdf`);
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'PDF failed', 'error');
    }
}

async function downloadZip() {
    zipping.value = true;
    try {
        const { data } = await client.get('/exams/annual-report/zip', {
            params: {
                school_class_id: filters.school_class_id || undefined,
                branch_id: filters.branch_id || undefined,
                section_id: filters.section_id || undefined,
            },
            responseType: 'blob',
        });
        triggerBlobDownload(data, 'annual-report-cards.zip');
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'ZIP failed', 'error');
    } finally {
        zipping.value = false;
    }
}

async function saveRemark(row, remarks) {
    try {
        await client.post('/exams/annual-report/remarks', {
            records: [{ student_id: row.student_id, remarks }],
        });
        row.remarks = remarks;
        pushToast('Remarks saved');
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Remarks save failed', 'error');
    }
}

async function saveCo() {
    if (!coStudentId.value) return;
    savingCo.value = true;
    try {
        const records = [];
        for (const t of terms.value) {
            for (const area of coAreas) {
                const key = `${coStudentId.value}|${t.id}|${area.key}`;
                records.push({
                    student_id: coStudentId.value,
                    academic_term_id: t.id,
                    area: area.key,
                    grade: coDraft[key] || '',
                });
            }
        }
        await client.post('/exams/annual-report/co-scholastic', { records });
        pushToast('Co-scholastic grades saved');
        await loadResults();
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Save failed', 'error');
    } finally {
        savingCo.value = false;
    }
}
</script>
