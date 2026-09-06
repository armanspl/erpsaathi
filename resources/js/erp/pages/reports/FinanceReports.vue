<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Finance Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Reports', 'Finance Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Total Income" :value="`₹${report.total_income.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
                <StatCard label="Total Expense" :value="`₹${report.total_expense.toLocaleString('en-IN')}`" color="rose" icon="💸" />
                <StatCard label="Net Balance" :value="`₹${report.net_balance.toLocaleString('en-IN')}`" color="indigo" icon="📊" />
                <StatCard label="Bank Balance" :value="`₹${report.total_bank_balance.toLocaleString('en-IN')}`" color="sky" icon="🏦" />
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
                <StatCard label="Income This Month" :value="`₹${report.income_this_month.toLocaleString('en-IN')}`" color="emerald" icon="📅" />
                <StatCard label="Expense This Month" :value="`₹${report.expense_this_month.toLocaleString('en-IN')}`" color="rose" icon="📅" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">Top Expense Categories</div>
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!Object.keys(report.top_expense_categories).length">
                            <td class="px-4 py-6 text-center text-slate-400">No expenses recorded yet.</td>
                        </tr>
                        <tr v-for="(amount, category) in report.top_expense_categories" :key="category" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ category }}</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">₹{{ Number(amount).toLocaleString('en-IN') }}</td>
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

client.get('/reports/finance').then(({ data }) => (report.value = data));
</script>
