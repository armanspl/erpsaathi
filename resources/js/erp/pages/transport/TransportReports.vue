<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Transport Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Transport Management', 'Transport Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Vehicles" :value="`${report.active_vehicles}/${report.total_vehicles}`" color="indigo" icon="🚌" />
                <StatCard label="Routes" :value="report.total_routes" color="sky" icon="🗺️" />
                <StatCard label="Students Transported" :value="report.students_using_transport" color="emerald" icon="🧑‍🎓" />
                <StatCard label="Monthly Fare Revenue" :value="`₹${report.total_monthly_fare.toLocaleString('en-IN')}`" color="amber" icon="💰" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Fuel Cost This Month" :value="`₹${report.fuel_cost_this_month.toLocaleString('en-IN')}`" color="rose" icon="⛽" />
                <StatCard label="Maintenance Cost This Month" :value="`₹${report.maintenance_cost_this_month.toLocaleString('en-IN')}`" color="violet" icon="🔧" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Route Utilization</div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Route</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stops</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Students</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly Fare</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!report.routes.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">No routes configured yet.</td>
                        </tr>
                        <tr v-for="r in report.routes" :key="r.route_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.vehicle || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.stops_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.students_count }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">₹{{ r.monthly_fare_total.toLocaleString('en-IN') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">⏳ Documents Expiring in 30 Days</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.upcoming_document_expiries.length" class="px-4 py-6 text-center text-sm text-slate-400">Nothing expiring soon.</li>
                        <li v-for="(d, idx) in report.upcoming_document_expiries" :key="idx" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ d.vehicle }} — {{ d.document_type }}</span>
                            <span class="text-slate-400">{{ formatDate(d.expiry_date) }}</span>
                        </li>
                    </ul>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">🔧 Maintenance Due in 30 Days</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.upcoming_maintenance.length" class="px-4 py-6 text-center text-sm text-slate-400">Nothing due soon.</li>
                        <li v-for="(m, idx) in report.upcoming_maintenance" :key="idx" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ m.vehicle }} — {{ m.type }}</span>
                            <span class="text-slate-400">{{ formatDate(m.next_due_date) }}</span>
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

client.get('/transport/reports').then(({ data }) => (report.value = data));

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
