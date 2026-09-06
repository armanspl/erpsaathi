<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Hostel Students</h1>
                <Breadcrumb :items="['Dashboard', 'Hostel', 'Hostel Students']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Allocate Bed</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="allocations" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Allocations" :value="allocations.length" color="indigo" icon="🏨" />
            <StatCard label="Active" :value="allocations.filter((a) => a.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Vacated" :value="allocations.filter((a) => a.status === 'Inactive').length" color="rose" icon="🚪" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Room / Bed</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredAllocations.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No allocations match your filters.</td>
                    </tr>
                    <tr v-for="a in filteredAllocations" :key="a.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ a.student.name }}</p>
                            <p class="text-xs text-slate-400">{{ a.student.admission_no }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ a.bed.room.room_no }} — {{ a.bed.bed_no }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(a.start_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(a.status)">{{ a.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="a.status === 'Active'" type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(a)">✏️</button>
                            <button v-if="a.status === 'Active'" type="button" class="btn-outline !py-1 !text-xs" @click="openVacate(a)">🚪 Vacate</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Allocation' : 'Allocate Bed'" @close="drawerOpen = false">
            <div v-if="editing">
                <label class="form-label">Student</label>
                <p class="form-input flex items-center bg-slate-50 text-slate-500 dark:bg-slate-800/50">{{ editing.student.name }} ({{ editing.student.admission_no }})</p>
            </div>
            <div v-else>
                <label class="form-label">Student</label>
                <select v-model.number="form.student_id" class="form-input">
                    <option :value="null">Select student</option>
                    <option v-for="s in unallocatedStudents" :key="s.id" :value="s.id">{{ s.name }} ({{ s.admission_no }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Bed</label>
                <select v-model.number="form.bed_id" class="form-input">
                    <option :value="null">Select bed</option>
                    <option v-for="b in editableBeds" :key="b.id" :value="b.id">{{ b.room.room_no }} — {{ b.bed_no }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Start Date</label>
                <input v-model="form.start_date" type="date" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : (editing ? 'Save' : 'Allocate') }}</button>
            </template>
        </SlideOver>

        <SlideOver :open="vacateDialogOpen" title="Vacate Bed" @close="vacateDialogOpen = false">
            <p v-if="vacateTarget" class="text-sm text-slate-600 dark:text-slate-300">
                Vacate <strong>{{ vacateTarget.student.name }}</strong> from {{ vacateTarget.bed.room.room_no }}-{{ vacateTarget.bed.bed_no }}?
            </p>
            <div>
                <label class="form-label">End Date</label>
                <input v-model="vacateForm.end_date" type="date" class="form-input" />
                <p class="mt-1 text-xs text-slate-400">Defaults to today — backdate if the student actually left earlier.</p>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="vacateDialogOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="confirmVacate">{{ saving ? 'Vacating...' : 'Vacate' }}</button>
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
const allocations = ref([]);
const beds = ref([]);
const students = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);
const vacateDialogOpen = ref(false);
const vacateTarget = ref(null);
const vacateForm = reactive({ end_date: new Date().toISOString().slice(0, 10) });

const form = reactive({ student_id: null, bed_id: null, start_date: new Date().toISOString().slice(0, 10) });

const availableBeds = computed(() => beds.value.filter((b) => b.status === 'Available'));
const editableBeds = computed(() => {
    if (!editing.value) return availableBeds.value;
    return beds.value.filter((b) => b.status === 'Available' || b.id === editing.value.bed_id);
});
const unallocatedStudents = computed(() => {
    const activeIds = new Set(allocations.value.filter((a) => a.status === 'Active').map((a) => a.student.id));
    return students.value.filter((s) => !activeIds.has(s.id));
});

const filteredAllocations = computed(() =>
    allocations.value.filter((a) => {
        if (filterValues.search && !`${a.student.name} ${a.student.admission_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && a.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [allocationsRes, bedsRes, studentsRes] = await Promise.all([
        client.get('/hostel/allocations'),
        client.get('/hostel/beds'),
        client.get('/people/students'),
    ]);
    allocations.value = allocationsRes.data;
    beds.value = bedsRes.data;
    students.value = studentsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { student_id: null, bed_id: null, start_date: new Date().toISOString().slice(0, 10) });
    drawerOpen.value = true;
}

function openEdit(allocation) {
    editing.value = allocation;
    Object.assign(form, {
        student_id: allocation.student.id,
        bed_id: allocation.bed_id,
        start_date: allocation.start_date ? allocation.start_date.slice(0, 10) : '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/hostel/allocations/${editing.value.id}`, { bed_id: form.bed_id, start_date: form.start_date });
            pushToast('Allocation updated.', 'success');
        } else {
            await client.post('/hostel/allocations', form);
            pushToast('Bed allocated.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

function openVacate(allocation) {
    vacateTarget.value = allocation;
    vacateForm.end_date = new Date().toISOString().slice(0, 10);
    vacateDialogOpen.value = true;
}

async function confirmVacate() {
    saving.value = true;
    try {
        await client.patch(`/hostel/allocations/${vacateTarget.value.id}/vacate`, { end_date: vacateForm.end_date || null });
        pushToast(`${vacateTarget.value.student.name} vacated ${vacateTarget.value.bed.room.room_no}-${vacateTarget.value.bed.bed_no}.`, 'success');
        vacateDialogOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
