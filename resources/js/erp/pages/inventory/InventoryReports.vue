<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Inventory Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Inventory', 'Inventory Reports']" class="mt-1" />
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Products" :value="report.total_products" color="indigo" icon="📦" />
                <StatCard label="Stock Units" :value="report.total_stock_units" color="sky" icon="📊" />
                <StatCard label="Stock Value" :value="`₹${report.total_stock_value.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
                <StatCard label="Low Stock" :value="report.low_stock_count" color="rose" icon="⚠️" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Suppliers" :value="report.total_suppliers" color="indigo" icon="🏭" />
                <StatCard label="Purchases This Month" :value="report.purchases_this_month" color="sky" icon="🧾" />
                <StatCard label="Purchase Value This Month" :value="`₹${report.purchase_value_this_month.toLocaleString('en-IN')}`" color="amber" icon="💸" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">⚠️ Low Stock</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.low_stock_list.length" class="px-4 py-6 text-center text-sm text-slate-400">Nothing below reorder level.</li>
                        <li v-for="l in report.low_stock_list" :key="l.product_id" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ l.name }}</span>
                            <span class="text-rose-600 dark:text-rose-400">{{ l.current_stock }} / {{ l.reorder_level }}</span>
                        </li>
                    </ul>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200">🏆 Most Purchased</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-if="!report.top_purchased.length" class="px-4 py-6 text-center text-sm text-slate-400">No purchases recorded yet.</li>
                        <li v-for="t in report.top_purchased" :key="t.product_id" class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-700 dark:text-slate-200">{{ t.product_name }}</span>
                            <span class="text-slate-400">{{ t.total_quantity }} units</span>
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

client.get('/inventory/reports').then(({ data }) => (report.value = data));
</script>
