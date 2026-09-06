<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Route</button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Routes" :value="routes.length" color="indigo" icon="🚌" />
            <StatCard label="Active" :value="routes.filter((r) => r.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Assigned Vehicles" :value="routes.filter((r) => r.vehicle).length" color="sky" icon="🧑‍✈️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Route</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">From → To</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stops</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!routes.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No routes added yet.</td>
                    </tr>
                    <tr v-for="r in routes" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.start_point }} → {{ r.end_point }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.vehicle?.vehicle_no || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.stops_count }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.status)">{{ r.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(r)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(r)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Route' : 'Add Route'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Route Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Start Point</label>
                    <input v-model="form.start_point" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">End Point</label>
                    <input v-model="form.end_point" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Assigned Vehicle</label>
                <select v-model="form.vehicle_id" class="form-input">
                    <option :value="null">Unassigned</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_no }}<span v-if="v.driver"> — {{ v.driver.name }}</span></option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option>Active</option>
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
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const pageTitle = computed(() => (route.path === '/transport-management/driver-assignment' ? 'Driver Assignment' : 'Routes'));

const loading = ref(true);
const saving = ref(false);
const routes = ref([]);
const vehicles = ref([]);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', start_point: '', end_point: '', vehicle_id: null, status: 'Active' });

async function load() {
    loading.value = true;
    const [routesRes, vehiclesRes] = await Promise.all([client.get('/transport/routes'), client.get('/transport/vehicles')]);
    routes.value = routesRes.data;
    vehicles.value = vehiclesRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', start_point: '', end_point: '', vehicle_id: null, status: 'Active' });
    drawerOpen.value = true;
}

function openEdit(r) {
    editing.value = r;
    Object.assign(form, { name: r.name, start_point: r.start_point, end_point: r.end_point, vehicle_id: r.vehicle_id, status: r.status });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/transport/routes/${editing.value.id}`, form);
            pushToast('Route updated.', 'success');
        } else {
            await client.post('/transport/routes', form);
            pushToast('Route added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(r) {
    routes.value = routes.value.filter((x) => x.id !== r.id);
    await client.delete(`/transport/routes/${r.id}`);
    pushToast(`Route "${r.name}" deleted.`, 'success');
}
</script>
