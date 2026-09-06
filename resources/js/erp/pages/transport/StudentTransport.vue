<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Student Transport</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', 'Student Transport']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Assign Student</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="assignments" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Students Using Transport" :value="assignments.length" color="indigo" icon="🚌" />
            <StatCard label="Active" :value="assignments.filter((a) => a.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Monthly Fare Total" :value="`₹${totalFare.toLocaleString('en-IN')}`" color="sky" icon="💰" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Route</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stop</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fare</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredAssignments.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No students match your filters.</td>
                    </tr>
                    <tr v-for="a in filteredAssignments" :key="a.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ a.student.name }}</p>
                            <p class="text-xs text-slate-400">{{ a.student.admission_no }}<span v-if="a.student.school_class"> · {{ a.student.school_class.name }}</span></p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ a.route.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ a.route_stop.stop_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ Number(a.route_stop.fare).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(a.status)">{{ a.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(a)">✎</button>
                                <button type="button" title="Remove" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(a)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Assignment' : 'Assign Student'" @close="drawerOpen = false">
            <div v-if="!editing">
                <label class="form-label">Student</label>
                <select v-model.number="form.student_id" class="form-input">
                    <option :value="null">Select student</option>
                    <option v-for="s in unassignedStudents" :key="s.id" :value="s.id">{{ s.name }} ({{ s.admission_no }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Route</label>
                <select v-model.number="form.route_id" class="form-input" @change="form.route_stop_id = null">
                    <option :value="null">Select route</option>
                    <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Stop</label>
                <select v-model.number="form.route_stop_id" class="form-input" :disabled="!form.route_id">
                    <option :value="null">Select stop</option>
                    <option v-for="s in stopsForRoute" :key="s.id" :value="s.id">{{ s.stop_name }} — ₹{{ Number(s.fare).toLocaleString('en-IN') }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Start Date</label>
                    <input v-model="form.start_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Transport Fee Start Month</label>
                    <input v-model="form.fee_start_month" type="month" class="form-input" />
                </div>
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
const assignments = ref([]);
const routes = ref([]);
const stops = ref([]);
const students = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({
    student_id: null,
    route_id: null,
    route_stop_id: null,
    start_date: new Date().toISOString().slice(0, 10),
    fee_start_month: new Date().toISOString().slice(0, 7),
    status: 'Active',
});

const totalFare = computed(() => assignments.value.filter((a) => a.status === 'Active').reduce((sum, a) => sum + Number(a.route_stop.fare), 0));

const unassignedStudents = computed(() => {
    const assignedIds = new Set(assignments.value.map((a) => a.student.id));
    return students.value.filter((s) => !assignedIds.has(s.id));
});

const stopsForRoute = computed(() => stops.value.filter((s) => s.route_id === form.route_id));

const filteredAssignments = computed(() =>
    assignments.value.filter((a) => {
        if (filterValues.search && !`${a.student.name} ${a.student.admission_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && a.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [assignmentsRes, routesRes, studentsRes] = await Promise.all([
        client.get('/transport/student-transport'),
        client.get('/transport/routes', { params: { with_stops: 1 } }),
        client.get('/people/students'),
    ]);
    assignments.value = assignmentsRes.data;
    const routeList = Array.isArray(routesRes.data) ? routesRes.data : [];
    routes.value = routeList;
    students.value = studentsRes.data;
    stops.value = routeList.flatMap((r) =>
        (Array.isArray(r.stops) ? r.stops : []).map((s) => ({
            ...s,
            route_id: s.route_id ?? r.id,
        })),
    );

    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, {
        student_id: null,
        route_id: null,
        route_stop_id: null,
        start_date: new Date().toISOString().slice(0, 10),
        fee_start_month: new Date().toISOString().slice(0, 7),
        status: 'Active',
    });
    drawerOpen.value = true;
}

function openEdit(assignment) {
    editing.value = assignment;
    Object.assign(form, {
        student_id: assignment.student.id,
        route_id: assignment.route_id,
        route_stop_id: assignment.route_stop_id,
        start_date: assignment.start_date.slice(0, 10),
        fee_start_month: (assignment.fee_start_month || assignment.start_date || '').toString().slice(0, 7),
        status: assignment.status,
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/transport/student-transport/${editing.value.id}`, form);
            pushToast('Assignment updated.', 'success');
        } else {
            await client.post('/transport/student-transport', form);
            pushToast('Student assigned to transport.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(assignment) {
    assignments.value = assignments.value.filter((a) => a.id !== assignment.id);
    await client.delete(`/transport/student-transport/${assignment.id}`);
    pushToast(`Removed "${assignment.student.name}" from transport.`, 'success');
}
</script>
