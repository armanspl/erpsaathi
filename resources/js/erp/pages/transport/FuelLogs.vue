<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fuel Logs</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', 'Fuel Logs']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!vehicleId" @click="openAdd">+ Add Fuel Log</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Vehicle</label>
            <select v-model.number="vehicleId" class="form-input" @change="load">
                <option :value="null">Select a vehicle</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_no }}</option>
            </select>
        </div>

        <div v-if="vehicleId" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Fill-ups" :value="logs.length" color="indigo" icon="⛽" />
            <StatCard label="Total Liters" :value="totalLiters.toLocaleString('en-IN')" color="sky" icon="🛢️" />
            <StatCard label="Total Cost" :value="`₹${totalCost.toLocaleString('en-IN')}`" color="rose" icon="💸" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Liters</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Odometer</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!vehicleId">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Select a vehicle to view its fuel logs.</td>
                    </tr>
                    <tr v-else-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!logs.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No fuel logs for this vehicle.</td>
                    </tr>
                    <tr v-for="l in logs" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(l.date) }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ Number(l.liters).toLocaleString('en-IN') }} L</td>
                        <td class="px-4 py-3 text-rose-600 dark:text-rose-400">₹{{ Number(l.cost).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ l.odometer_reading ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ l.remarks || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(l)">✏️</button>
                            <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(l)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Fuel Log' : 'Add Fuel Log'" @close="drawerOpen = false">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Liters</label>
                    <input v-model.number="form.liters" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Cost</label>
                    <input v-model.number="form.cost" type="number" step="0.01" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Odometer</label>
                    <input v-model.number="form.odometer_reading" type="number" min="0" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input v-model="form.remarks" type="text" class="form-input" />
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
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const saving = ref(false);
const vehicles = ref([]);
const logs = ref([]);
const vehicleId = ref(null);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ date: new Date().toISOString().slice(0, 10), liters: 0, cost: 0, odometer_reading: null, remarks: '' });

const totalLiters = computed(() => logs.value.reduce((sum, l) => sum + Number(l.liters), 0));
const totalCost = computed(() => logs.value.reduce((sum, l) => sum + Number(l.cost), 0));

client.get('/transport/vehicles').then(({ data }) => (vehicles.value = data));

async function load() {
    if (!vehicleId.value) {
        logs.value = [];
        return;
    }
    loading.value = true;
    const { data } = await client.get('/transport/fuel-logs', { params: { vehicle_id: vehicleId.value } });
    logs.value = data;
    loading.value = false;
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { date: new Date().toISOString().slice(0, 10), liters: 0, cost: 0, odometer_reading: null, remarks: '' });
    drawerOpen.value = true;
}

function openEdit(log) {
    editing.value = log;
    Object.assign(form, {
        date: log.date ? log.date.slice(0, 10) : '',
        liters: Number(log.liters),
        cost: Number(log.cost),
        odometer_reading: log.odometer_reading ?? null,
        remarks: log.remarks || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, vehicle_id: vehicleId.value };
        if (editing.value) {
            await client.put(`/transport/fuel-logs/${editing.value.id}`, payload);
            pushToast('Fuel log updated.', 'success');
        } else {
            await client.post('/transport/fuel-logs', payload);
            pushToast('Fuel log added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(log) {
    logs.value = logs.value.filter((l) => l.id !== log.id);
    await client.delete(`/transport/fuel-logs/${log.id}`);
    pushToast('Fuel log removed.', 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
