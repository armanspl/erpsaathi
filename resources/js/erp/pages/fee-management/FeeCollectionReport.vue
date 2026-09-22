<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
            <Breadcrumb :items="['Dashboard', 'Fee Management', pageTitle]" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <input v-model="filterValues.search" type="text" placeholder="Search student, receipt..." class="form-input xl:col-span-2" />
                <input v-model="filterValues.from" type="date" class="form-input" />
                <input v-model="filterValues.to" type="date" class="form-input" />
                <select v-model="filterValues.mode" class="form-input">
                    <option value="">Payment Mode — All</option>
                    <option v-for="m in modes" :key="m">{{ m }}</option>
                </select>
                <button type="button" class="btn-outline" @click="resetFilters">Reset</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Transactions" :value="filteredPayments.length" color="indigo" icon="🧾" />
            <StatCard label="Total Collected" :value="`₹${totalCollected.toLocaleString('en-IN')}`" color="emerald" icon="💰" />
            <StatCard label="Average Receipt" :value="`₹${averageReceipt.toLocaleString('en-IN')}`" color="sky" icon="📊" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('receipt_no')">Receipt No {{ sortArrow('receipt_no') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('student_name')">Student {{ sortArrow('student_name') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('amount')">Amount {{ sortArrow('amount') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('payment_mode')">Mode {{ sortArrow('payment_mode') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('payment_date')">Date {{ sortArrow('payment_date') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('collected_by_name')">Collected By {{ sortArrow('collected_by_name') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredPayments.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No transactions match your filters.</td>
                    </tr>
                    <tr v-for="p in filteredPayments" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.receipt_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.student.name }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">₹{{ Number(p.amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.payment_mode }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(p.payment_date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ p.collected_by?.name || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { erpStore } from '../../store';

const route = useRoute();
const modes = ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Cheque'];

const pageTitle = computed(() => ({
    '/fee-management/fee-collection-report': 'Fee Collection Report',
    '/fee-management/daily-collection': 'Daily Collection',
    '/fee-management/online-payments': 'Online Payments',
}[route.path] || 'Fee Collection Report'));

const loading = ref(true);
const payments = ref([]);
const filterValues = reactive({ search: '', from: '', to: '', mode: '' });
const sortKey = ref('payment_date');
const sortDir = ref('asc');

function applyDefaults() {
    const today = new Date().toISOString().slice(0, 10);
    if (route.path === '/fee-management/daily-collection') {
        filterValues.from = today;
        filterValues.to = today;
    } else if (route.path === '/fee-management/online-payments') {
        filterValues.mode = 'UPI';
    }
}
applyDefaults();

function resetFilters() {
    Object.assign(filterValues, { search: '', from: '', to: '', mode: '' });
    applyDefaults();
}

async function load() {
    loading.value = true;
    const { data } = await client.get('/fee-management/payments', { params: { limit: 500 } });
    payments.value = data;
    loading.value = false;
}
load();
watch(() => erpStore.currentSession, load);

function sortValue(row, key) {
    switch (key) {
        case 'student_name':
            return row.student?.name || '';
        case 'collected_by_name':
            return row.collected_by?.name || '';
        case 'amount':
            return Number(row.amount) || 0;
        default:
            return row[key];
    }
}

const filteredPayments = computed(() => {
    const rows = payments.value.filter((p) => {
        if (filterValues.search && !`${p.student.name} ${p.student.admission_no} ${p.receipt_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.from && p.payment_date < filterValues.from) return false;
        if (filterValues.to && p.payment_date > filterValues.to) return false;
        if (filterValues.mode && p.payment_mode !== filterValues.mode) return false;
        if (route.path === '/fee-management/online-payments' && p.payment_mode === 'Cash') return false;
        return true;
    });
    return [...rows].sort((a, b) => {
        let av = sortValue(a, sortKey.value);
        let bv = sortValue(b, sortKey.value);
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

const totalCollected = computed(() => filteredPayments.value.reduce((sum, p) => sum + (Number(p.amount) - Number(p.refunded_amount)), 0));
const averageReceipt = computed(() => (filteredPayments.value.length ? Math.round(totalCollected.value / filteredPayments.value.length) : 0));

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
