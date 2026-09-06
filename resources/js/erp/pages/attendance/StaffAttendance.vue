<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Staff Attendance</h1>
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
                    <div v-if="!person && !markLoading" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                        {{ markMessage || 'No attendance profile linked to your login.' }}
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
                        <button type="button" class="btn-primary inline-flex items-center gap-2" :disabled="markSaving || !person" @click="triggerCheckIn">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ markSaving ? 'Checking in...' : 'Check in' }}
                        </button>
                        <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200" :disabled="markSaving || !person" @click="submitCheckIn">
                            Check in without photo
                        </button>
                        <button v-if="markPhoto || markPreview" type="button" class="btn-outline !text-xs" @click="clearPhoto">Clear photo</button>
                    </div>
                    <input ref="photoInput" type="file" accept="image/*" capture="user" class="hidden" @change="onPhotoSelected" />
                </div>
            </div>
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
                    No attendance records found.
                </div>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in historyRecords" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-800 dark:text-slate-100">{{ formatDisplayDate(r.date) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.staff?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ r.staff?.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ r.staff?.department || '—' }}</td>
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
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'mark', label: 'Mark' },
    { id: 'history', label: 'History' },
];
const activeTab = ref('mark');

const person = ref(null);
const markMessage = ref('');
const markLoading = ref(true);
const photoInput = ref(null);

const mark = reactive({
    date: new Date().toISOString().slice(0, 10),
    remarks: '',
});
const markStatus = ref(null);
const markPhoto = ref(null);
const markPreview = ref('');
const markSaving = ref(false);
const pendingCheckIn = ref(false);

const history = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    search: '',
});
const historyRecords = ref([]);
const historyLoading = ref(false);

function formatDisplayDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadMarkStatus() {
    markLoading.value = true;
    try {
        const { data } = await client.get('/attendance/staff/mine', { params: { date: mark.date } });
        person.value = data.person;
        markMessage.value = data.message || '';
        if (data.today) {
            markStatus.value = data.today;
            if (!mark.remarks && data.today.remarks) mark.remarks = data.today.remarks;
        } else {
            markStatus.value = null;
        }
    } finally {
        markLoading.value = false;
    }
}

function triggerCheckIn() {
    if (!person.value) {
        pushToast('No attendance profile linked to your login.', 'error');
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
    if (!person.value) return;
    markSaving.value = true;
    try {
        const form = new FormData();
        form.append('date', mark.date);
        if (mark.remarks) form.append('remarks', mark.remarks);
        if (markPhoto.value) form.append('photo', markPhoto.value);
        const { data } = await client.post('/attendance/staff/check-in', form);
        markStatus.value = {
            status: data.record?.status || 'Present',
            has_photo: Boolean(data.record?.has_photo),
        };
        pushToast('Checked in.', 'success');
        clearPhoto();
    } catch {
        /* toast via interceptor */
    } finally {
        markSaving.value = false;
    }
}

async function loadHistory() {
    historyLoading.value = true;
    try {
        const params = { from: history.from, to: history.to };
        if (history.search.trim()) params.search = history.search.trim();
        const { data } = await client.get('/attendance/staff/history', { params });
        historyRecords.value = data.records || [];
    } finally {
        historyLoading.value = false;
    }
}

function exportHistoryCsv() {
    const lines = [['Date', 'Staff', 'Phone', 'Department', 'Status', 'Notes', 'Photo']];
    historyRecords.value.forEach((r) => {
        lines.push([
            r.date,
            r.staff?.name || '',
            r.staff?.phone || '',
            r.staff?.department || '',
            r.status,
            r.remarks || '',
            r.has_photo ? 'Yes' : 'No',
        ]);
    });
    const csv = lines.map((row) => row.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([`ï»¿${csv}`], { type: 'text/csv;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'staff-attendance-history.csv';
    a.click();
    URL.revokeObjectURL(url);
}

watch(activeTab, (tab) => {
    if (tab === 'history' && !historyRecords.value.length) loadHistory();
});

onMounted(loadMarkStatus);
</script>
