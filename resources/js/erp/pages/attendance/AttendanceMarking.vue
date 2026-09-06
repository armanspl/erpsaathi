<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Attendance', pageTitle]" class="mt-1" />
            </div>
            <div class="flex items-center gap-2">
                <input v-model="date" type="date" class="form-input w-auto" @change="load" />
                <button type="button" class="btn-outline" @click="simulateExport">📤 Export</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Present" :value="counts.Present" color="emerald" icon="✅" />
            <StatCard label="Absent" :value="counts.Absent" color="rose" icon="⛔" />
            <StatCard label="Leave" :value="counts.Leave" color="amber" icon="🌴" />
            <StatCard label="Late" :value="counts.Late" color="sky" icon="⏰" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-slate-400">Mark All:</span>
                <button v-for="s in statuses" :key="s" type="button" class="btn-outline !py-1 !text-xs" @click="markAll(s)">{{ s }}</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ metaLabel }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!people.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No records found.</td>
                    </tr>
                    <tr v-for="p in people" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.code || '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.meta || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="s in statuses"
                                    :key="s"
                                    type="button"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                                    :class="p.status === s ? statusActiveClass(s) : 'border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800'"
                                    @click="p.status = s"
                                >
                                    {{ s }}
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <input v-model="p.remarks" type="text" class="form-input !py-1 !text-xs" placeholder="Optional" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : '💾 Save Attendance' }}</button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const TYPE_BY_PATH = {
    '/attendance/student-attendance': { type: 'student', title: 'Student Attendance', meta: 'Class' },
    '/attendance/teacher-attendance': { type: 'teacher', title: 'Teacher Attendance', meta: 'Class' },
    '/attendance/staff-attendance': { type: 'staff', title: 'Staff Attendance', meta: 'Department' },
    '/attendance/driver-attendance': { type: 'driver', title: 'Driver Attendance', meta: 'Vehicle' },
};
const config = computed(() => TYPE_BY_PATH[route.path] || TYPE_BY_PATH['/attendance/student-attendance']);
const pageTitle = computed(() => config.value.title);
const metaLabel = computed(() => config.value.meta);

const statuses = ['Present', 'Absent', 'Leave', 'Late', 'Half Day'];

function statusActiveClass(status) {
    return {
        Present: 'bg-emerald-600 text-white',
        Absent: 'bg-rose-600 text-white',
        Leave: 'bg-amber-500 text-white',
        Late: 'bg-sky-600 text-white',
        'Half Day': 'bg-violet-600 text-white',
    }[status];
}

const date = ref(new Date().toISOString().slice(0, 10));
const loading = ref(true);
const saving = ref(false);
const people = ref([]);

const counts = computed(() => ({
    Present: people.value.filter((p) => p.status === 'Present').length,
    Absent: people.value.filter((p) => p.status === 'Absent').length,
    Leave: people.value.filter((p) => p.status === 'Leave').length,
    Late: people.value.filter((p) => p.status === 'Late').length,
}));

async function load() {
    loading.value = true;
    const { data } = await client.get(`/attendance/${config.value.type}`, { params: { date: date.value } });
    people.value = data.people;
    loading.value = false;
}
load();

function markAll(status) {
    people.value.forEach((p) => (p.status = status));
}

async function save() {
    const records = people.value.filter((p) => p.status).map((p) => ({ attendable_id: p.id, status: p.status, remarks: p.remarks || null }));
    if (!records.length) {
        pushToast('Mark at least one status before saving.', 'error');
        return;
    }
    saving.value = true;
    try {
        await client.post(`/attendance/${config.value.type}`, { date: date.value, records });
        pushToast(`Attendance saved for ${records.length} record(s).`, 'success');
        await load();
    } finally {
        saving.value = false;
    }
}

function simulateExport() {
    pushToast(`${pageTitle.value} export — demo simulation.`, 'info');
}
</script>
