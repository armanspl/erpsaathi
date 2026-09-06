<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Student Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Reports', 'Student Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Total Students" :value="report.total_students" color="indigo" icon="🧑‍🎓" />
                <StatCard label="Active" :value="report.active_students" color="emerald" icon="✅" />
                <StatCard label="Inactive" :value="report.inactive_students" color="rose" icon="⛔" />
                <StatCard label="New This Month" :value="report.new_admissions_this_month" color="sky" icon="🆕" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Class Wise Strength</div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!report.class_wise_strength.length">
                                <td class="px-4 py-6 text-center text-slate-400">No active students yet.</td>
                            </tr>
                            <tr v-for="c in report.class_wise_strength" :key="c.class" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">Class {{ c.class }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ c.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Gender Breakdown</div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!Object.keys(report.gender_breakdown).length">
                                <td class="px-4 py-6 text-center text-slate-400">No active students yet.</td>
                            </tr>
                            <tr v-for="(count, gender) in report.gender_breakdown" :key="gender" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ gender }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

client.get('/reports/students').then(({ data }) => (report.value = data));
</script>
