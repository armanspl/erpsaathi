<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Activity Center</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Activity Center']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="activity" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Activity" :value="logs.length" color="indigo" icon="🗂️" />
            <StatCard label="Created" :value="logs.filter((l) => l.action === 'created').length" color="emerald" icon="➕" />
            <StatCard label="Updated / Deleted" :value="logs.filter((l) => l.action !== 'created').length" color="amber" icon="✎" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-if="loading" class="px-4 py-10 text-center text-sm text-slate-400">Loading...</li>
                <li v-else-if="!filteredLogs.length" class="px-4 py-10 text-center text-sm text-slate-400">No activity recorded yet — actions taken across the ERP will appear here.</li>
                <li v-for="l in filteredLogs" :key="l.id" class="flex items-start gap-3 px-4 py-3 text-sm">
                    <span :class="actionIconClass(l.action)">{{ actionIcon(l.action) }}</span>
                    <div class="flex-1">
                        <p class="text-slate-700 dark:text-slate-200">
                            <strong class="font-semibold text-slate-800 dark:text-slate-100">{{ l.performed_by?.name || 'System' }}</strong>
                            {{ actionVerb(l.action) }} <strong class="font-medium">{{ l.auditable_type }}</strong> #{{ l.auditable_id }}
                        </p>
                        <p class="text-xs text-slate-400">{{ formatDateTime(l.created_at) }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

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
        return true;
    }),
);

client.get('/system/audit-logs').then(({ data }) => {
    logs.value = data;
    loading.value = false;
});

function actionVerb(action) {
    return { created: 'created a', updated: 'updated a', deleted: 'deleted a' }[action] || action;
}
function actionIcon(action) {
    return { created: '➕', updated: '✎', deleted: '🗑' }[action] || '•';
}
function actionIconClass(action) {
    return { created: 'text-emerald-500', updated: 'text-amber-500', deleted: 'text-rose-500' }[action] || 'text-slate-400';
}
function formatDateTime(value) {
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
