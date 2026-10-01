<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Subject Enrollment</h1>
            <Breadcrumb :items="['Dashboard', 'Academics', 'Subject Enrollment']" class="mt-1" />
            <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                Choose which optional/elective subject each student takes — e.g. Student A takes Urdu, Student B takes Sanskrit, in the same class and section.
                Compulsory subjects are unaffected here; manage those from <span class="font-medium">Classes &amp; Sections → Manage Subjects</span>.
            </p>
            <p v-if="hasElectiveGroups" class="mt-2 max-w-3xl text-xs text-slate-400">
                The small label under a subject name (e.g. "Language") is just a grouping label for display — it does not stop a student from being checked into more than one subject in that group. Each checkbox below is independent.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-3">
            <div>
                <label class="form-label">Class</label>
                <select v-model.number="filters.school_class_id" class="form-input" @change="onClassChange">
                    <option :value="null">Select class</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Section (optional)</label>
                <select v-model.number="filters.section_id" class="form-input" :disabled="!filters.school_class_id" @change="load">
                    <option :value="null">All sections</option>
                    <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="flex items-end text-sm text-slate-500 dark:text-slate-400">
                <span v-if="academicSession">Academic Session: <strong>{{ academicSession.name }}</strong></span>
            </div>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>

        <template v-else-if="filters.school_class_id && !optionalSubjects.length">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                This class has no optional/elective subjects yet. Mark a subject optional from <span class="font-medium">Classes &amp; Sections → Manage Subjects</span> first.
            </div>
        </template>

        <template v-else-if="filters.school_class_id && optionalSubjects.length">
            <!-- Bulk assign -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Bulk assign</h2>
                <p class="mt-1 text-xs text-slate-400">Religion is only a convenience filter for picking students — it never assigns a subject automatically.</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <select v-model.number="bulk.subject_id" class="form-input">
                        <option :value="null">Select subject</option>
                        <option v-for="s in optionalSubjects" :key="s.id" :value="s.id">{{ s.name }}{{ s.elective_group ? ` (${s.elective_group})` : '' }}</option>
                    </select>
                    <select v-model="bulk.religion" class="form-input">
                        <option value="">All religions</option>
                        <option v-for="r in religions" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <div class="flex items-center text-xs text-slate-400">{{ bulkMatchCount }} student(s) match</div>
                    <button type="button" class="btn-primary" :disabled="!bulk.subject_id || bulkSaving" @click="runBulkAssign">
                        {{ bulkSaving ? 'Assigning...' : 'Assign to matching students' }}
                    </button>
                </div>
            </div>

            <!-- Roster -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll No</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Religion</th>
                            <th v-for="s in optionalSubjects" :key="s.id" class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ s.name }}<span v-if="s.elective_group" class="block font-normal normal-case text-slate-400">{{ s.elective_group }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!students.length">
                            <td :colspan="3 + optionalSubjects.length" class="px-4 py-10 text-center text-slate-400">No active students in this class{{ filters.section_id ? ' / section' : '' }}.</td>
                        </tr>
                        <tr v-for="row in students" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ row.roll_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ row.name }}</div>
                                <div class="text-xs text-slate-400">{{ row.admission_no }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ row.religion || '—' }}</td>
                            <td v-for="s in optionalSubjects" :key="s.id" class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    :checked="row.enrolled_subject_ids.includes(s.id)"
                                    :disabled="!!rowSaving[`${row.id}-${s.id}`]"
                                    @change="toggleEnrollment(row, s, $event.target.checked)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { pushToast } from '../../utils/toast';

const classes = ref([]);
const filters = reactive({ school_class_id: null, section_id: null });
const loading = ref(false);
const academicSession = ref(null);
const optionalSubjects = ref([]);
const religions = ref([]);
const students = ref([]);
const rowSaving = reactive({});

const bulk = reactive({ subject_id: null, religion: '' });
const bulkSaving = ref(false);

const sectionsForClass = computed(() => classes.value.find((c) => c.id === filters.school_class_id)?.sections || []);
const bulkMatchCount = computed(() => students.value.filter((s) => !bulk.religion || s.religion === bulk.religion).length);
const hasElectiveGroups = computed(() => optionalSubjects.value.some((s) => s.elective_group));

async function loadClasses() {
    const data = await fetchAcademicsLookups();
    classes.value = data.classes || [];
}

function onClassChange() {
    filters.section_id = null;
    load();
}

async function load() {
    if (!filters.school_class_id) {
        optionalSubjects.value = [];
        students.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await client.get('/academics/subject-enrollment/roster', {
            params: { school_class_id: filters.school_class_id, section_id: filters.section_id || undefined },
        });
        academicSession.value = data.academic_session;
        optionalSubjects.value = data.optional_subjects || [];
        religions.value = data.religions || [];
        students.value = data.students || [];
    } finally {
        loading.value = false;
    }
}

async function toggleEnrollment(row, subject, checked) {
    if (!academicSession.value) {
        pushToast('No active academic session — cannot enroll.', 'error');
        return;
    }
    const key = `${row.id}-${subject.id}`;
    rowSaving[key] = true;
    try {
        await client.put('/academics/subject-enrollment/assign', {
            student_id: row.id,
            subject_id: subject.id,
            academic_session_id: academicSession.value.id,
            enrolled: checked,
        });
        if (checked) {
            row.enrolled_subject_ids = [...row.enrolled_subject_ids, subject.id];
        } else {
            row.enrolled_subject_ids = row.enrolled_subject_ids.filter((id) => id !== subject.id);
        }
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not update enrollment.', 'error');
    } finally {
        delete rowSaving[key];
    }
}

async function runBulkAssign() {
    if (!bulk.subject_id || !academicSession.value) return;
    bulkSaving.value = true;
    try {
        const { data } = await client.post('/academics/subject-enrollment/bulk-assign', {
            school_class_id: filters.school_class_id,
            section_id: filters.section_id || undefined,
            subject_id: bulk.subject_id,
            academic_session_id: academicSession.value.id,
            religion: bulk.religion || undefined,
        });
        pushToast(`Assigned to ${data.assigned} student(s).`, 'success');
        await load();
    } finally {
        bulkSaving.value = false;
    }
}

onMounted(loadClasses);
</script>
