<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Subjects</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Subjects']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Subject</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="subjects" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Subjects" :value="subjects.length" color="indigo" icon="📖" />
            <StatCard label="Assigned to Classes" :value="subjects.filter((s) => s.classes_count > 0).length" color="emerald" icon="🔗" />
            <StatCard label="Unassigned" :value="subjects.filter((s) => s.classes_count === 0).length" color="amber" icon="⚠️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Classes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredSubjects.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">No subjects match your filters.</td>
                    </tr>
                    <tr v-for="s in filteredSubjects" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ s.code }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.classes_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(s)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(s)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Subject' : 'Add Subject'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Subject Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Code</label>
                <input v-model="form.code" type="text" class="form-input" placeholder="e.g. MATH" required />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { invalidateAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const subjects = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', code: '' });

const filteredSubjects = computed(() =>
    subjects.value.filter((s) => {
        if (filterValues.search && !`${s.name} ${s.code}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/academics/subjects', { params: { with_counts: 1 } });
    subjects.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', code: '' });
    drawerOpen.value = true;
}

function openEdit(subject) {
    editing.value = subject;
    Object.assign(form, { name: subject.name, code: subject.code });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/academics/subjects/${editing.value.id}`, form);
            pushToast('Subject updated.', 'success');
        } else {
            await client.post('/academics/subjects', form);
            pushToast('Subject added.', 'success');
        }
        drawerOpen.value = false;
        invalidateAcademicsLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(subject) {
    subjects.value = subjects.value.filter((s) => s.id !== subject.id);
    await client.delete(`/academics/subjects/${subject.id}`);
    invalidateAcademicsLookups();
    pushToast(`Subject "${subject.name}" deleted.`, 'success');
}
</script>
