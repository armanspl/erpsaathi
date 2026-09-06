<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Income</h1>
                <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Income']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Income</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="income entries" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Income" :value="`₹${totalAmount.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
            <StatCard label="This Month" :value="`₹${thisMonthAmount.toLocaleString('en-IN')}`" color="sky" icon="📅" />
            <StatCard label="Entries" :value="incomes.length" color="indigo" icon="🧾" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Voucher</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Source</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredIncomes.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No income entries match your filters.</td>
                    </tr>
                    <tr v-for="i in filteredIncomes" :key="i.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ i.voucher_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ i.source }}</td>
                        <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400">₹{{ Number(i.amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ i.payment_mode }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(i.date) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(i)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(i)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Income' : 'Add Income'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Source</label>
                <input v-model="form.source" type="text" class="form-input" required placeholder="e.g. Donation, Rent, Other Fee" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Amount</label>
                    <input v-model.number="form.amount" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.date" type="date" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Payment Mode</label>
                    <select v-model="form.payment_mode" class="form-input">
                        <option>Cash</option>
                        <option>Bank</option>
                        <option>UPI</option>
                        <option>Cheque</option>
                    </select>
                </div>
                <div v-if="form.payment_mode === 'Bank' || form.payment_mode === 'Cheque'">
                    <label class="form-label">Bank Account</label>
                    <select v-model="form.bank_account_id" class="form-input">
                        <option :value="null">Select account</option>
                        <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.account_name }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input v-model="form.remarks" type="text" class="form-input" />
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

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Mode', type: 'select', options: ['Cash', 'Bank', 'UPI', 'Cheque'] },
];

const loading = ref(true);
const saving = ref(false);
const incomes = ref([]);
const bankAccounts = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ source: '', amount: 0, date: new Date().toISOString().slice(0, 10), payment_mode: 'Cash', bank_account_id: null, remarks: '' });

const totalAmount = computed(() => incomes.value.reduce((sum, i) => sum + Number(i.amount), 0));
const thisMonthAmount = computed(() => {
    const ym = new Date().toISOString().slice(0, 7);
    return incomes.value.filter((i) => i.date.startsWith(ym)).reduce((sum, i) => sum + Number(i.amount), 0);
});

const filteredIncomes = computed(() =>
    incomes.value.filter((i) => {
        if (filterValues.search && !`${i.source} ${i.voucher_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && i.payment_mode !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [incomesRes, accountsRes] = await Promise.all([client.get('/finance-payroll/incomes'), client.get('/finance-payroll/bank-accounts')]);
    incomes.value = incomesRes.data;
    bankAccounts.value = accountsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { source: '', amount: 0, date: new Date().toISOString().slice(0, 10), payment_mode: 'Cash', bank_account_id: null, remarks: '' });
    drawerOpen.value = true;
}

function openEdit(income) {
    editing.value = income;
    Object.assign(form, {
        source: income.source,
        amount: Number(income.amount),
        date: income.date.slice(0, 10),
        payment_mode: income.payment_mode,
        bank_account_id: income.bank_account_id,
        remarks: income.remarks || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/finance-payroll/incomes/${editing.value.id}`, form);
            pushToast('Income updated.', 'success');
        } else {
            await client.post('/finance-payroll/incomes', form);
            pushToast('Income added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(income) {
    incomes.value = incomes.value.filter((i) => i.id !== income.id);
    await client.delete(`/finance-payroll/incomes/${income.id}`);
    pushToast(`Income "${income.source}" deleted.`, 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
