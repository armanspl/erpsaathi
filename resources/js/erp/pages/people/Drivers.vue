<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Drivers</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Drivers']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Driver</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="drivers" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total" :value="drivers.length" color="indigo" icon="🚗" />
            <StatCard label="Active" :value="drivers.filter((d) => d.status === 'active').length" color="emerald" icon="✅" />
            <StatCard label="Inactive" :value="drivers.filter((d) => d.status === 'inactive').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Emp ID</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">License No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredDrivers.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No drivers match your filters.</td>
                    </tr>
                    <tr v-for="d in filteredDrivers" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ d.employee_id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ d.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.phone || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.license_no || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.vehicle_no || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(d.status)">{{ d.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(d)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(d)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Driver' : 'Add Driver'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Employee ID</label>
                <input v-model="form.employee_id" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">License No</label>
                <input v-model="form.license_no" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Vehicle No</label>
                <input v-model="form.vehicle_no" type="text" class="form-input" />
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
import { fetchPeopleLookups, invalidatePeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const drivers = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ employee_id: '', name: '', phone: '', license_no: '', vehicle_no: '', status: 'active' });

const filteredDrivers = computed(() =>
    drivers.value.filter((d) => {
        if (filterValues.search && !`${d.name} ${d.employee_id} ${d.vehicle_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && d.status !== filterValues.status.toLowerCase()) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const lookups = await fetchPeopleLookups({ force: true });
    drivers.value = lookups.drivers || [];
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { employee_id: '', name: '', phone: '', license_no: '', vehicle_no: '', status: 'active' });
    drawerOpen.value = true;
}

function openEdit(driver) {
    editing.value = driver;
    Object.assign(form, { employee_id: driver.employee_id, name: driver.name, phone: driver.phone || '', license_no: driver.license_no || '', vehicle_no: driver.vehicle_no || '', status: driver.status });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/people/drivers/${editing.value.id}`, form);
            pushToast('Driver updated.', 'success');
        } else {
            await client.post('/people/drivers', form);
            pushToast('Driver added.', 'success');
        }
        drawerOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(driver) {
    drivers.value = drivers.value.filter((d) => d.id !== driver.id);
    await client.delete(`/people/drivers/${driver.id}`);
    invalidatePeopleLookups();
    pushToast(`Driver "${driver.name}" deleted.`, 'success');
}
</script>
