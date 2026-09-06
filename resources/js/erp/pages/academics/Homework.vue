<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Homework</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Homework']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openCreate">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                Add Homework
            </button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="homework" @reset="resetFilters" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Assignments" :value="homeworks.length" color="indigo" icon="📚" />
            <StatCard label="Today" :value="todayCount" color="emerald" icon="📅" />
            <StatCard label="With Files" :value="withFilesCount" color="sky" icon="📎" />
            <StatCard label="Subjects Covered" :value="subjectsCovered" color="amber" icon="📖" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Teacher</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Subjects</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredHomeworks.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No homework assignments yet.</td>
                    </tr>
                    <tr v-for="hw in filteredHomeworks" :key="hw.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(hw.assigned_date) }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ hw.school_class?.name || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ hw.section?.name || 'All' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ hw.teacher?.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="item in hw.items"
                                    :key="item.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ item.subject?.name }}
                                    <span v-if="item.attachment_path" title="Has attachment">📎</span>
                                </span>
                            </div>
                        </td>
                        <td class="max-w-[12rem] truncate px-4 py-3 text-slate-500 dark:text-slate-400">{{ hw.description || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="View" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openView(hw)">👁</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(hw)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Homework -->
        <SlideOver :open="createOpen" title="Create Homework" wide @close="createOpen = false">
            <p class="text-xs text-slate-400">Select the class context first. Subject rows are loaded from the class–subject map.</p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="form.branch_id" class="form-input">
                        <option :value="null">Select branch</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="form.school_class_id" class="form-input" @change="onClassChange">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="form.section_id" class="form-input" :disabled="!form.school_class_id">
                        <option :value="null">All sections</option>
                        <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Teacher</label>
                    <select v-model="form.teacher_id" class="form-input">
                        <option :value="null">Select teacher</option>
                        <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label">Date</label>
                <input v-model="form.assigned_date" type="date" class="form-input" />
            </div>
            <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" rows="2" class="form-input" placeholder="Optional overall notes for this homework set" />
            </div>

            <div>
                <h4 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Subjects</h4>
                <p class="mb-3 text-xs text-slate-400">Add homework text for the subjects assigned to the selected class.</p>

                <div v-if="!form.school_class_id" class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                    Select a class to load its subjects.
                </div>
                <div v-else-if="!subjectsForClass.length" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                    No subjects assigned to this class yet. Assign subjects under Classes &amp; Sections first.
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="w-36 px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</th>
                                <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Homework</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="sub in subjectsForClass" :key="sub.id">
                                <td class="px-3 py-3 align-top font-medium text-slate-700 dark:text-slate-200">{{ sub.name }}</td>
                                <td class="px-3 py-3">
                                    <textarea
                                        v-model="subjectEntries[sub.id].content"
                                        rows="2"
                                        class="form-input mb-2"
                                        :placeholder="`Homework for ${sub.name}...`"
                                    />
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-500 transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-slate-700 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10">
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4L8 8M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                                        <span class="truncate">{{ subjectEntries[sub.id].file?.name || 'Upload file (optional)' }}</span>
                                        <input type="file" class="hidden" @change="onFilePick(sub.id, $event)" />
                                    </label>
                                    <button
                                        v-if="subjectEntries[sub.id].file"
                                        type="button"
                                        class="mt-1 text-xs text-rose-500 hover:underline"
                                        @click="subjectEntries[sub.id].file = null"
                                    >
                                        Remove file
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn-outline" @click="createOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving || !form.school_class_id" @click="save">
                    {{ saving ? 'Saving...' : 'Save Homework' }}
                </button>
            </template>
        </SlideOver>

        <!-- View details -->
        <SlideOver :open="!!viewing" title="Homework Details" wide @close="viewing = null">
            <template v-if="viewing">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-400">Date</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ formatDate(viewing.assigned_date) }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Branch</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ viewing.branch?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Class</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ viewing.school_class?.name }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Section</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ viewing.section?.name || 'All' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Teacher</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ viewing.teacher?.name || '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-400">Description</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ viewing.description || '—' }}</dd></div>
                </dl>
                <div class="mt-4 space-y-3">
                    <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Subjects</h4>
                    <div v-for="item in viewing.items" :key="item.id" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ item.subject?.name }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ item.content || '—' }}</p>
                        <a
                            v-if="item.attachment_path"
                            :href="`/erp/api/academics/homework-items/${item.id}/attachment`"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                            target="_blank"
                        >
                            📎 {{ item.attachment_name || 'Download attachment' }}
                        </a>
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="viewing = null">Close</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { fetchAcademicsLookups, fetchClassesFull } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = computed(() => [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'class', label: 'Class', type: 'select', options: classes.value.map((c) => c.name) },
]);

const loading = ref(true);
const saving = ref(false);
const createOpen = ref(false);
const viewing = ref(null);

const homeworks = ref([]);
const branches = ref([]);
const classes = ref([]);
const teachers = ref([]);
const filterValues = reactive({});

function resetFilters() {
    Object.keys(filterValues).forEach((k) => delete filterValues[k]);
}

const form = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    teacher_id: null,
    assigned_date: new Date().toISOString().slice(0, 10),
    description: '',
});

/** @type {import('vue').Reactive<Record<number, { content: string, file: File|null }>>} */
const subjectEntries = reactive({});

const sectionsForClass = computed(() => {
    const cls = classes.value.find((c) => c.id === form.school_class_id);
    return cls?.sections || [];
});

const subjectsForClass = computed(() => {
    const cls = classes.value.find((c) => c.id === form.school_class_id);
    return cls?.subjects || [];
});

const todayCount = computed(() => {
    const today = new Date().toISOString().slice(0, 10);
    return homeworks.value.filter((h) => (h.assigned_date || '').slice(0, 10) === today).length;
});

const withFilesCount = computed(() =>
    homeworks.value.filter((h) => h.items?.some((i) => i.attachment_path)).length,
);

const subjectsCovered = computed(() => {
    const ids = new Set();
    homeworks.value.forEach((h) => h.items?.forEach((i) => ids.add(i.subject_id)));
    return ids.size;
});

const filteredHomeworks = computed(() =>
    homeworks.value.filter((h) => {
        if (filterValues.search) {
            const q = filterValues.search.toLowerCase();
            const hay = [
                h.school_class?.name,
                h.section?.name,
                h.teacher?.name,
                h.branch?.name,
                h.description,
                formatDate(h.assigned_date),
                ...(h.items || []).map((i) => `${i.subject?.name || ''} ${i.content || ''}`),
            ].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (filterValues.class && h.school_class?.name !== filterValues.class) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [hwRes, lookups, fullClasses, teachersRes] = await Promise.all([
        client.get('/academics/homeworks', { params: { limit: 150 } }),
        fetchAcademicsLookups(),
        fetchClassesFull(),
        client.get('/people/teachers'),
    ]);
    homeworks.value = hwRes.data;
    branches.value = (lookups.branches || []).filter((b) => b.status !== 'inactive');
    // Full classes carry assigned subjects needed by the create form.
    classes.value = fullClasses;
    teachers.value = teachersRes.data.filter((t) => t.status !== 'Inactive');
    loading.value = false;
}
load();

function resetSubjectEntries() {
    Object.keys(subjectEntries).forEach((k) => delete subjectEntries[k]);
    subjectsForClass.value.forEach((s) => {
        subjectEntries[s.id] = { content: '', file: null };
    });
}

function onClassChange() {
    form.section_id = null;
    resetSubjectEntries();
}

watch(subjectsForClass, () => {
    // Keep existing typed content when possible; init missing keys.
    subjectsForClass.value.forEach((s) => {
        if (!subjectEntries[s.id]) subjectEntries[s.id] = { content: '', file: null };
    });
});

function onFilePick(subjectId, event) {
    const file = event.target.files?.[0] || null;
    if (!subjectEntries[subjectId]) subjectEntries[subjectId] = { content: '', file: null };
    subjectEntries[subjectId].file = file;
    event.target.value = '';
}

function openCreate() {
    Object.assign(form, {
        branch_id: branches.value[0]?.id ?? null,
        school_class_id: null,
        section_id: null,
        teacher_id: null,
        assigned_date: new Date().toISOString().slice(0, 10),
        description: '',
    });
    Object.keys(subjectEntries).forEach((k) => delete subjectEntries[k]);
    createOpen.value = true;
}

function openView(hw) {
    viewing.value = hw;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function save() {
    const items = subjectsForClass.value
        .map((s) => ({
            subject_id: s.id,
            content: subjectEntries[s.id]?.content?.trim() || '',
            hasFile: !!subjectEntries[s.id]?.file,
        }))
        .filter((i) => i.content || i.hasFile);

    if (!form.school_class_id) {
        pushToast('Please select a class.', 'error');
        return;
    }
    if (!items.length) {
        pushToast('Add homework text or a file for at least one subject.', 'error');
        return;
    }

    saving.value = true;
    try {
        const fd = new FormData();
        if (form.branch_id) fd.append('branch_id', form.branch_id);
        fd.append('school_class_id', form.school_class_id);
        if (form.section_id) fd.append('section_id', form.section_id);
        if (form.teacher_id) fd.append('teacher_id', form.teacher_id);
        fd.append('assigned_date', form.assigned_date);
        if (form.description) fd.append('description', form.description);

        const payloadItems = items.map(({ subject_id, content }) => ({ subject_id, content }));
        fd.append('items', JSON.stringify(payloadItems));

        items.forEach((item) => {
            const file = subjectEntries[item.subject_id]?.file;
            if (file) fd.append(`attachments[${item.subject_id}]`, file);
        });

        await client.post('/academics/homeworks', fd);
        pushToast('Homework saved.', 'success');
        createOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(hw) {
    homeworks.value = homeworks.value.filter((h) => h.id !== hw.id);
    await client.delete(`/academics/homeworks/${hw.id}`);
    pushToast('Homework deleted.', 'success');
}
</script>
