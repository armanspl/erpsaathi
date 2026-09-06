<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Audit Logs</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Audit Logs']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="audit logs" @reset="filterValues = {}" />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Model</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Record ID</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Changes</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">By</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredLogs.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No audit entries match your filters.</td>
                    </tr>
                    <tr v-for="l in filteredLogs" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(l.action === 'created' ? 'Active' : l.action === 'deleted' ? 'Inactive' : 'Pending')">{{ l.action }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ l.auditable_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">#{{ l.auditable_id }}</td>
                        <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ l.changes ? JSON.stringify(l.changes) : '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ l.performed_by?.name || 'System' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDateTime(l.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'action', label: 'Action', type: 'select', options: ['created', 'updated', 'deleted'] },
];

const loading = ref(true);
const logs = ref([]);
const filterValues = reactive({});

const filteredLogs = computed(() =>
    logs.value.filter((l) => {
        if (filterValues.search) {
            const q = filterValues.search.toLowerCase();
            const haystack = `${l.action} ${l.auditable_type} ${l.auditable_id} ${l.performed_by?.name || 'System'}`.toLowerCase();
            if (!haystack.includes(q)) return false;
        }
        if (filterValues.action && l.action !== filterValues.action) return false;
        return true;
    }),
);

client.get('/system/audit-logs').then(({ data }) => {
    logs.value = data;
    loading.value = false;
});

function formatDateTime(value) {
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
