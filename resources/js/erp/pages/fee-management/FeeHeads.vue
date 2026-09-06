<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fee Heads</h1>
                <Breadcrumb :items="['Dashboard', 'Fee Management', 'Fee Heads']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Fee Head</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="fee heads" @reset="filterValues = {}" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Total Fee Heads" :value="heads.length" color="indigo" icon="🧾" />
            <StatCard label="Used in Structure" :value="usedCount" color="emerald" icon="🔗" />
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
                    <tr v-else-if="!filteredHeads.length">
                        <td colspan="3" class="px-4 py-10 text-center text-slate-400">No fee heads match your filters.</td>
                    </tr>
                    <tr v-for="h in filteredHeads" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ h.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ h.description || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(h)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(h)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Fee Head' : 'Add Fee Head'" @close="drawerOpen = false">
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
import { fetchFeeLookups, invalidateFeeLookups } from '../../api/feeManagement';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const heads = ref([]);
const structures = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', description: '' });

const usedCount = computed(() => new Set(structures.value.map((s) => s.fee_head.id)).size);

const filteredHeads = computed(() =>
    heads.value.filter((h) => {
        if (filterValues.search && !`${h.name} ${h.description}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [fee, structuresRes] = await Promise.all([
        fetchFeeLookups({ force: true }),
        client.get('/fee-management/structures', { params: { flat: 1 } }),
    ]);
    heads.value = fee.heads || [];
    structures.value = structuresRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', description: '' });
    drawerOpen.value = true;
}

function openEdit(head) {
    editing.value = head;
    Object.assign(form, { name: head.name, description: head.description || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/fee-management/heads/${editing.value.id}`, form);
            pushToast('Fee head updated.', 'success');
        } else {
            await client.post('/fee-management/heads', form);
            pushToast('Fee head added.', 'success');
        }
        drawerOpen.value = false;
        invalidateFeeLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(head) {
    heads.value = heads.value.filter((h) => h.id !== head.id);
    await client.delete(`/fee-management/heads/${head.id}`);
    invalidateFeeLookups();
    pushToast(`Fee head "${head.name}" deleted.`, 'success');
}
</script>
