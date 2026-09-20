<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Attendance</h1>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <template v-else-if="data">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Overall Attendance</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.overall.percentage }}%</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Working Days</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.overall.working_days }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Days Present</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.overall.days_present }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Working Days</th>
                            <th class="px-4 py-3">Days Present</th>
                            <th class="px-4 py-3">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="m in data.months" :key="m.year + '-' + m.month">
                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">{{ m.month_label }} {{ m.year }}</td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ m.working_days }}</td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ m.days_present }}</td>
                            <td class="px-4 py-2.5 font-medium" :class="m.percentage >= 75 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ m.percentage }}%</td>
                        </tr>
                        <tr v-if="!data.months.length">
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">No attendance recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import client from '../api/client';

const loading = ref(true);
const data = ref(null);

onMounted(async () => {
    try {
        const { data: res } = await client.get('/attendance');
        data.value = res;
    } finally {
        loading.value = false;
    }
});
</script>
