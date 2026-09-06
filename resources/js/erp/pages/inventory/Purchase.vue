<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Purchase</h1>
                <Breadcrumb :items="['Dashboard', 'Inventory', 'Purchase']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ New Purchase</button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Purchases" :value="purchases.length" color="indigo" icon="🧾" />
            <StatCard label="Total Spend" :value="`₹${totalSpend.toLocaleString('en-IN')}`" color="rose" icon="💸" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Voucher</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Supplier</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Items</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!purchases.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No purchases recorded yet.</td>
                    </tr>
                    <tr v-for="p in purchases" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.voucher_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.supplier.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.items.length }} item(s)</td>
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">₹{{ Number(p.total_amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(p.purchase_date) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="New Purchase" @close="drawerOpen = false">
            <div>
                <label class="form-label">Supplier</label>
                <select v-model.number="form.supplier_id" class="form-input">
                    <option :value="null">Select supplier</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Purchase Date</label>
                <input v-model="form.purchase_date" type="date" class="form-input" />
            </div>
            <div>
                <label class="form-label">Items</label>
                <div class="space-y-2">
                    <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-12 items-center gap-2">
                        <select v-model.number="item.product_id" class="form-input col-span-5">
                            <option :value="null">Product</option>
                            <option v-for="pr in products" :key="pr.id" :value="pr.id">{{ pr.name }}</option>
                        </select>
                        <input v-model.number="item.quantity" type="number" min="1" class="form-input col-span-3" placeholder="Qty" />
                        <input v-model.number="item.unit_cost" type="number" step="0.01" min="0" class="form-input col-span-3" placeholder="Cost" />
                        <button type="button" class="col-span-1 text-slate-400 hover:text-rose-600" @click="form.items.splice(idx, 1)">🗑</button>
                    </div>
                </div>
                <button type="button" class="btn-outline mt-2 !py-1 !text-xs" @click="form.items.push({ product_id: null, quantity: 1, unit_cost: 0 })">+ Add Item</button>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 text-right text-sm font-semibold text-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
                Total: ₹{{ itemsTotal.toLocaleString('en-IN') }}
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input v-model="form.remarks" type="text" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Record Purchase' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const purchases = ref([]);
const suppliers = ref([]);
const products = ref([]);
const drawerOpen = ref(false);

const form = reactive({ supplier_id: null, purchase_date: new Date().toISOString().slice(0, 10), items: [{ product_id: null, quantity: 1, unit_cost: 0 }], remarks: '' });

const totalSpend = computed(() => purchases.value.reduce((sum, p) => sum + Number(p.total_amount), 0));
const itemsTotal = computed(() => form.items.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.unit_cost) || 0), 0));

async function load() {
    loading.value = true;
    const [purchasesRes, suppliersRes, productsRes] = await Promise.all([
        client.get('/inventory/purchases'),
        client.get('/inventory/suppliers'),
        client.get('/inventory/products'),
    ]);
    purchases.value = purchasesRes.data;
    suppliers.value = suppliersRes.data;
    products.value = productsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    Object.assign(form, { supplier_id: null, purchase_date: new Date().toISOString().slice(0, 10), items: [{ product_id: null, quantity: 1, unit_cost: 0 }], remarks: '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/inventory/purchases', form);
        pushToast('Purchase recorded and stock updated.', 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
