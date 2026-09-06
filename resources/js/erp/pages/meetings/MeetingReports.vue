<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Meeting Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Meetings', 'Meeting Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Total Meetings" :value="report.total_meetings" color="indigo" icon="🎥" />
                <StatCard label="Scheduled" :value="report.scheduled" color="emerald" icon="📅" />
                <StatCard label="Completed" :value="report.completed" color="sky" icon="✅" />
                <StatCard label="Cancelled" :value="report.cancelled" color="rose" icon="⛔" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Upcoming" :value="report.upcoming" color="amber" icon="⏳" />
                <StatCard label="With Recording" :value="report.with_recording" color="violet" icon="🎬" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Breakdown by Type</div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="(count, type) in report.by_type" :key="type" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ type }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ count }}</td>
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

const report = ref(null);

client.get('/meetings/reports').then(({ data }) => (report.value = data));
</script>
