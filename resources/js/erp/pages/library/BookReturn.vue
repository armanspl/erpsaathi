<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Book Return</h1>
            <Breadcrumb :items="['Dashboard', 'Library', 'Book Return']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="issued books" @reset="filterValues = {}" />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Book</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Due Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredIssues.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No books currently issued.</td>
                    </tr>
                    <tr v-for="i in filteredIssues" :key="i.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ i.book.title }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ i.member.member.name }} ({{ i.member.library_card_no }})</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(i.due_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(i.is_overdue ? 'Overdue' : 'Pending')">{{ i.is_overdue ? 'Overdue' : 'Issued' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="openReturn(i)">↩ Return</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Return Book" @close="drawerOpen = false">
            <div v-if="active">
                <p class="text-sm text-slate-500 dark:text-slate-400"><strong class="text-slate-800 dark:text-slate-100">{{ active.book.title }}</strong> — {{ active.member.member.name }}</p>
                <p class="mt-1 text-xs text-slate-400">Due {{ formatDate(active.due_date) }}</p>
            </div>
            <div>
                <label class="form-label">Return Date</label>
                <input v-model="returnDate" type="date" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="confirmReturn">{{ saving ? 'Processing...' : 'Confirm Return' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const issues = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const active = ref(null);
const returnDate = ref(new Date().toISOString().slice(0, 10));

const filteredIssues = computed(() =>
    issues.value.filter((i) => {
        if (filterValues.search && !`${i.book.title} ${i.member.member.name}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/library/issues', { params: { status: 'Issued' } });
    issues.value = data;
    loading.value = false;
}
load();

function openReturn(issue) {
    active.value = issue;
    returnDate.value = new Date().toISOString().slice(0, 10);
    drawerOpen.value = true;
}

async function confirmReturn() {
    saving.value = true;
    try {
        const { data } = await client.patch(`/library/issues/${active.value.id}/return`, { return_date: returnDate.value });
        const message = Number(data.fine_amount) > 0 ? `Book returned. Fine of ₹${Number(data.fine_amount).toLocaleString('en-IN')} recorded.` : 'Book returned on time — no fine.';
        pushToast(message, 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
