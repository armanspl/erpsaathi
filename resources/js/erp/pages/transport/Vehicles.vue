<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Vehicles</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', 'Vehicles']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Vehicle</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="vehicles" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Vehicles" :value="vehicles.length" color="indigo" icon="🚌" />
            <StatCard label="Active" :value="vehicles.filter((v) => v.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Under Maintenance" :value="vehicles.filter((v) => v.status === 'Under Maintenance').length" color="amber" icon="🔧" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capacity</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Driver</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredVehicles.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No vehicles match your filters.</td>
                    </tr>
                    <tr v-for="v in filteredVehicles" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ v.vehicle_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ v.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.capacity }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.driver?.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(v.status)">{{ v.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(v)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(v)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Vehicle' : 'Add Vehicle'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Vehicle No.</label>
                <input v-model="form.vehicle_no" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="form.type" class="form-input">
                        <option>Bus</option>
                        <option>Van</option>
                        <option>Car</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Capacity</label>
                    <input v-model.number="form.capacity" type="number" min="1" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Driver</label>
                <select v-model="form.driver_id" class="form-input">
                    <option :value="null">Unassigned</option>
                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }} ({{ d.employee_id }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option>Active</option>
                    <option>Under Maintenance</option>
                    <option>Inactive</option>
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
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Under Maintenance', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const vehicles = ref([]);
const drivers = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ vehicle_no: '', type: 'Bus', capacity: 20, driver_id: null, status: 'Active' });

const filteredVehicles = computed(() =>
    vehicles.value.filter((v) => {
        if (filterValues.search && !`${v.vehicle_no} ${v.type}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && v.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [vehiclesRes, driversRes] = await Promise.all([client.get('/transport/vehicles'), client.get('/people/drivers')]);
    vehicles.value = vehiclesRes.data;
    drivers.value = driversRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { vehicle_no: '', type: 'Bus', capacity: 20, driver_id: null, status: 'Active' });
    drawerOpen.value = true;
}

function openEdit(vehicle) {
    editing.value = vehicle;
    Object.assign(form, { vehicle_no: vehicle.vehicle_no, type: vehicle.type, capacity: vehicle.capacity, driver_id: vehicle.driver_id, status: vehicle.status });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/transport/vehicles/${editing.value.id}`, form);
            pushToast('Vehicle updated.', 'success');
        } else {
            await client.post('/transport/vehicles', form);
            pushToast('Vehicle added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(vehicle) {
    vehicles.value = vehicles.value.filter((v) => v.id !== vehicle.id);
    await client.delete(`/transport/vehicles/${vehicle.id}`);
    pushToast(`Vehicle "${vehicle.vehicle_no}" deleted.`, 'success');
}
</script>
