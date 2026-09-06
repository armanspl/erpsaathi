<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Bank Transactions</h1>
                <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Bank Transactions']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!accountId" @click="openAdd">+ Add Transaction</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Bank Account</label>
            <select v-model.number="accountId" class="form-input" @change="load">
                <option :value="null">Select an account</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.account_name }} — {{ a.bank_name }}</option>
            </select>
        </div>

        <div v-if="account" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Current Balance" :value="`₹${Number(account.current_balance).toLocaleString('en-IN')}`" color="emerald" icon="💰" />
            <StatCard label="Deposits" :value="transactions.filter((t) => t.type === 'Deposit').length" color="sky" icon="⬇️" />
            <StatCard label="Withdrawals" :value="transactions.filter((t) => t.type === 'Withdrawal').length" color="rose" icon="⬆️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!accountId">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Select a bank account to view its transactions.</td>
                    </tr>
                    <tr v-else-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!transactions.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No transactions recorded for this account.</td>
                    </tr>
                    <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="t.type === 'Deposit' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30' : 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30'">{{ t.type }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">₹{{ Number(t.amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(t.date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.reference_no || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.remarks || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(t)">✏️</button>
                            <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(t)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Transaction' : 'Add Transaction'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option>Deposit</option>
                    <option>Withdrawal</option>
                </select>
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
            <div>
                <label class="form-label">Reference No.</label>
                <input v-model="form.reference_no" type="text" class="form-input" />
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
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const route = useRoute();

const loading = ref(false);
const saving = ref(false);
const accounts = ref([]);
const transactions = ref([]);
const accountId = ref(route.query.account ? Number(route.query.account) : null);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ type: 'Deposit', amount: 0, date: new Date().toISOString().slice(0, 10), reference_no: '', remarks: '' });

const account = computed(() => accounts.value.find((a) => a.id === accountId.value));

client.get('/finance-payroll/bank-accounts').then(({ data }) => {
    accounts.value = data;
    if (accountId.value) load();
});

async function load() {
    if (!accountId.value) {
        transactions.value = [];
        return;
    }
    loading.value = true;
    const { data } = await client.get('/finance-payroll/bank-transactions', { params: { bank_account_id: accountId.value } });
    transactions.value = data;
    loading.value = false;
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { type: 'Deposit', amount: 0, date: new Date().toISOString().slice(0, 10), reference_no: '', remarks: '' });
    drawerOpen.value = true;
}

function openEdit(transaction) {
    editing.value = transaction;
    Object.assign(form, {
        type: transaction.type,
        amount: Number(transaction.amount),
        date: transaction.date ? transaction.date.slice(0, 10) : '',
        reference_no: transaction.reference_no || '',
        remarks: transaction.remarks || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, bank_account_id: accountId.value };
        if (editing.value) {
            await client.put(`/finance-payroll/bank-transactions/${editing.value.id}`, payload);
            pushToast('Transaction updated.', 'success');
        } else {
            await client.post('/finance-payroll/bank-transactions', payload);
            pushToast('Transaction recorded.', 'success');
        }
        drawerOpen.value = false;
        const { data } = await client.get('/finance-payroll/bank-accounts');
        accounts.value = data;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(transaction) {
    transactions.value = transactions.value.filter((t) => t.id !== transaction.id);
    await client.delete(`/finance-payroll/bank-transactions/${transaction.id}`);
    const { data } = await client.get('/finance-payroll/bank-accounts');
    accounts.value = data;
    pushToast('Transaction removed.', 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
