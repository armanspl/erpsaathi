<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Attendance', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openApply">+ Apply Leave</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="leave requests" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Pending" :value="all.filter((l) => l.status === 'Pending').length" color="amber" icon="⏳" />
            <StatCard label="Approved" :value="all.filter((l) => l.status === 'Approved').length" color="emerald" icon="✅" />
            <StatCard label="Rejected" :value="all.filter((l) => l.status === 'Rejected').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Leave Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">From — To</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredList.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Nothing here.</td>
                    </tr>
                    <tr v-for="l in filteredList" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ l.attendable_type }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ l.attendable_name }}<br /><span class="font-mono text-xs font-normal text-slate-400">{{ l.attendable_code }}</span></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ l.leave_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(l.from_date) }} — {{ formatDate(l.to_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(l.status === 'Approved' ? 'Active' : l.status === 'Rejected' ? 'Inactive' : 'Pending')">{{ l.status }}</span>
                            <span v-if="l.rejoined_at" class="ml-1 text-xs text-emerald-600 dark:text-emerald-400">(Rejoined {{ formatDate(l.rejoined_at) }})</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <template v-if="l.status === 'Pending'">
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="approve(l)">Approve</button>
                                    <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:border-slate-700 dark:hover:bg-rose-500/10" @click="openReject(l)">Reject</button>
                                </template>
                                <button v-else-if="l.status === 'Approved' && !l.rejoined_at" type="button" class="btn-outline !py-1 !text-xs" @click="rejoin(l)">Mark Rejoined</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(l)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Apply Leave -->
        <SlideOver :open="applyDrawerOpen" title="Apply Leave" @close="applyDrawerOpen = false">
            <div>
                <label class="form-label">Person Type</label>
                <select v-model="form.attendable_type" class="form-input" @change="onTypeChange">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="staff">Staff</option>
                    <option value="driver">Driver</option>
                </select>
            </div>
            <div>
                <label class="form-label">Person</label>
                <select v-model="form.attendable_id" class="form-input">
                    <option :value="null">Select person</option>
                    <option v-for="p in peopleOptions" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Leave Type</label>
                <select v-model="form.leave_type" class="form-input">
                    <option>Sick</option>
                    <option>Casual</option>
                    <option>Earned</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">From Date</label>
                    <input v-model="form.from_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">To Date</label>
                    <input v-model="form.to_date" type="date" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Reason</label>
                <textarea v-model="form.reason" rows="3" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="applyDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="submitApply">{{ saving ? 'Submitting...' : 'Submit' }}</button>
            </template>
        </SlideOver>

        <!-- Reject -->
        <SlideOver :open="!!rejecting" title="Reject Leave Request" @close="rejecting = null">
            <template v-if="rejecting">
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ rejecting.attendable_name }} — {{ rejecting.leave_type }} leave</p>
                <div>
                    <label class="form-label">Rejection Reason</label>
                    <input v-model="rejectionReason" type="text" class="form-input" required />
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="rejecting = null">Cancel</button>
                <button type="button" class="btn-primary" :disabled="!rejectionReason || saving" @click="submitReject">Reject</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const pageTitle = computed(() => ({
    '/attendance/leave-approval': 'Leave Approval',
}[route.path] || 'Leave Approval'));

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Pending', 'Approved', 'Rejected'] },
];

const loading = ref(true);
const saving = ref(false);
const all = ref([]);
const filterValues = reactive({});

async function load() {
    loading.value = true;
    const { data } = await client.get('/attendance/leave-requests');
    all.value = data;
    loading.value = false;
}
load();

const filteredList = computed(() => {
    let rows = all.value;
    if (route.path === '/attendance/leave-approval') {
        rows = rows.filter((l) => l.status === 'Pending');
    }

    return rows.filter((l) => {
        if (filterValues.search && !`${l.attendable_name} ${l.attendable_code} ${l.leave_type}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && l.status !== filterValues.status) return false;
        return true;
    });
});

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function approve(leave) {
    await client.patch(`/attendance/leave-requests/${leave.id}/approve`);
    pushToast('Leave approved.', 'success');
    await load();
}

const rejecting = ref(null);
const rejectionReason = ref('');
function openReject(leave) {
    rejecting.value = leave;
    rejectionReason.value = '';
}
async function submitReject() {
    saving.value = true;
    try {
        await client.patch(`/attendance/leave-requests/${rejecting.value.id}/reject`, { rejection_reason: rejectionReason.value });
        pushToast('Leave rejected.', 'success');
        rejecting.value = null;
        await load();
    } finally {
        saving.value = false;
    }
}

async function rejoin(leave) {
    await client.patch(`/attendance/leave-requests/${leave.id}/rejoin`);
    pushToast(`${leave.attendable_name} marked as rejoined.`, 'success');
    await load();
}

async function remove(leave) {
    all.value = all.value.filter((l) => l.id !== leave.id);
    await client.delete(`/attendance/leave-requests/${leave.id}`);
    pushToast('Leave request deleted.', 'success');
}

// --- Apply leave ---
const applyDrawerOpen = ref(false);
const peopleOptions = ref([]);
const form = reactive({ attendable_type: 'student', attendable_id: null, leave_type: 'Sick', from_date: '', to_date: '', reason: '' });

async function onTypeChange() {
    form.attendable_id = null;
    const { fetchPeopleLookups, fetchStudentsLite } = await import('../../api/people');
    let rows = [];
    if (form.attendable_type === 'student') {
        rows = await fetchStudentsLite();
    } else {
        const people = await fetchPeopleLookups();
        rows = people[form.attendable_type === 'teacher' ? 'teachers' : form.attendable_type === 'staff' ? 'staff' : 'drivers'] || [];
    }
    peopleOptions.value = rows.map((p) => ({ id: p.id, name: p.name, code: p.admission_no || p.employee_id }));
}

function openApply() {
    Object.assign(form, { attendable_type: 'student', attendable_id: null, leave_type: 'Sick', from_date: '', to_date: '', reason: '' });
    onTypeChange();
    applyDrawerOpen.value = true;
}

async function submitApply() {
    saving.value = true;
    try {
        await client.post('/attendance/leave-requests', form);
        pushToast('Leave request submitted.', 'success');
        applyDrawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
