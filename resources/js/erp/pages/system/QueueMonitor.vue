<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Queue Monitor</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Queue Monitor']" class="mt-1" />
        </div>

        <template v-if="data">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Queue Connection" :value="data.connection" color="indigo" icon="🔌" />
                <StatCard label="Pending Jobs" :value="data.pending_jobs" color="amber" icon="⏳" />
                <StatCard label="Failed Jobs" :value="data.failed_jobs" color="rose" icon="⛔" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Recent Failed Jobs</div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Queue</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Exception</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Failed At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!data.recent_failed.length">
                            <td colspan="3" class="px-4 py-10 text-center text-slate-400">No failed jobs. 🎉</td>
                        </tr>
                        <tr v-for="f in data.recent_failed" :key="f.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ f.queue }}</td>
                            <td class="max-w-md truncate px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ f.exception }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ f.failed_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const data = ref(null);

client.get('/system/queue-monitor').then(({ data: d }) => (data.value = d));
</script>
