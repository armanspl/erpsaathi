<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Login History</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Login History']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="login attempts" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Attempts" :value="filteredHistory.length" color="indigo" icon="🔑" />
            <StatCard label="Successful" :value="history.filter((h) => h.status === 'Success').length" color="emerald" icon="✅" />
            <StatCard label="Failed" :value="history.filter((h) => h.status === 'Failed').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">User</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">IP Address</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredHistory.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No login attempts match your filters.</td>
                    </tr>
                    <tr v-for="h in filteredHistory" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ h.erp_user?.name || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ h.email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(h.status === 'Success' ? 'Active' : 'Inactive')">{{ h.status }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ h.ip_address || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDateTime(h.created_at) }}</td>
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
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Success', 'Failed'] },
];

const loading = ref(true);
const history = ref([]);
const filterValues = reactive({});

const filteredHistory = computed(() =>
    history.value.filter((h) => {
        if (filterValues.search && !`${h.email}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && h.status !== filterValues.status) return false;
        return true;
    }),
);

client.get('/system/login-history').then(({ data }) => {
    history.value = data;
    loading.value = false;
});

function formatDateTime(value) {
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
