<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Vehicle Maintenance</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', 'Vehicle Maintenance']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!vehicleId" @click="openAdd">+ Add Record</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Vehicle</label>
            <select v-model.number="vehicleId" class="form-input" @change="load">
                <option :value="null">Select a vehicle</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_no }}</option>
            </select>
        </div>

        <div v-if="vehicleId" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Records" :value="records.length" color="indigo" icon="🔧" />
            <StatCard label="Total Cost" :value="`₹${totalCost.toLocaleString('en-IN')}`" color="rose" icon="💸" />
            <StatCard label="Next Due" :value="nextDue || '—'" color="amber" icon="📅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Next Due</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!vehicleId">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Select a vehicle to view its maintenance log.</td>
                    </tr>
                    <tr v-else-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!records.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No maintenance records for this vehicle.</td>
                    </tr>
                    <tr v-for="r in records" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.description || '—' }}</td>
                        <td class="px-4 py-3 text-rose-600 dark:text-rose-400">₹{{ Number(r.cost).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(r.date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.next_due_date ? formatDate(r.next_due_date) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(r)">✏️</button>
                            <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(r)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Maintenance Record' : 'Add Maintenance Record'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option>Service</option>
                    <option>Repair</option>
                    <option>Inspection</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label class="form-label">Description</label>
                <input v-model="form.description" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Cost</label>
                    <input v-model.number="form.cost" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.date" type="date" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Next Due Date</label>
                <input v-model="form.next_due_date" type="date" class="form-input" />
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
const records = ref([]);
const vehicleId = ref(null);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ type: 'Service', description: '', cost: 0, date: new Date().toISOString().slice(0, 10), next_due_date: '' });

const totalCost = computed(() => records.value.reduce((sum, r) => sum + Number(r.cost), 0));
const nextDue = computed(() => {
    const dates = records.value.map((r) => r.next_due_date).filter(Boolean).sort();
    return dates.length ? formatDate(dates[0]) : null;
});

client.get('/transport/vehicles').then(({ data }) => (vehicles.value = data));

async function load() {
    if (!vehicleId.value) {
        records.value = [];
        return;
    }
    loading.value = true;
    const { data } = await client.get('/transport/maintenance', { params: { vehicle_id: vehicleId.value } });
    records.value = data;
    loading.value = false;
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { type: 'Service', description: '', cost: 0, date: new Date().toISOString().slice(0, 10), next_due_date: '' });
    drawerOpen.value = true;
}

function openEdit(record) {
    editing.value = record;
    Object.assign(form, {
        type: record.type,
        description: record.description || '',
        cost: Number(record.cost),
        date: record.date ? record.date.slice(0, 10) : '',
        next_due_date: record.next_due_date ? record.next_due_date.slice(0, 10) : '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, vehicle_id: vehicleId.value, next_due_date: form.next_due_date || null };
        if (editing.value) {
            await client.put(`/transport/maintenance/${editing.value.id}`, payload);
            pushToast('Maintenance record updated.', 'success');
        } else {
            await client.post('/transport/maintenance', payload);
            pushToast('Maintenance record added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(record) {
    records.value = records.value.filter((r) => r.id !== record.id);
    await client.delete(`/transport/maintenance/${record.id}`);
    pushToast('Maintenance record removed.', 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
