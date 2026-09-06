<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Bank Accounts</h1>
                <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Bank Accounts']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Account</button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Total Accounts" :value="accounts.length" color="indigo" icon="🏦" />
            <StatCard label="Total Balance" :value="`₹${totalBalance.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900 sm:col-span-2 lg:col-span-3">
                Loading...
            </div>
            <div v-else-if="!accounts.length" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900 sm:col-span-2 lg:col-span-3">
                No bank accounts added yet.
            </div>
            <div v-for="a in accounts" :key="a.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ a.account_name }}</p>
                        <p class="text-xs text-slate-400">{{ a.bank_name }} · {{ a.branch || '—' }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(a)">✎</button>
                        <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(a)">🗑</button>
                    </div>
                </div>
                <p class="mt-3 font-mono text-xs text-slate-500 dark:text-slate-400">A/C: {{ a.account_number }}</p>
                <p class="mt-3 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ Number(a.current_balance).toLocaleString('en-IN') }}</p>
                <RouterLink :to="`/finance-and-payroll/bank-transactions?account=${a.id}`" class="btn-outline mt-3 block text-center !py-1.5 !text-xs">View Transactions</RouterLink>
            </div>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Bank Account' : 'Add Bank Account'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Account Name</label>
                <input v-model="form.account_name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Bank Name</label>
                <input v-model="form.bank_name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Account Number</label>
                    <input v-model="form.account_number" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">IFSC Code</label>
                    <input v-model="form.ifsc_code" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Branch</label>
                    <input v-model="form.branch" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Opening Balance</label>
                    <input v-model.number="form.opening_balance" type="number" step="0.01" class="form-input" :disabled="!!editing" />
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
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const accounts = ref([]);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ account_name: '', bank_name: '', account_number: '', ifsc_code: '', branch: '', opening_balance: 0 });

const totalBalance = computed(() => accounts.value.reduce((sum, a) => sum + Number(a.current_balance), 0));

async function load() {
    loading.value = true;
    const { data } = await client.get('/finance-payroll/bank-accounts');
    accounts.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { account_name: '', bank_name: '', account_number: '', ifsc_code: '', branch: '', opening_balance: 0 });
    drawerOpen.value = true;
}

function openEdit(account) {
    editing.value = account;
    Object.assign(form, {
        account_name: account.account_name,
        bank_name: account.bank_name,
        account_number: account.account_number,
        ifsc_code: account.ifsc_code || '',
        branch: account.branch || '',
        opening_balance: Number(account.opening_balance),
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/finance-payroll/bank-accounts/${editing.value.id}`, form);
            pushToast('Bank account updated.', 'success');
        } else {
            await client.post('/finance-payroll/bank-accounts', form);
            pushToast('Bank account added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(account) {
    accounts.value = accounts.value.filter((a) => a.id !== account.id);
    await client.delete(`/finance-payroll/bank-accounts/${account.id}`);
    pushToast(`Bank account "${account.account_name}" deleted.`, 'success');
}
</script>
