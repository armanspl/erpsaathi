<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Admission Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Reports', 'Admission Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Total Enquiries" :value="report.total_enquiries" color="indigo" icon="📝" />
                <StatCard label="Admitted" :value="report.admitted" color="emerald" icon="✅" />
                <StatCard label="Rejected" :value="report.rejected" color="rose" icon="⛔" />
                <StatCard label="Conversion Rate" :value="`${report.conversion_rate}%`" color="sky" icon="📈" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">By Stage</div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!Object.keys(report.by_stage).length">
                                <td class="px-4 py-6 text-center text-slate-400">No enquiries yet.</td>
                            </tr>
                            <tr v-for="(count, stage) in report.by_stage" :key="stage" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium capitalize text-slate-800 dark:text-slate-100">{{ stage.replace('_', ' ') }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">By Source</div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!Object.keys(report.by_source).length">
                                <td class="px-4 py-6 text-center text-slate-400">No enquiries yet.</td>
                            </tr>
                            <tr v-for="(count, source) in report.by_source" :key="source" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ source }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                {{ report.new_this_month }} new enquir{{ report.new_this_month === 1 ? 'y' : 'ies' }} this month.
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

client.get('/reports/admissions').then(({ data }) => (report.value = data));
</script>
