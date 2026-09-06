<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Low Stock Alerts</h1>
            <Breadcrumb :items="['Dashboard', 'Inventory', 'Low Stock Alerts']" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Products Below Reorder Level" :value="products.length" color="rose" icon="⚠️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Product</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Current Stock</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Reorder Level</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Shortfall</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!products.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">All products are above their reorder level. 🎉</td>
                    </tr>
                    <tr v-for="p in products" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.sku }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                        <td class="px-4 py-3 text-rose-600 dark:text-rose-400">{{ p.current_stock }} {{ p.unit }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.reorder_level }}</td>
                        <td class="px-4 py-3 font-semibold text-rose-600 dark:text-rose-400">{{ p.reorder_level - p.current_stock }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const loading = ref(true);
const products = ref([]);

client.get('/inventory/low-stock-alerts').then(({ data }) => {
    products.value = data;
    loading.value = false;
});
</script>
