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
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('class')">Class {{ sortArrow('class') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('count')">Students {{ sortArrow('count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!report.class_wise_strength.length">
                                <td class="px-4 py-6 text-center text-slate-400">No active students yet.</td>
                            </tr>
                            <tr v-for="c in sortedClassWiseStrength" :key="c.class" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">Class {{ c.class }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ c.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Gender Breakdown</div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort2('gender')">Gender {{ sortArrow2('gender') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort2('count')">Students {{ sortArrow2('count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!genderRows.length">
                                <td class="px-4 py-6 text-center text-slate-400">No active students yet.</td>
                            </tr>
                            <tr v-for="row in genderRows" :key="row.gender" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ row.gender }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ row.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

client.get('/reports/students').then(({ data }) => (report.value = data));

const sortKey = ref('class');
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
const sortedClassWiseStrength = computed(() => {
    const rows = report.value?.class_wise_strength || [];
    return [...rows].sort((a, b) => {
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

const sortKey2 = ref('gender');
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
const genderRows = computed(() => {
    const rows = Object.entries(report.value?.gender_breakdown || {}).map(([gender, count]) => ({ gender, count }));
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
