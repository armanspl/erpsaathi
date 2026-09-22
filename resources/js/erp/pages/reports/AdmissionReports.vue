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
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('stage')">Stage {{ sortArrow('stage') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('count')">Enquiries {{ sortArrow('count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!stageRows.length">
                                <td class="px-4 py-6 text-center text-slate-400">No enquiries yet.</td>
                            </tr>
                            <tr v-for="row in stageRows" :key="row.stage" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium capitalize text-slate-800 dark:text-slate-100">{{ row.stage.replace('_', ' ') }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ row.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">By Source</div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort2('source')">Source {{ sortArrow2('source') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort2('count')">Enquiries {{ sortArrow2('count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!sourceRows.length">
                                <td class="px-4 py-6 text-center text-slate-400">No enquiries yet.</td>
                            </tr>
                            <tr v-for="row in sourceRows" :key="row.source" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ row.source }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ row.count }}</td>
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
import { computed, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const report = ref(null);

client.get('/reports/admissions').then(({ data }) => (report.value = data));

const sortKey = ref('stage');
const sortDir = ref('asc');
function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}
const stageRows = computed(() => {
    const rows = Object.entries(report.value?.by_stage || {}).map(([stage, count]) => ({ stage, count }));
    return rows.sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const sortKey2 = ref('source');
const sortDir2 = ref('asc');
function toggleSort2(key) {
    if (sortKey2.value === key) {
        sortDir2.value = sortDir2.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey2.value = key;
        sortDir2.value = 'asc';
    }
}
function sortArrow2(key) {
    if (sortKey2.value !== key) return '';
    return sortDir2.value === 'asc' ? '↑' : '↓';
}
const sourceRows = computed(() => {
    const rows = Object.entries(report.value?.by_source || {}).map(([source, count]) => ({ source, count }));
    return rows.sort((a, b) => {
        let av = a[sortKey2.value];
        let bv = b[sortKey2.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir2.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir2.value === 'asc' ? 1 : -1;
        return 0;
    });
});
</script>
