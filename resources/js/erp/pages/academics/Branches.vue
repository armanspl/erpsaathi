<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Branches</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Branches']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Branch</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="branches" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total Branches" :value="branches.length" color="indigo" icon="🏫" />
            <StatCard label="Active" :value="branches.filter((b) => b.status === 'active').length" color="emerald" icon="✅" />
            <StatCard label="Inactive" :value="branches.filter((b) => b.status === 'inactive').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Principal</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredBranches.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No branches match your filters.</td>
                    </tr>
                    <tr v-for="b in filteredBranches" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ b.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.principal || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.phone || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(b.status)">{{ b.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(b)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(b)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Branch' : 'Add Branch'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Branch Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Principal</label>
                <input v-model="form.principal" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Address</label>
                <input v-model="form.address" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
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
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const branches = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', principal: '', phone: '', address: '', status: 'active' });

const filteredBranches = computed(() =>
    branches.value.filter((b) => {
        if (filterValues.search && !`${b.name} ${b.principal} ${b.phone}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && b.status !== filterValues.status.toLowerCase()) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/academics/branches');
    branches.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', principal: '', phone: '', address: '', status: 'active' });
    drawerOpen.value = true;
}

function openEdit(branch) {
    editing.value = branch;
    Object.assign(form, { name: branch.name, principal: branch.principal || '', phone: branch.phone || '', address: branch.address || '', status: branch.status });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/academics/branches/${editing.value.id}`, form);
            pushToast('Branch updated.', 'success');
        } else {
            await client.post('/academics/branches', form);
            pushToast('Branch added.', 'success');
        }
        drawerOpen.value = false;
        invalidateAcademicsLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(branch) {
    branches.value = branches.value.filter((b) => b.id !== branch.id);
    await client.delete(`/academics/branches/${branch.id}`);
    invalidateAcademicsLookups();
    pushToast(`Branch "${branch.name}" deleted.`, 'success');
}
</script>
