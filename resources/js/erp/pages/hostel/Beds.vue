<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Beds</h1>
                <Breadcrumb :items="['Dashboard', 'Hostel', 'Beds']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!roomId" @click="openAdd">+ Add Bed</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Room</label>
            <select v-model.number="roomId" class="form-input" @change="load">
                <option :value="null">All rooms</option>
                <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_no }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Bed No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Room</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!beds.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">No beds found.</td>
                    </tr>
                    <tr v-for="b in beds" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ b.bed_no }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.room.room_no }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(b.status === 'Occupied' ? 'Inactive' : 'Active')">{{ b.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(b)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Add Bed" @close="drawerOpen = false">
            <div v-if="!roomId">
                <label class="form-label">Room</label>
                <select v-model.number="form.room_id" class="form-input">
                    <option :value="null">Select room</option>
                    <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_no }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Bed No.</label>
                <input v-model="form.bed_no" type="text" class="form-input" placeholder="A, B, 1, 2..." />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const rooms = ref([]);
const beds = ref([]);
const roomId = ref(null);
const drawerOpen = ref(false);

const form = reactive({ room_id: null, bed_no: '' });

client.get('/hostel/rooms').then(({ data }) => (rooms.value = data));

async function load() {
    loading.value = true;
    const { data } = await client.get('/hostel/beds', { params: { room_id: roomId.value } });
    beds.value = data;
    loading.value = false;
}
load();

function openAdd() {
    Object.assign(form, { room_id: roomId.value, bed_no: '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/hostel/beds', { ...form, room_id: roomId.value || form.room_id });
        pushToast('Bed added.', 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(bed) {
    beds.value = beds.value.filter((b) => b.id !== bed.id);
    await client.delete(`/hostel/beds/${bed.id}`);
    pushToast(`Bed "${bed.bed_no}" deleted.`, 'success');
}
</script>
