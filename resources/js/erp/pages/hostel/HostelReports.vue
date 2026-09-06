<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Hostel Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Hostel', 'Hostel Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Rooms" :value="report.total_rooms" color="indigo" icon="🏨" />
                <StatCard label="Beds" :value="`${report.occupied_beds}/${report.total_beds}`" color="sky" icon="🛏️" />
                <StatCard label="Occupancy Rate" :value="`${report.occupancy_rate}%`" color="emerald" icon="📊" />
                <StatCard label="Active Students" :value="report.active_students" color="amber" icon="🧑‍🎓" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Fee Collected" :value="`₹${report.fee_collected_total.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
                <StatCard label="Fee Pending" :value="`₹${report.fee_pending_total.toLocaleString('en-IN')}`" color="rose" icon="⚠️" />
                <StatCard label="Visitors This Month" :value="report.visitors_this_month" color="sky" icon="🧑‍🤝‍🧑" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Room Occupancy</div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Room</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capacity</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Beds</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Occupied</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!report.room_occupancy.length">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">No rooms configured yet.</td>
                        </tr>
                        <tr v-for="r in report.room_occupancy" :key="r.room_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.room_no }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.capacity }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.beds_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.occupied_beds_count }}</td>
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

client.get('/hostel/reports').then(({ data }) => (report.value = data));
</script>
