<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Driver Attendance</h1>
        </div>

        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- MARK -->
        <template v-if="activeTab === 'mark'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="space-y-4 max-w-xl">
                    <div>
                        <label class="form-label">Driver</label>
                        <select v-model="mark.driver_id" class="form-input" @change="loadMarkStatus">
                            <option :value="null">Select driver</option>
                            <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}{{ d.vehicle_no ? ` · ${d.vehicle_no}` : '' }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Attendance date</label>
                        <input v-model="mark.date" type="date" class="form-input" @change="loadMarkStatus" />
                    </div>
                    <div>
                        <label class="form-label">Today</label>
                        <textarea v-model="mark.remarks" rows="4" class="form-input" placeholder="What did you do today? (optional)" />
                    </div>
                    <div v-if="markPreview" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <img :src="markPreview" alt="Check-in preview" class="max-h-48 w-full object-cover" />
                    </div>
                    <div v-if="markStatus" class="text-sm text-slate-500">
                        Already marked:
                        <span class="font-semibold" :class="statusBadgeClass(markStatus.status)">{{ markStatus.status }}</span>
                        <span v-if="markStatus.has_photo" class="ml-2 text-xs text-slate-400">· photo saved</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn-primary inline-flex items-center gap-2" :disabled="markSaving || !mark.driver_id" @click="triggerCheckIn">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ markSaving ? 'Checking in...' : 'Check in' }}
                        </button>
                        <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200" :disabled="markSaving || !mark.driver_id" @click="submitCheckIn">
                            Check in without photo
                        </button>
                        <button v-if="markPhoto || markPreview" type="button" class="btn-outline !text-xs" @click="clearPhoto">Clear photo</button>
                    </div>
                    <input ref="photoInput" type="file" accept="image/*" capture="user" class="hidden" @change="onPhotoSelected" />
                </div>
            </div>
        </template>

        <!-- ROUTE STUDENTS -->
        <template v-else-if="activeTab === 'route'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <label class="form-label">Attendance date</label>
                <input v-model="route.date" type="date" class="form-input max-w-xs" @change="onRouteFiltersChange" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <label class="form-label">Notes</label>
                <textarea v-model="route.notes" rows="4" class="form-input" placeholder="Optional route notes" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <label class="form-label">Driver</label>
                <select v-model="route.driver_id" class="form-input max-w-md" @change="onRouteFiltersChange">
                    <option :value="null">Select driver</option>
                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}{{ d.vehicle_no ? ` · ${d.vehicle_no}` : '' }}</option>
                </select>
            </div>

            <div v-if="!route.driver_id" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                Select a driver to load route students.
            </div>
            <template v-else>
                <div v-if="routeLoading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
                <div v-else-if="!routeStudents.length" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                    {{ routeMessage || "No students assigned to this driver's routes." }}
                </div>
                <template v-else>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-slate-400">Mark All:</span>
                            <button v-for="s in statuses" :key="s" type="button" class="btn-outline !py-1 !text-xs" @click="markAllRoute(s)">{{ s }}</button>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Route / Stop</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="p in routeStudents" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.code || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</div>
                                        <div v-if="p.meta" class="text-xs text-slate-400">{{ p.meta }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        <div>{{ p.route || '—' }}</div>
                                        <div v-if="p.stop" class="text-xs text-slate-400">{{ p.stop }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                v-for="s in statuses"
                                                :key="s"
                                                type="button"
                                                class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                                                :class="p.status === s ? statusActiveClass(s) : 'border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400'"
                                                @click="p.status = s"
                                            >{{ s }}</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" class="btn-primary" :disabled="routeSaving" @click="saveRouteStudents">
                            {{ routeSaving ? 'Saving...' : 'Save route attendance' }}
                        </button>
                    </div>
                </template>
            </template>
        </template>

        <!-- HISTORY -->
        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="history.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="history.to" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="history.search" type="search" class="form-input" placeholder="Name or phone..." @keyup.enter="loadHistory" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <button type="button" class="btn-primary" :disabled="historyLoading" @click="loadHistory">
                        {{ historyLoading ? 'Loading...' : 'Apply filters' }}
                    </button>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="pushToast('Export columns coming soon.', 'info')">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h14"/></svg>
                            Export columns
                        </button>
                        <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!historyRecords.length" @click="exportHistoryCsv">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="historyLoading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!historyRecords.length" class="px-6 py-16 text-center text-sm text-slate-400">
                    No driver attendance records found.
                </div>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Driver</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in historyRecords" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-800 dark:text-slate-100">{{ formatDisplayDate(r.date) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.driver?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ r.driver?.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ r.driver?.vehicle_no || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.status)">{{ r.status }}</span>
                                <span v-if="r.has_photo" class="ml-1 text-[10px] font-medium uppercase text-slate-400">photo</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ r.remarks || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { fetchPeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'mark', label: 'Mark' },
    { id: 'route', label: 'Route students' },
    { id: 'history', label: 'History' },
];
const activeTab = ref('mark');
const statuses = ['Present', 'Absent', 'Leave', 'Late', 'Half Day'];

const drivers = ref([]);
const photoInput = ref(null);

const mark = reactive({
    driver_id: null,
    date: new Date().toISOString().slice(0, 10),
    remarks: '',
});
const markStatus = ref(null);
const markPhoto = ref(null);
const markPreview = ref('');
const markSaving = ref(false);
const pendingCheckIn = ref(false);

const route = reactive({
    driver_id: null,
    date: new Date().toISOString().slice(0, 10),
    notes: '',
});
const routeStudents = ref([]);
const routeMessage = ref('');
const routeLoading = ref(false);
const routeSaving = ref(false);

const history = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    search: '',
});
const historyRecords = ref([]);
const historyLoading = ref(false);

function statusActiveClass(status) {
    return {
        Present: 'bg-emerald-600 text-white',
        Absent: 'bg-rose-600 text-white',
        Leave: 'bg-amber-500 text-white',
        Late: 'bg-sky-600 text-white',
        'Half Day': 'bg-violet-600 text-white',
    }[status] || 'bg-primary-600 text-white';
}

function formatDisplayDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadDrivers() {
    const people = await fetchPeopleLookups();
    const data = people.drivers || [];
    drivers.value = data.filter((d) => String(d.status || '').toLowerCase() !== 'inactive');
}

async function loadMarkStatus() {
    markStatus.value = null;
    if (!mark.driver_id || !mark.date) return;
    try {
        const { data } = await client.get('/attendance/driver', { params: { date: mark.date } });
        const person = (data.people || []).find((p) => p.id === mark.driver_id);
        if (person?.status) {
            markStatus.value = { status: person.status, has_photo: false };
            if (!mark.remarks && person.remarks) mark.remarks = person.remarks;
        }
    } catch {
        /* toast via interceptor */
    }
}

function triggerCheckIn() {
    if (!mark.driver_id) {
        pushToast('Select a driver first.', 'error');
        return;
    }
    if (markPhoto.value) {
        submitCheckIn();
        return;
    }
    pendingCheckIn.value = true;
    photoInput.value?.click();
}

function onPhotoSelected(event) {
    const file = event.target.files?.[0] || null;
    event.target.value = '';
    if (file) {
        markPhoto.value = file;
        markPreview.value = URL.createObjectURL(file);
    }
    if (pendingCheckIn.value) {
        pendingCheckIn.value = false;
        submitCheckIn();
    }
}

function clearPhoto() {
    if (markPreview.value) URL.revokeObjectURL(markPreview.value);
    markPhoto.value = null;
    markPreview.value = '';
}

async function submitCheckIn() {
    if (!mark.driver_id) return;
    markSaving.value = true;
    try {
        const form = new FormData();
        form.append('driver_id', String(mark.driver_id));
        form.append('date', mark.date);
        if (mark.remarks) form.append('remarks', mark.remarks);
        if (markPhoto.value) form.append('photo', markPhoto.value);
        const { data } = await client.post('/attendance/driver/check-in', form);
        markStatus.value = {
            status: data.record?.status || 'Present',
            has_photo: Boolean(data.record?.has_photo),
        };
        pushToast('Driver checked in.', 'success');
        clearPhoto();
    } catch {
        /* toast via interceptor */
    } finally {
        markSaving.value = false;
    }
}

async function onRouteFiltersChange() {
    routeStudents.value = [];
    routeMessage.value = '';
    if (!route.driver_id) return;
    routeLoading.value = true;
    try {
        const { data } = await client.get('/attendance/driver/route-students', {
            params: { driver_id: route.driver_id, date: route.date },
        });
        routeStudents.value = data.students || [];
        routeMessage.value = data.message || '';
        if (data.notes != null && data.notes !== '') route.notes = data.notes;
    } finally {
        routeLoading.value = false;
    }
}

function markAllRoute(status) {
    routeStudents.value.forEach((p) => { p.status = status; });
}

async function saveRouteStudents() {
    const records = routeStudents.value
        .filter((p) => p.status)
        .map((p) => ({ attendable_id: p.id, status: p.status, remarks: p.remarks || null }));
    if (!records.length) {
        pushToast('Mark at least one student before saving.', 'error');
        return;
    }
    routeSaving.value = true;
    try {
        await client.post('/attendance/driver/route-students', {
            driver_id: route.driver_id,
            date: route.date,
            notes: route.notes || null,
            records,
        });
        pushToast(`Saved attendance for ${records.length} student(s).`, 'success');
        await onRouteFiltersChange();
    } catch {
        /* toast via interceptor */
    } finally {
        routeSaving.value = false;
    }
}

async function loadHistory() {
    historyLoading.value = true;
    try {
        const params = { from: history.from, to: history.to };
        if (history.search.trim()) params.search = history.search.trim();
        const { data } = await client.get('/attendance/driver/history', { params });
        historyRecords.value = data.records || [];
    } finally {
        historyLoading.value = false;
    }
}

function exportHistoryCsv() {
    const lines = [['Date', 'Driver', 'Phone', 'Vehicle', 'Status', 'Notes', 'Photo']];
    historyRecords.value.forEach((r) => {
        lines.push([
            r.date,
            r.driver?.name || '',
            r.driver?.phone || '',
            r.driver?.vehicle_no || '',
            r.status,
            r.remarks || '',
            r.has_photo ? 'Yes' : 'No',
        ]);
    });
    const csv = lines.map((row) => row.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'driver-attendance-history.csv';
    a.click();
    URL.revokeObjectURL(url);
}

watch(activeTab, (tab) => {
    if (tab === 'history' && !historyRecords.value.length) loadHistory();
});

onMounted(loadDrivers);
</script>
