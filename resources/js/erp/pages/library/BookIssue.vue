<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Book Issue</h1>
                <Breadcrumb :items="['Dashboard', 'Library', 'Book Issue']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Issue Book</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="issues" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Currently Issued" :value="issues.filter((i) => i.status === 'Issued').length" color="indigo" icon="📖" />
            <StatCard label="Overdue" :value="issues.filter((i) => i.is_overdue).length" color="rose" icon="⏳" />
            <StatCard label="Returned" :value="issues.filter((i) => i.status === 'Returned').length" color="emerald" icon="✅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Book</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Issued</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Due</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredIssues.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No issues match your filters.</td>
                    </tr>
                    <tr v-for="i in filteredIssues" :key="i.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ i.book.title }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ i.member.member.name }} ({{ i.member.library_card_no }})</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(i.issue_date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(i.due_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(i.is_overdue ? 'Overdue' : i.status === 'Returned' ? 'Active' : 'Pending')">
                                {{ i.is_overdue ? 'Overdue' : i.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Issue Book" @close="drawerOpen = false">
            <div>
                <label class="form-label">Book</label>
                <select v-model.number="form.book_id" class="form-input">
                    <option :value="null">Select book</option>
                    <option v-for="b in availableBooks" :key="b.id" :value="b.id">{{ b.title }} ({{ b.available_copies }} available)</option>
                </select>
            </div>
            <div>
                <label class="form-label">Member</label>
                <select v-model.number="form.library_member_id" class="form-input">
                    <option :value="null">Select member</option>
                    <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.member_name }} ({{ m.library_card_no }})</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Issue Date</label>
                    <input v-model="form.issue_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Due Date</label>
                    <input v-model="form.due_date" type="date" class="form-input" />
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Issuing...' : 'Issue Book' }}</button>
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
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Issued', 'Returned'] },
];

const loading = ref(true);
const saving = ref(false);
const issues = ref([]);
const books = ref([]);
const members = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);

const form = reactive({ book_id: null, library_member_id: null, issue_date: new Date().toISOString().slice(0, 10), due_date: '' });

const availableBooks = computed(() => books.value.filter((b) => b.available_copies > 0));
const activeMembers = computed(() => members.value.filter((m) => m.status === 'Active'));

const filteredIssues = computed(() =>
    issues.value.filter((i) => {
        if (filterValues.search && !`${i.book.title} ${i.member.member.name}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && i.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [issuesRes, booksRes, membersRes] = await Promise.all([client.get('/library/issues'), client.get('/library/books'), client.get('/library/members')]);
    issues.value = issuesRes.data;
    books.value = booksRes.data;
    members.value = membersRes.data;
    loading.value = false;
}
load();

function openAdd() {
    const due = new Date();
    due.setDate(due.getDate() + 14);
    Object.assign(form, { book_id: null, library_member_id: null, issue_date: new Date().toISOString().slice(0, 10), due_date: due.toISOString().slice(0, 10) });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/library/issues', form);
        pushToast('Book issued.', 'success');
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
