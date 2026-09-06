<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Scheduled Jobs</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Scheduled Jobs']" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Registered Schedules" :value="count" color="indigo" icon="🗓️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Command</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Expression</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Next Run</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!events.length">
                        <td colspan="3" class="px-4 py-10 text-center text-slate-400">No scheduled tasks are registered in <code>routes/console.php</code> yet.</td>
                    </tr>
                    <tr v-for="(e, i) in events" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-200">{{ e.command }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ e.expression }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.next_due || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const count = ref(0);
const events = ref([]);

client.get('/system/scheduled-jobs').then(({ data }) => {
    count.value = data.count;
    events.value = data.events;
});
</script>
