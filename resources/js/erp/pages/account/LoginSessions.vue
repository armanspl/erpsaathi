<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Login Sessions</h1>
            <Breadcrumb :items="['Dashboard', 'Account', 'Login Sessions']" class="mt-1" />
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">IP Address</th>
                        <th class="px-4 py-3">Device / Browser</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="row.status === 'Success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'"
                            >
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ row.ip_address }}</td>
                        <td class="max-w-xs truncate px-4 py-3" :title="row.user_agent">{{ row.user_agent }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(row.created_at) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-400">No login sessions recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';

const rows = ref([]);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

client.get('/account/sessions').then(({ data }) => {
    rows.value = data;
});
</script>
