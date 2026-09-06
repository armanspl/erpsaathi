<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Rooms</h1>
                <Breadcrumb :items="['Dashboard', 'Hostel', 'Rooms']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Room</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="rooms" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Rooms" :value="rooms.length" color="indigo" icon="🏨" />
            <StatCard label="Total Beds" :value="totalBeds" color="sky" icon="🛏️" />
            <StatCard label="Occupied" :value="totalOccupied" color="emerald" icon="✅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Room No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capacity</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Beds</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly Fee</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredRooms.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No rooms match your filters.</td>
                    </tr>
                    <tr v-for="r in filteredRooms" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.room_no }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.capacity }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.occupied_beds_count }}/{{ r.beds_count }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ Number(r.monthly_fee).toLocaleString('en-IN') }}</td>
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

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Room' : 'Add Room'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Room No.</label>
                <input v-model="form.room_no" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="form.type" class="form-input">
                        <option>Single</option>
                        <option>Double</option>
                        <option>Dormitory</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Capacity</label>
                    <input v-model.number="form.capacity" type="number" min="1" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Monthly Fee</label>
                    <input v-model.number="form.monthly_fee" type="number" step="0.01" min="0" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-input">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
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
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const rooms = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ room_no: '', type: 'Double', capacity: 2, monthly_fee: 0, status: 'Active' });

const totalBeds = computed(() => rooms.value.reduce((sum, r) => sum + r.beds_count, 0));
const totalOccupied = computed(() => rooms.value.reduce((sum, r) => sum + r.occupied_beds_count, 0));

const filteredRooms = computed(() =>
    rooms.value.filter((r) => {
        if (filterValues.search && !`${r.room_no} ${r.type}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && r.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/hostel/rooms');
    rooms.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { room_no: '', type: 'Double', capacity: 2, monthly_fee: 0, status: 'Active' });
    drawerOpen.value = true;
}

function openEdit(room) {
    editing.value = room;
    Object.assign(form, { room_no: room.room_no, type: room.type, capacity: room.capacity, monthly_fee: Number(room.monthly_fee), status: room.status });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/hostel/rooms/${editing.value.id}`, form);
            pushToast('Room updated.', 'success');
        } else {
            await client.post('/hostel/rooms', form);
            pushToast('Room added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(room) {
    rooms.value = rooms.value.filter((r) => r.id !== room.id);
    await client.delete(`/hostel/rooms/${room.id}`);
    pushToast(`Room "${room.room_no}" deleted.`, 'success');
}
</script>
