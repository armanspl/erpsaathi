<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Follow-up</h1>
            <Breadcrumb :items="['Dashboard', 'Admissions', 'Follow-up']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="follow-ups" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Overdue" :value="overdue.length" color="rose" icon="⚠️" />
            <StatCard label="Due Today" :value="dueToday.length" color="amber" icon="📅" />
            <StatCard label="Upcoming" :value="upcoming.length" color="sky" icon="🔜" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Enquiry No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Parent / Phone</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stage</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Next Follow-up</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredList.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Nothing needs follow-up right now.</td>
                    </tr>
                    <tr v-for="e in filteredList" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ e.enquiry_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ e.student_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.parent_name }}<br /><span class="text-xs">{{ e.phone }}</span></td>
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ e.stage.replace('_', ' ') }}</td>
                        <td class="px-4 py-3">
                            <span v-if="e.next_follow_up_date" class="font-medium" :class="isOverdue(e) ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300'">{{ formatDate(e.next_follow_up_date) }}</span>
                            <span v-else class="text-slate-300">Not scheduled</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="openLog(e)">Log Follow-up</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="!!active" :title="`Follow-up — ${active?.student_name || ''}`" @close="active = null">
            <template v-if="active">
                <ul class="mb-3 max-h-48 space-y-2 overflow-y-auto">
                    <li v-for="f in history" :key="f.id" class="rounded-lg border border-slate-100 p-2 text-xs dark:border-slate-800">
                        <p class="text-slate-600 dark:text-slate-300">{{ f.note }}</p>
                        <p class="mt-1 text-slate-400">{{ formatDate(f.follow_up_date) }}<span v-if="f.next_follow_up_date"> • Next: {{ formatDate(f.next_follow_up_date) }}</span></p>
                    </li>
                    <li v-if="!history.length" class="text-xs text-slate-400">No follow-ups logged yet.</li>
                </ul>
                <div>
                    <label class="form-label">Note</label>
                    <textarea v-model="note" rows="3" class="form-input" placeholder="What was discussed?" />
                </div>
                <div>
                    <label class="form-label">Next Follow-up Date</label>
                    <input v-model="nextDate" type="date" class="form-input" />
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="active = null">Cancel</button>
                <button type="button" class="btn-primary" :disabled="!note || saving" @click="save">{{ saving ? 'Saving...' : 'Save Follow-up' }}</button>
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
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Stage', type: 'select', options: ['Enquiry', 'Follow_up', 'Registered'] },
];

const loading = ref(true);
const enquiries = ref([]);
const filterValues = reactive({});

async function load() {
    loading.value = true;
    const { data } = await client.get('/admissions/enquiries', { params: { open: 1, limit: 300 } });
    enquiries.value = data;
    loading.value = false;
}
load();

function isOverdue(e) {
    return e.next_follow_up_date && new Date(e.next_follow_up_date) < new Date(new Date().toDateString());
}
function isDueToday(e) {
    return e.next_follow_up_date && new Date(e.next_follow_up_date).toDateString() === new Date().toDateString();
}

const overdue = computed(() => enquiries.value.filter((e) => isOverdue(e)));
const dueToday = computed(() => enquiries.value.filter((e) => isDueToday(e)));
const upcoming = computed(() => enquiries.value.filter((e) => e.next_follow_up_date && !isOverdue(e) && !isDueToday(e)));

const filteredList = computed(() =>
    enquiries.value.filter((e) => {
        if (filterValues.search && !`${e.student_name} ${e.parent_name} ${e.phone} ${e.enquiry_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && e.stage !== filterValues.status.toLowerCase()) return false;
        return true;
    }),
);

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

const active = ref(null);
const history = ref([]);
const note = ref('');
const nextDate = ref('');
const saving = ref(false);

async function openLog(enquiry) {
    active.value = enquiry;
    note.value = '';
    nextDate.value = '';
    const { data } = await client.get(`/admissions/enquiries/${enquiry.id}/follow-ups`);
    history.value = data;
}

async function save() {
    saving.value = true;
    try {
        await client.post(`/admissions/enquiries/${active.value.id}/follow-ups`, { note: note.value, next_follow_up_date: nextDate.value || null });
        pushToast('Follow-up logged.', 'success');
        active.value = null;
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
