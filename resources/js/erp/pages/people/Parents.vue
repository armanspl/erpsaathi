<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Parents</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Parents']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Parent</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="parents" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Parents" :value="parents.length" color="indigo" icon="👪" />
            <StatCard label="Linked to Children" :value="parents.filter((p) => p.children_count > 0).length" color="emerald" icon="🔗" />
            <StatCard label="Unlinked" :value="parents.filter((p) => p.children_count === 0).length" color="amber" icon="⚠️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Occupation</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Children</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredParents.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No parents match your filters.</td>
                    </tr>
                    <tr v-for="p in filteredParents" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.phone || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.email || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.occupation || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.children_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(p)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(p)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Parent' : 'Add Parent'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-input" />
            </div>
            <div>
                <label class="form-label">Occupation</label>
                <input v-model="form.occupation" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Qualification</label>
                    <input v-model="form.qualification" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Date of Birth</label>
                    <input v-model="form.dob" type="date" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Address</label>
                <input v-model="form.address" type="text" class="form-input" />
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
const parents = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', phone: '', email: '', occupation: '', qualification: '', dob: '', address: '' });

const filteredParents = computed(() =>
    parents.value.filter((p) => {
        if (filterValues.search && !`${p.name} ${p.phone} ${p.email}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/people/parents');
    parents.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', phone: '', email: '', occupation: '', qualification: '', dob: '', address: '' });
    drawerOpen.value = true;
}

function openEdit(parent) {
    editing.value = parent;
    Object.assign(form, {
        name: parent.name,
        phone: parent.phone || '',
        email: parent.email || '',
        occupation: parent.occupation || '',
        qualification: parent.qualification || '',
        dob: parent.dob ? parent.dob.slice(0, 10) : '',
        address: parent.address || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/people/parents/${editing.value.id}`, form);
            pushToast('Parent updated.', 'success');
        } else {
            await client.post('/people/parents', form);
            pushToast('Parent added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(parent) {
    parents.value = parents.value.filter((p) => p.id !== parent.id);
    await client.delete(`/people/parents/${parent.id}`);
    pushToast(`Parent "${parent.name}" deleted.`, 'success');
}
</script>
