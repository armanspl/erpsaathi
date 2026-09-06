<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Stock</h1>
            <Breadcrumb :items="['Dashboard', 'Inventory', 'Stock']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="products" @reset="filterValues = {}" />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Product</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Current Stock</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Reorder Level</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredProducts.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No products match your filters.</td>
                    </tr>
                    <tr v-for="p in filteredProducts" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.sku }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.current_stock }} {{ p.unit }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.reorder_level }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(p.current_stock <= p.reorder_level ? 'Inactive' : 'Active')">
                                {{ p.current_stock <= p.reorder_level ? 'Low Stock' : 'In Stock' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="openAdjust(p)">⚙ Adjust</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="`Adjust Stock — ${active?.name}`" @close="drawerOpen = false">
            <div>
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option>Addition</option>
                    <option>Reduction</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Quantity</label>
                    <input v-model.number="form.quantity" type="number" min="1" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.date" type="date" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Reason</label>
                <input v-model="form.reason" type="text" class="form-input" placeholder="Damaged, lost, correction..." />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Apply Adjustment' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const products = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const active = ref(null);

const form = reactive({ type: 'Addition', quantity: 1, reason: '', date: new Date().toISOString().slice(0, 10) });

const filteredProducts = computed(() =>
    products.value.filter((p) => {
        if (filterValues.search && !`${p.name} ${p.sku}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/inventory/stock');
    products.value = data;
    loading.value = false;
}
load();

function openAdjust(product) {
    active.value = product;
    Object.assign(form, { type: 'Addition', quantity: 1, reason: '', date: new Date().toISOString().slice(0, 10) });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/inventory/stock/adjust', { ...form, product_id: active.value.id });
        pushToast('Stock adjusted.', 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
