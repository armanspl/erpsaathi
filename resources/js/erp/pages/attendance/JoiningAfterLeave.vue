<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Joining After Leave</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Record return-to-duty for people whose approved leave has ended.
                </p>
            </div>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="loading" @click="load">
                <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500">Awaiting rejoin</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ awaitingCount }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/30">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400">Overdue</p>
                <p class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-300">{{ overdueCount }}</p>
                <p class="mt-0.5 text-[11px] text-amber-600/80 dark:text-amber-500/80">Leave ended, not yet rejoined</p>
            </div>
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-4 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/30">
                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Rejoined (30 days)</p>
                <p class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-300">{{ recentRejoinedCount }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ tab.count }}</span>
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name, code, leave type..." />
                </div>
                <div>
                    <label class="form-label">Person type</label>
                    <select v-model="filters.type" class="form-input">
                        <option value="">All types</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="staff">Staff</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>
                <div class="sm:col-span-2 flex items-end">
                    <button type="button" class="btn-outline !text-xs" @click="resetFilters">Clear filters</button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!visibleRows.length" class="px-6 py-16 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">{{ emptyTitle }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ emptySubtitle }}</p>
            </div>
            <table v-else class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Person</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Leave</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Period</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ activeTab === 'rejoined' ? 'Rejoined' : 'Status' }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="l in visibleRows" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ l.attendable_name || '—' }}</div>
                            <div class="text-xs capitalize text-slate-400">
                                {{ l.attendable_type }}
                                <span v-if="l.attendable_code"> · {{ l.attendable_code }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-slate-700 dark:text-slate-200">{{ l.leave_type }}</div>
                            <div v-if="l.reason_text" class="mt-0.5 line-clamp-1 text-xs text-slate-400">{{ l.reason_text }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <div>{{ formatDate(l.from_date) }} — {{ formatDate(l.to_date) }}</div>
                            <div class="text-xs text-slate-400">{{ l.total_days ?? '—' }} day(s)</div>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="activeTab === 'rejoined'">
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400">
                                    {{ formatDate(l.rejoined_at) }}
                                </span>
                            </template>
                            <template v-else>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="l.is_overdue
                                        ? 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300'
                                        : 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300'"
                                >
                                    {{ l.is_overdue ? overdueLabel(l) : 'Awaiting rejoin' }}
                                </span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                v-if="activeTab !== 'rejoined'"
                                type="button"
                                class="btn-primary !py-1.5 !text-xs"
                                @click="openRejoin(l)"
                            >
                                Mark rejoined
                            </button>
                            <span v-else class="text-xs text-slate-400">Done</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Rejoin modal -->
        <div v-if="rejoining" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeRejoin" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Mark as rejoined</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ rejoining.attendable_name }} · {{ rejoining.leave_type }} leave
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="closeRejoin">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 space-y-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800/60">
                    <div class="flex justify-between gap-2">
                        <span>Leave period</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ formatDate(rejoining.from_date) }} — {{ formatDate(rejoining.to_date) }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>Expected return</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ formatDate(rejoining.to_date) }}</span>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Joining date</label>
                    <input v-model="rejoinDate" type="date" class="form-input" />
                    <p class="mt-1 text-[11px] text-slate-400">Defaults to today. Change if they returned on a different date.</p>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="closeRejoin">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving || !rejoinDate" @click="submitRejoin">
                        {{ saving ? 'Saving...' : 'Confirm joining' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const awaiting = ref([]);
const rejoined = ref([]);
const activeTab = ref('awaiting');
const filters = reactive({ search: '', type: '' });

const rejoining = ref(null);
const rejoinDate = ref('');

const tabs = computed(() => [
    { id: 'awaiting', label: 'Awaiting rejoin', count: filteredAwaiting.value.length },
    { id: 'rejoined', label: 'Recently rejoined', count: filteredRejoined.value.length },
]);

const awaitingCount = computed(() => awaiting.value.length);
const overdueCount = computed(() => awaiting.value.filter((l) => l.is_overdue).length);
const recentRejoinedCount = computed(() => rejoined.value.length);

function matchesFilters(row) {
    if (filters.type && row.attendable_type !== filters.type) return false;
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        const hay = `${row.attendable_name || ''} ${row.attendable_code || ''} ${row.leave_type || ''} ${row.reason_text || ''}`.toLowerCase();
        if (!hay.includes(term)) return false;
    }
    return true;
}

const filteredAwaiting = computed(() => awaiting.value.filter(matchesFilters));
const filteredRejoined = computed(() => rejoined.value.filter(matchesFilters));

const visibleRows = computed(() => (
    activeTab.value === 'rejoined' ? filteredRejoined.value : filteredAwaiting.value
));

const emptyTitle = computed(() => (
    activeTab.value === 'rejoined' ? 'No recent rejoins' : 'No one awaiting rejoin'
));
const emptySubtitle = computed(() => (
    activeTab.value === 'rejoined'
        ? 'People marked as rejoined in the last 30 days will appear here.'
        : 'Approved leave that still needs a joining date will show up here.'
));

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function overdueLabel(leave) {
    const end = new Date(leave.to_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    const days = Math.round((today - end) / 86400000);
    if (days <= 0) return 'Overdue';
    return days === 1 ? '1 day overdue' : `${days} days overdue`;
}

function resetFilters() {
    filters.search = '';
    filters.type = '';
}

async function load() {
    loading.value = true;
    try {
        const since = new Date();
        since.setDate(since.getDate() - 30);
        const sinceStr = since.toISOString().slice(0, 10);

        const [awaitingRes, rejoinedRes] = await Promise.all([
            client.get('/attendance/leave-requests', { params: { awaiting_rejoin: 1 } }),
            client.get('/attendance/leave-requests', { params: { rejoined: 1, from: sinceStr } }),
        ]);

        awaiting.value = (awaitingRes.data || []).sort((a, b) => {
            if (a.is_overdue !== b.is_overdue) return a.is_overdue ? -1 : 1;
            return String(a.to_date).localeCompare(String(b.to_date));
        });
        rejoined.value = (rejoinedRes.data || []).sort((a, b) => String(b.rejoined_at).localeCompare(String(a.rejoined_at)));
    } finally {
        loading.value = false;
    }
}

function openRejoin(leave) {
    rejoining.value = leave;
    rejoinDate.value = new Date().toISOString().slice(0, 10);
}

function closeRejoin() {
    rejoining.value = null;
    rejoinDate.value = '';
}

async function submitRejoin() {
    if (!rejoining.value || !rejoinDate.value) return;
    saving.value = true;
    try {
        await client.patch(`/attendance/leave-requests/${rejoining.value.id}/rejoin`, {
            rejoined_at: rejoinDate.value,
        });
        pushToast(`${rejoining.value.attendable_name} marked as rejoined.`, 'success');
        closeRejoin();
        activeTab.value = 'rejoined';
        await load();
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
