<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!routeId" @click="openAdd">+ Add Stop</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Route</label>
            <select v-model.number="routeId" class="form-input" @change="load">
                <option :value="null">Select a route</option>
                <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">#</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stop</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fare</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Drop</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!routeId">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Select a route to view its stops.</td>
                    </tr>
                    <tr v-else-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!stops.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No stops added for this route.</td>
                    </tr>
                    <tr v-for="s in stops" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.sequence_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.stop_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ Number(s.fare).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.pickup_time || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.drop_time || '—' }}</td>
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

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Stop' : 'Add Stop'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Stop Name</label>
                <input v-model="form.stop_name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Sequence No.</label>
                    <input v-model.number="form.sequence_no" type="number" min="1" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Fare</label>
                    <input v-model.number="form.fare" type="number" step="0.01" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Pickup Time</label>
                    <input v-model="form.pickup_time" type="time" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Drop Time</label>
                    <input v-model="form.drop_time" type="time" class="form-input" />
                </div>
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
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const routeMeta = useRoute();
const pageTitle = computed(() => (routeMeta.path === '/transport-management/route-fare' ? 'Route Fare' : 'Route Stops'));

const loading = ref(false);
const saving = ref(false);
const routes = ref([]);
const stops = ref([]);
const routeId = ref(null);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ stop_name: '', sequence_no: 1, fare: 0, pickup_time: '', drop_time: '' });

client.get('/transport/routes').then(({ data }) => (routes.value = data));

async function load() {
    if (!routeId.value) {
        stops.value = [];
        return;
    }
    loading.value = true;
    const { data } = await client.get('/transport/stops', { params: { route_id: routeId.value } });
    stops.value = data;
    loading.value = false;
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { stop_name: '', sequence_no: stops.value.length + 1, fare: 0, pickup_time: '', drop_time: '' });
    drawerOpen.value = true;
}

function openEdit(stop) {
    editing.value = stop;
    Object.assign(form, {
        stop_name: stop.stop_name,
        sequence_no: stop.sequence_no,
        fare: Number(stop.fare),
        pickup_time: stop.pickup_time || '',
        drop_time: stop.drop_time || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/transport/stops/${editing.value.id}`, form);
            pushToast('Stop updated.', 'success');
        } else {
            await client.post('/transport/stops', { ...form, route_id: routeId.value });
            pushToast('Stop added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(stop) {
    stops.value = stops.value.filter((s) => s.id !== stop.id);
    await client.delete(`/transport/stops/${stop.id}`);
    pushToast(`Stop "${stop.stop_name}" deleted.`, 'success');
}
</script>
