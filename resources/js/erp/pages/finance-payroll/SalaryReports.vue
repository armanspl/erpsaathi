<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Salary Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Salary Reports']" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Period</label>
            <input v-model="period" type="month" class="form-input max-w-xs" @change="load" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Employees" :value="report.total_employees" color="indigo" icon="👥" />
                <StatCard label="Total Net Payroll" :value="`₹${report.total_net.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
                <StatCard label="Paid" :value="report.paid_count" color="sky" icon="✅" />
                <StatCard label="Pending" :value="report.pending_count" color="amber" icon="⏳" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Total Basic" :value="`₹${report.total_basic.toLocaleString('en-IN')}`" color="indigo" icon="🧾" />
                <StatCard label="Total Allowances" :value="`₹${report.total_allowances.toLocaleString('en-IN')}`" color="emerald" icon="➕" />
                <StatCard label="Total Deductions" :value="`₹${report.total_deductions.toLocaleString('en-IN')}`" color="rose" icon="➖" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Net Salary</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!report.rows.length">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">No salary slips generated for this period.</td>
                        </tr>
                        <tr v-for="r in report.rows" :key="`${r.employee_type}-${r.employee_id}`" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{{ r.employee_type }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ Number(r.net_salary).toLocaleString('en-IN') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.status === 'Paid' ? 'Active' : 'Inactive')">{{ r.status }}</span>
                            </td>
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
import { statusBadgeClass } from '../../utils/colors';

const period = ref(new Date().toISOString().slice(0, 7));
const report = ref(null);

async function load() {
    const { data } = await client.get('/finance-payroll/salary-reports', { params: { period: period.value } });
    report.value = data;
}
load();
</script>
