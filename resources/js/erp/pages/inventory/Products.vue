<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Products</h1>
                <Breadcrumb :items="['Dashboard', 'Inventory', 'Products']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Product</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="products" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Products" :value="products.length" color="indigo" icon="📦" />
            <StatCard label="Total Stock Units" :value="totalStock" color="sky" icon="📊" />
            <StatCard label="Low Stock" :value="lowStockCount" color="rose" icon="⚠️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Cost Price</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Stock</th>
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
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.category || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ Number(p.cost_price).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium" :class="p.current_stock <= p.reorder_level ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">{{ p.current_stock }} {{ p.unit }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(p)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(p)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Product' : 'Add Product'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">SKU</label>
                    <input v-model="form.sku" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <input v-model="form.category" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Unit</label>
                    <input v-model="form.unit" type="text" class="form-input" placeholder="pcs, box, kg..." />
                </div>
                <div>
                    <label class="form-label">Cost Price</label>
                    <input v-model.number="form.cost_price" type="number" step="0.01" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Reorder Level</label>
                    <input v-model.number="form.reorder_level" type="number" min="0" class="form-input" />
                </div>
                <div v-if="!editing">
                    <label class="form-label">Opening Stock</label>
                    <input v-model.number="form.opening_stock" type="number" min="0" class="form-input" />
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const products = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', sku: '', category: '', unit: 'pcs', cost_price: 0, reorder_level: 0, opening_stock: 0 });

const totalStock = computed(() => products.value.reduce((sum, p) => sum + p.current_stock, 0));
const lowStockCount = computed(() => products.value.filter((p) => p.current_stock <= p.reorder_level).length);

const filteredProducts = computed(() =>
    products.value.filter((p) => {
        if (filterValues.search && !`${p.name} ${p.sku} ${p.category}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/inventory/products');
    products.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', sku: '', category: '', unit: 'pcs', cost_price: 0, reorder_level: 0, opening_stock: 0 });
    drawerOpen.value = true;
}

function openEdit(product) {
    editing.value = product;
    Object.assign(form, {
        name: product.name,
        sku: product.sku,
        category: product.category || '',
        unit: product.unit,
        cost_price: Number(product.cost_price),
        reorder_level: product.reorder_level,
        opening_stock: 0,
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/inventory/products/${editing.value.id}`, form);
            pushToast('Product updated.', 'success');
        } else {
            await client.post('/inventory/products', form);
            pushToast('Product added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(product) {
    products.value = products.value.filter((p) => p.id !== product.id);
    await client.delete(`/inventory/products/${product.id}`);
    pushToast(`Product "${product.name}" deleted.`, 'success');
}
</script>
