<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Attendance Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Attendance', 'Attendance Reports']" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <select v-model="type" class="form-input" @change="load">
                    <option value="student">Students</option>
                    <option value="teacher">Teachers</option>
                    <option value="staff">Staff</option>
                    <option value="driver">Drivers</option>
                </select>
                <input v-model="from" type="date" class="form-input" @change="load" />
                <input v-model="to" type="date" class="form-input" @change="load" />
                <input v-model="search" type="text" placeholder="Search name..." class="form-input" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Records" :value="rows.length" color="indigo" icon="👥" />
            <StatCard label="Avg Attendance" :value="`${averagePercentage}%`" color="emerald" icon="📊" />
            <StatCard label="Below 75%" :value="rows.filter((r) => r.percentage < 75 && r.total_marked > 0).length" color="rose" icon="⚠️" />
            <StatCard label="Perfect Attendance" :value="rows.filter((r) => r.percentage === 100 && r.total_marked > 0).length" color="sky" icon="🌟" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('code')">Code {{ sortArrow('code') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Name {{ sortArrow('name') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('present')">Present {{ sortArrow('present') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('absent')">Absent {{ sortArrow('absent') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('leave')">Leave {{ sortArrow('leave') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('late')">Late {{ sortArrow('late') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('percentage')">Attendance % {{ sortArrow('percentage') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredRows.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No records match your filters.</td>
                    </tr>
                    <tr v-for="r in filteredRows" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.code || '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                        <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400">{{ r.present }}</td>
                        <td class="px-4 py-3 text-rose-600 dark:text-rose-400">{{ r.absent }}</td>
                        <td class="px-4 py-3 text-amber-600 dark:text-amber-400">{{ r.leave }}</td>
                        <td class="px-4 py-3 text-sky-600 dark:text-sky-400">{{ r.late }}</td>
                        <td class="px-4 py-3">
                            <span class="font-semibold" :class="r.percentage >= 75 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ r.percentage }}%</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const type = ref('student');
const from = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const to = ref(new Date().toISOString().slice(0, 10));
const search = ref('');
const loading = ref(true);
const rows = ref([]);
const sortKey = ref('name');
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

async function load() {
    loading.value = true;
    const { data } = await client.get('/attendance/reports', { params: { type: type.value, from: from.value, to: to.value } });
    rows.value = data.rows;
    loading.value = false;
}
load();

const filteredRows = computed(() => {
    const list = rows.value.filter((r) => !search.value || r.name.toLowerCase().includes(search.value.toLowerCase()));
    return [...list].sort((a, b) => {
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

const averagePercentage = computed(() => {
    const marked = rows.value.filter((r) => r.total_marked > 0);
    if (!marked.length) return 0;
    return Math.round(marked.reduce((sum, r) => sum + r.percentage, 0) / marked.length);
});
</script>
