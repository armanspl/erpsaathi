<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Library Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Library', 'Library Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Titles" :value="report.total_titles" color="indigo" icon="📚" />
                <StatCard label="Members" :value="report.total_members" color="sky" icon="🪪" />
                <StatCard label="Currently Issued" :value="report.currently_issued" color="amber" icon="📖" />
                <StatCard label="Overdue" :value="report.overdue_count" color="rose" icon="⏳" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Fine Collected" :value="`₹${report.fine_collected_total.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
                <StatCard label="Fine Pending" :value="`₹${report.fine_pending_total.toLocaleString('en-IN')}`" color="rose" icon="⚠️" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">⏳ Overdue Books</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.overdue_list.length" class="px-4 py-6 text-center text-sm text-slate-400">Nothing overdue right now.</li>
                        <li v-for="o in report.overdue_list" :key="o.issue_id" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ o.book }}</span>
                            <span class="text-rose-600 dark:text-rose-400">{{ o.days_late }} days late</span>
                        </li>
                    </ul>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">🏆 Most Borrowed</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.most_borrowed.length" class="px-4 py-6 text-center text-sm text-slate-400">No issues recorded yet.</li>
                        <li v-for="(m, idx) in report.most_borrowed" :key="idx" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ m.title }}</span>
                            <span class="text-slate-400">{{ m.times_issued }}× issued</span>
                        </li>
                    </ul>
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

client.get('/library/reports').then(({ data }) => (report.value = data));
</script>
