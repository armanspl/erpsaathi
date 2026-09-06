<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Library Cards</h1>
            <Breadcrumb :items="['Dashboard', 'Documents', 'Library Cards']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="members" @reset="filterValues = {}" />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Card No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredMembers.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No library members match your filters.</td>
                    </tr>
                    <tr v-for="m in filteredMembers" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ m.library_card_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ m.member_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{{ m.member_type }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(m.status)">{{ m.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-1.5">
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="preview(m)">🖨 Print</button>
                                <button type="button" class="btn-primary !py-1 !text-xs" :disabled="downloadingId === m.id" @click="downloadCard(m)">{{ downloadingId === m.id ? 'Opening...' : '⬇ Download' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Print View -->
        <div v-if="active" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 print:static print:bg-transparent print:p-0">
            <div class="w-full max-w-xs rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900 print:shadow-none">
                <div class="mb-3 text-center">
                    <p class="text-sm font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">Library Card</p>
                    <p class="text-xs text-slate-400">{{ active.library_card_no }}</p>
                </div>
                <div class="space-y-1 text-center text-sm">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ active.member_name }}</p>
                    <p class="text-xs text-slate-400">{{ active.member_code }}</p>
                    <p class="text-xs capitalize text-slate-400">{{ active.member_type }}</p>
                </div>
                <p class="mt-4 text-center text-xs text-slate-400">Max books: {{ active.max_books }} · Joined {{ formatDate(active.joined_date) }}</p>
                <div class="mt-6 flex gap-2 print:hidden">
                    <button type="button" class="btn-outline flex-1" @click="active = null">Close</button>
                    <button type="button" class="btn-primary flex-1" @click="printCard">🖨 Print</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Blocked'] },
];

const loading = ref(true);
const members = ref([]);
const filterValues = reactive({});
const active = ref(null);
const downloadingId = ref(null);

const filteredMembers = computed(() =>
    members.value.filter((m) => {
        if (filterValues.search && !`${m.member_name} ${m.library_card_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && m.status !== filterValues.status) return false;
        return true;
    }),
);

client.get('/library/members').then(({ data }) => {
    members.value = data;
    loading.value = false;
});

function preview(member) {
    active.value = member;
}

async function downloadCard(member) {
    downloadingId.value = member.id;
    try {
        await downloadPdf(`/library/members/${member.id}/pdf`, `library-card-${member.library_card_no || member.id}.pdf`);
    } catch {
        pushToast('Could not open library card PDF.', 'error');
    } finally {
        downloadingId.value = null;
    }
}

function printCard() {
    window.print();
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
