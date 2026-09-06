<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Cash Book</h1>
            <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Cash Book']" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">From</label>
                    <input v-model="from" type="date" class="form-input" @change="load" />
                </div>
                <div>
                    <label class="form-label">To</label>
                    <input v-model="to" type="date" class="form-input" @change="load" />
                </div>
            </div>
        </div>

        <template v-if="book">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <StatCard label="Cash In" :value="`₹${book.total_in.toLocaleString('en-IN')}`" color="emerald" icon="⬇️" />
                <StatCard label="Cash Out" :value="`₹${book.total_out.toLocaleString('en-IN')}`" color="rose" icon="⬆️" />
                <StatCard label="Closing Balance" :value="`₹${book.closing_balance.toLocaleString('en-IN')}`" color="indigo" icon="💰" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Particulars</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">In</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Out</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!book.rows.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">No cash transactions in this range.</td>
                        </tr>
                        <tr v-for="(r, idx) in book.rows" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(r.date) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.particulars }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.type }}</td>
                            <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400">{{ r.in ? `₹${r.in.toLocaleString('en-IN')}` : '—' }}</td>
                            <td class="px-4 py-3 text-rose-600 dark:text-rose-400">{{ r.out ? `₹${r.out.toLocaleString('en-IN')}` : '—' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">₹{{ r.balance.toLocaleString('en-IN') }}</td>
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

const from = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const to = ref(new Date().toISOString().slice(0, 10));
const book = ref(null);

async function load() {
    const { data } = await client.get('/finance-payroll/cash-book', { params: { from: from.value, to: to.value } });
    book.value = data;
}
load();

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
