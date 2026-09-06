<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Suppliers</h1>
                <Breadcrumb :items="['Dashboard', 'Inventory', 'Supplier']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Supplier</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="suppliers" @reset="filterValues = {}" />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Contact</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Address</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredSuppliers.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No suppliers match your filters.</td>
                    </tr>
                    <tr v-for="s in filteredSuppliers" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.contact || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.email || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.address || '—' }}</td>
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

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Supplier' : 'Add Supplier'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Contact</label>
                <input v-model="form.contact" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-input" />
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
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const suppliers = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', contact: '', email: '', address: '' });

const filteredSuppliers = computed(() =>
    suppliers.value.filter((s) => {
        if (filterValues.search && !`${s.name}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/inventory/suppliers');
    suppliers.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', contact: '', email: '', address: '' });
    drawerOpen.value = true;
}

function openEdit(supplier) {
    editing.value = supplier;
    Object.assign(form, { name: supplier.name, contact: supplier.contact || '', email: supplier.email || '', address: supplier.address || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/inventory/suppliers/${editing.value.id}`, form);
            pushToast('Supplier updated.', 'success');
        } else {
            await client.post('/inventory/suppliers', form);
            pushToast('Supplier added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(supplier) {
    suppliers.value = suppliers.value.filter((s) => s.id !== supplier.id);
    await client.delete(`/inventory/suppliers/${supplier.id}`);
    pushToast(`Supplier "${supplier.name}" deleted.`, 'success');
}
</script>
