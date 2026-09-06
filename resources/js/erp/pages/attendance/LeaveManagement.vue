<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Leave Management</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Apply for leave, track status, and review pending requests.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="pushToast('Export columns coming soon.', 'info')">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h14"/></svg>
                    Export columns
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!filtered.length" @click="exportCsv">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openApply">
                    <span class="text-lg leading-none">+</span> Apply leave
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Requester, student, reason..." @keyup.enter="load" />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All statuses</option>
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">From date</label>
                        <input v-model="filters.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To date</label>
                        <input v-model="filters.to" type="date" class="form-input" />
                    </div>
                </div>
                <div class="mt-4">
                    <button type="button" class="btn-primary" :disabled="loading" @click="load">{{ loading ? 'Loading...' : 'Apply filters' }}</button>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <button
                    v-for="mode in viewModes"
                    :key="mode.id"
                    type="button"
                    class="rounded-md p-1.5 transition"
                    :class="viewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    :title="mode.label"
                    @click="viewMode = mode.id"
                >
                    <span v-html="mode.icon" />
                </button>
            </div>

            <div v-if="loading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No results.</div>

            <!-- Table view -->
            <table v-else-if="viewMode === 'table'" class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Requester</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dates</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Days</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="l in paged" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ l.attendable_name || '—' }}</div>
                            <div class="text-xs capitalize text-slate-400">{{ l.attendable_type }} · {{ l.leave_type }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ l.leave_type }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(l.from_date) }} — {{ formatDate(l.to_date) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ l.total_days ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(statusTone(l.status))">{{ l.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <template v-if="l.status === 'Pending'">
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="approve(l)">Approve</button>
                                    <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:border-slate-700" @click="openReject(l)">Reject</button>
                                </template>
                                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" title="Delete" @click="remove(l)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- List view -->
            <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="l in paged" :key="l.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <div>
                        <div class="font-medium text-slate-800 dark:text-slate-100">{{ l.attendable_name }}</div>
                        <div class="text-xs text-slate-400">{{ formatDate(l.from_date) }} — {{ formatDate(l.to_date) }} · {{ l.total_days }} day(s) · {{ l.leave_type }}</div>
                        <div v-if="l.reason_text" class="mt-1 line-clamp-1 text-xs text-slate-500">{{ l.reason_text }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(statusTone(l.status))">{{ l.status }}</span>
                        <template v-if="l.status === 'Pending'">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="approve(l)">Approve</button>
                            <button type="button" class="btn-outline !py-1 !text-xs !text-rose-600" @click="openReject(l)">Reject</button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Grid / cards -->
            <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="l in paged" :key="l.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ l.attendable_name }}</div>
                            <div class="text-xs capitalize text-slate-400">{{ l.attendable_type }} · {{ l.leave_type }}</div>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(statusTone(l.status))">{{ l.status }}</span>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">{{ formatDate(l.from_date) }} — {{ formatDate(l.to_date) }}</p>
                    <p class="text-xs text-slate-400">{{ l.total_days }} day(s)</p>
                    <p v-if="l.reason_text" class="mt-2 line-clamp-2 text-xs text-slate-500">{{ l.reason_text }}</p>
                    <div v-if="l.status === 'Pending'" class="mt-3 flex gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="approve(l)">Approve</button>
                        <button type="button" class="btn-outline !py-1 !text-xs !text-rose-600" @click="openReject(l)">Reject</button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <p class="text-xs text-slate-400">Showing {{ showingFrom }}“{{ showingTo }} of {{ filtered.length }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model.number="perPage" class="form-input !w-auto !py-1.5 !text-xs">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page <= 1" @click="page -= 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span>Page {{ page }} of {{ totalPages }}</span>
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page >= totalPages" @click="page += 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply for leave modal -->
        <div v-if="applyOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeApply" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Apply for leave</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Submitting as {{ erpStore.user.name || 'User' }}.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="closeApply">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div v-if="!linkedProfile" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Person type</label>
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
                                <option v-for="p in peopleOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-else class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/60">
                        Leave for <span class="font-semibold text-slate-700 dark:text-slate-200">{{ linkedProfile.name }}</span> ({{ linkedProfile.attendable_type }})
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Start date</label>
                            <input v-model="form.from_date" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">End date</label>
                            <input v-model="form.to_date" type="date" class="form-input" />
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Total days</label>
                        <input :value="totalDaysLabel" type="text" class="form-input bg-slate-50 dark:bg-slate-800/50" readonly />
                    </div>

                    <div>
                        <label class="form-label">Reason</label>
                        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                            <div class="flex gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/50">
                                <button type="button" class="rounded px-2 py-1 text-xs font-bold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Bold" @click="formatReason('bold')">B</button>
                                <button type="button" class="rounded px-2 py-1 text-xs italic text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Italic" @click="formatReason('italic')">I</button>
                                <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Numbered list" @click="formatReason('insertOrderedList')">1.</button>
                                <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Bulleted list" @click="formatReason('insertUnorderedList')">-</button>
                                <button type="button" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" title="Clear formatting" @click="formatReason('removeFormat')">Tx</button>
                            </div>
                            <div
                                ref="reasonEditor"
                                class="min-h-[120px] px-3 py-2 text-sm text-slate-800 outline-none empty:before:text-slate-400 empty:before:italic empty:before:content-[attr(data-placeholder)] dark:text-slate-100"
                                contenteditable="true"
                                data-placeholder="Describe the reason for leave..."
                                @input="syncReason"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Attachment (optional)</label>
                        <label class="btn-outline inline-flex cursor-pointer items-center gap-2 !py-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Choose file
                            <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" @change="onAttachment" />
                        </label>
                        <p v-if="form.attachmentName" class="mt-1 text-xs text-slate-500">{{ form.attachmentName }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="closeApply">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="submitApply">{{ saving ? 'Submitting...' : 'Submit request' }}</button>
                </div>
            </div>
        </div>

        <!-- Reject modal -->
        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="rejecting = null" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Reject leave request</h2>
                <p class="mt-1 text-sm text-slate-500">{{ rejecting.attendable_name }} — {{ rejecting.leave_type }}</p>
                <div class="mt-4">
                    <label class="form-label">Rejection reason</label>
                    <input v-model="rejectionReason" type="text" class="form-input" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="rejecting = null">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!rejectionReason || saving" @click="submitReject">Reject</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { fetchPeopleLookups, fetchStudentsLite } from '../../api/people';
import client from '../../api/client';
import { erpStore } from '../../store';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const viewModes = [
    {
        id: 'table',
        label: 'Table',
        icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
    },
    {
        id: 'list',
        label: 'List',
        icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
    },
    {
        id: 'grid',
        label: 'Grid',
        icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>',
    },
];

const filtersOpen = ref(true);
const viewMode = ref('table');
const loading = ref(true);
const saving = ref(false);
const all = ref([]);
const filters = reactive({ search: '', status: '', from: '', to: '' });
const page = ref(1);
const perPage = ref(20);

const applyOpen = ref(false);
const linkedProfile = ref(null);
const peopleOptions = ref([]);
const reasonEditor = ref(null);
const form = reactive({
    attendable_type: 'student',
    attendable_id: null,
    from_date: '',
    to_date: '',
    reason: '',
    attachment: null,
    attachmentName: '',
});

const rejecting = ref(null);
const rejectionReason = ref('');


const filtered = computed(() => all.value);

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const paged = computed(() => {
    const start = (page.value - 1) * perPage.value;
    return filtered.value.slice(start, start + perPage.value);
});
const showingFrom = computed(() => (filtered.value.length ? (page.value - 1) * perPage.value + 1 : 0));
const showingTo = computed(() => Math.min(page.value * perPage.value, filtered.value.length));

const totalDaysLabel = computed(() => {
    if (!form.from_date || !form.to_date) return '—';
    const from = new Date(form.from_date);
    const to = new Date(form.to_date);
    if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime()) || to < from) return '—';
    const days = Math.round((to - from) / 86400000) + 1;
    return String(days);
});

function statusTone(status) {
    if (status === 'Approved') return 'Active';
    if (status === 'Rejected') return 'Inactive';
    return 'Pending';
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function load() {
    loading.value = true;
    page.value = 1;
    try {
        const params = {};
        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.status) params.status = filters.status;
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;
        const { data } = await client.get('/attendance/leave-requests', { params });
        all.value = data;
    } finally {
        loading.value = false;
    }
}

async function onTypeChange() {
    form.attendable_id = null;
    let rows = [];
    if (form.attendable_type === 'student') {
        rows = await fetchStudentsLite();
    } else {
        const people = await fetchPeopleLookups();
        rows = people[form.attendable_type === 'teacher' ? 'teachers' : form.attendable_type === 'staff' ? 'staff' : 'drivers'] || [];
    }
    peopleOptions.value = rows.map((p) => ({ id: p.id, name: p.name, code: p.admission_no || p.employee_id }));
}

async function openApply() {
    Object.assign(form, {
        attendable_type: 'student',
        attendable_id: null,
        from_date: '',
        to_date: '',
        reason: '',
        attachment: null,
        attachmentName: '',
    });
    linkedProfile.value = null;
    applyOpen.value = true;
    await nextTick();
    if (reasonEditor.value) reasonEditor.value.innerHTML = '';

    try {
        const { data } = await client.get('/attendance/leave-requests/my-profile');
        if (data.profile) {
            linkedProfile.value = data.profile;
            form.attendable_type = data.profile.attendable_type;
            form.attendable_id = data.profile.attendable_id;
        } else {
            await onTypeChange();
        }
    } catch {
        await onTypeChange();
    }
}

function closeApply() {
    applyOpen.value = false;
}

function syncReason() {
    form.reason = reasonEditor.value?.innerHTML || '';
}

function formatReason(command) {
    reasonEditor.value?.focus();
    document.execCommand(command, false);
    syncReason();
}

function onAttachment(event) {
    const file = event.target.files?.[0] || null;
    form.attachment = file;
    form.attachmentName = file?.name || '';
}

async function submitApply() {
    if (!form.attendable_id) {
        pushToast('Select who the leave is for.', 'error');
        return;
    }
    if (!form.from_date || !form.to_date) {
        pushToast('Start and end dates are required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const body = new FormData();
        body.append('attendable_type', form.attendable_type);
        body.append('attendable_id', String(form.attendable_id));
        body.append('leave_type', 'Casual');
        body.append('from_date', form.from_date);
        body.append('to_date', form.to_date);
        if (form.reason) body.append('reason', form.reason);
        if (form.attachment) body.append('attachment', form.attachment);
        await client.post('/attendance/leave-requests', body);
        pushToast('Leave request submitted.', 'success');
        closeApply();
        await load();
    } finally {
        saving.value = false;
    }
}

async function approve(leave) {
    await client.patch(`/attendance/leave-requests/${leave.id}/approve`);
    pushToast('Leave approved.', 'success');
    await load();
}

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

async function remove(leave) {
    if (!window.confirm(`Delete leave request for ${leave.attendable_name}?`)) return;
    await client.delete(`/attendance/leave-requests/${leave.id}`);
    pushToast('Leave request deleted.', 'success');
    await load();
}

function exportCsv() {
    const lines = [['Requester', 'Type', 'Person Type', 'From', 'To', 'Days', 'Status', 'Reason']];
    filtered.value.forEach((l) => {
        lines.push([
            l.attendable_name || '',
            l.leave_type || '',
            l.attendable_type || '',
            String(l.from_date || '').slice(0, 10),
            String(l.to_date || '').slice(0, 10),
            l.total_days ?? '',
            l.status || '',
            l.reason_text || '',
        ]);
    });
    const csv = lines.map((row) => row.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'leave-management.csv';
    a.click();
    URL.revokeObjectURL(url);
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });

onMounted(load);
</script>
