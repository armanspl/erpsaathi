<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">RTE Students</h1>
                <p class="mt-1 text-sm text-slate-500">Right to Education — these students pay no school fees. If they use transport, only the transport fee is charged.</p>
            </div>
            <button type="button" class="btn-primary" :disabled="!ready" @click="openAdd">+ Mark student as RTE</button>
        </div>

        <div v-if="!loading && !ready" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
            RTE needs a one-time database update. Run the migrations, then reload this page.
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="RTE students" :value="rows.length" color="indigo" icon="🎓" />
            <StatCard label="Using transport" :value="withTransport" color="amber" icon="🚌" />
            <StatCard label="No fee at all" :value="rows.length - withTransport" color="emerald" icon="✅" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <input v-model="search" type="search" class="w-72 max-w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Search name, admission no, class…" />
            <select v-model="classFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                <option value="">All classes</option>
                <option v-for="c in classOptions" :key="c" :value="c">{{ c }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-3">#</th>
                            <th class="px-3 py-3">Admission No</th>
                            <th class="px-3 py-3">Student</th>
                            <th class="px-3 py-3">Class</th>
                            <th class="px-3 py-3">Section</th>
                            <th class="px-3 py-3">Roll</th>
                            <th class="px-3 py-3">Father</th>
                            <th class="px-3 py-3">Mobile</th>
                            <th class="px-3 py-3">RTE from</th>
                            <th class="px-3 py-3">Transport</th>
                            <th class="px-3 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loading"><td colspan="11" class="px-4 py-12 text-center text-slate-400">Loading…</td></tr>
                        <tr v-else-if="!filtered.length"><td colspan="11" class="px-4 py-12 text-center text-slate-400">{{ rows.length ? 'No RTE students match.' : 'No students are marked RTE yet.' }}</td></tr>
                        <tr v-for="(r, i) in filtered" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-3 py-3 text-slate-400">{{ i + 1 }}</td>
                            <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                            <td class="px-3 py-3">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                                <span v-if="r.status !== 'Active'" class="text-xs text-rose-500">{{ r.status }}</span>
                            </td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ r.school_class || '—' }}</td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ r.section || '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ r.roll_no || '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ r.father || '—' }}</td>
                            <td class="px-3 py-3 tabular-nums text-slate-500">{{ r.mobile || '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ r.rte_from || 'All sessions' }}</td>
                            <td class="px-3 py-3">
                                <template v-if="r.transport_fare > 0">
                                    <p class="font-semibold text-amber-600">₹{{ money(r.transport_fare) }} / month</p>
                                    <p class="text-xs text-slate-400">{{ [r.transport_route, r.transport_stop].filter(Boolean).join(' · ') }}</p>
                                </template>
                                <span v-else class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">No fee</span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <button type="button" class="btn-outline !py-1 !text-xs" :disabled="busyId === r.id" @click="unmark(r)">Remove RTE</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mark a student as RTE -->
        <div v-if="addOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="addOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Mark student as RTE</h2>
                <p class="mt-1 text-sm text-slate-500">From the chosen session onward, school fees are not charged. Transport fee stays if the student uses transport. Earlier sessions keep their fees.</p>
                <div class="mt-4 space-y-3">
                    <input v-model="pickSearch" type="search" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Search student by name or admission no" @input="searchStudents" />
                    <div class="max-h-60 overflow-y-auto rounded-lg border border-slate-100 dark:border-slate-800">
                        <p v-if="picking" class="px-3 py-4 text-center text-xs text-slate-400">Searching…</p>
                        <p v-else-if="pickSearch.trim().length < 2" class="px-3 py-4 text-center text-xs text-slate-400">Type at least 2 letters.</p>
                        <p v-else-if="!pickResults.length" class="px-3 py-4 text-center text-xs text-slate-400">No students found.</p>
                        <label v-for="s in pickResults" :key="s.id" class="flex cursor-pointer items-center gap-3 border-b border-slate-50 px-3 py-2 text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                            <input v-model="pickId" type="radio" :value="s.id" :disabled="rteIds.has(s.id)" />
                            <span class="flex-1">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                                <span class="ml-1 text-xs text-slate-400">{{ s.admission_no }} · {{ [s.school_class?.name, s.section?.name].filter(Boolean).join(' ') }}</span>
                            </span>
                            <span v-if="rteIds.has(s.id)" class="text-xs font-semibold text-emerald-600">Already RTE</span>
                        </label>
                    </div>
                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">RTE from session</span>
                        <select v-model="fromSessionId" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}{{ s.is_current ? ' (current)' : '' }}</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="addOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!pickId || saving" @click="mark">{{ saving ? 'Saving…' : 'Mark as RTE' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import client from '../../api/client';
import StatCard from '../../components/common/StatCard.vue';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const ready = ref(true);
const rows = ref([]);
const sessions = ref([]);
const search = ref('');
const classFilter = ref('');
const busyId = ref(null);

const addOpen = ref(false);
const pickSearch = ref('');
const pickResults = ref([]);
const picking = ref(false);
const pickId = ref(null);
const fromSessionId = ref(null);
const saving = ref(false);

const rteIds = computed(() => new Set(rows.value.map((r) => r.id)));
const withTransport = computed(() => rows.value.filter((r) => r.transport_fare > 0).length);
const classOptions = computed(() => [...new Set(rows.value.map((r) => r.school_class).filter(Boolean))]);
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((r) => {
        if (classFilter.value && r.school_class !== classFilter.value) return false;
        if (!q) return true;
        return [r.name, r.admission_no, r.school_class, r.section, r.father, r.mobile].some((v) => String(v || '').toLowerCase().includes(q));
    });
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/people/students-rte');
        ready.value = data.ready !== false;
        rows.value = data.rows || [];
        sessions.value = data.sessions || [];
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    pickSearch.value = '';
    pickResults.value = [];
    pickId.value = null;
    fromSessionId.value = sessions.value.find((s) => s.is_current)?.id ?? sessions.value[0]?.id ?? null;
    addOpen.value = true;
}

let searchTimer = null;
function searchStudents() {
    clearTimeout(searchTimer);
    const term = pickSearch.value.trim();
    if (term.length < 2) {
        pickResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        picking.value = true;
        try {
            const { data } = await client.get('/people/students', { params: { for: 'fee', search: term, limit: 30 } });
            pickResults.value = Array.isArray(data) ? data : [];
        } finally {
            picking.value = false;
        }
    }, 250);
}

async function mark() {
    if (!pickId.value) return;
    saving.value = true;
    try {
        await client.patch(`/people/students/${pickId.value}/rte`, { is_rte: true, rte_from_session_id: fromSessionId.value });
        pushToast('Student marked as RTE — school fees will not be charged.', 'success');
        addOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function unmark(r) {
    if (!window.confirm(`Remove RTE for "${r.name}"? School fees will be charged again from ${r.rte_from || 'all sessions'}.`)) return;
    busyId.value = r.id;
    try {
        await client.patch(`/people/students/${r.id}/rte`, { is_rte: false });
        rows.value = rows.value.filter((x) => x.id !== r.id);
        pushToast('RTE removed.', 'success');
    } finally {
        busyId.value = null;
    }
}

onMounted(load);
</script>
