<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Exam Types</h1>
                <Breadcrumb :items="['Dashboard', 'Exam Management', 'Exam Types']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Exam Type</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="exam types" @reset="filterValues = {}" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Total Types" :value="types.length" color="indigo" icon="📚" />
            <StatCard label="Used in Exams" :value="usedCount" color="emerald" icon="🔗" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="3" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredTypes.length">
                        <td colspan="3" class="px-4 py-10 text-center text-slate-400">No exam types match your filters.</td>
                    </tr>
                    <tr v-for="t in filteredTypes" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ t.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.description || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(t)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(t)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Exam Type' : 'Add Exam Type'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Description</label>
                <input v-model="form.description" type="text" class="form-input" />
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
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const types = ref([]);
const exams = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', description: '' });

const usedCount = computed(() => new Set(exams.value.map((e) => e.exam_type.id)).size);

const filteredTypes = computed(() =>
    types.value.filter((t) => {
        if (filterValues.search && !`${t.name} ${t.description}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [typesRes, examsRes] = await Promise.all([client.get('/exams/types'), client.get('/exams')]);
    types.value = typesRes.data;
    exams.value = examsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', description: '' });
    drawerOpen.value = true;
}

function openEdit(type) {
    editing.value = type;
    Object.assign(form, { name: type.name, description: type.description || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/exams/types/${editing.value.id}`, form);
            pushToast('Exam type updated.', 'success');
        } else {
            await client.post('/exams/types', form);
            pushToast('Exam type added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(type) {
    types.value = types.value.filter((t) => t.id !== type.id);
    await client.delete(`/exams/types/${type.id}`);
    pushToast(`Exam type "${type.name}" deleted.`, 'success');
}
</script>
