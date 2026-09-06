<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fine Collection</h1>
            <Breadcrumb :items="['Dashboard', 'Library', 'Fine Collection']" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Pending Fines" :value="fines.length" color="amber" icon="⏳" />
            <StatCard label="Total Pending Amount" :value="`₹${totalPending.toLocaleString('en-IN')}`" color="rose" icon="💸" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Book</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Returned</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fine</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!fines.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No pending fines. 🎉</td>
                    </tr>
                    <tr v-for="f in fines" :key="f.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ f.book.title }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ f.member.member.name }} ({{ f.member.library_card_no }})</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(f.return_date) }}</td>
                        <td class="px-4 py-3 font-semibold text-rose-600 dark:text-rose-400">₹{{ Number(f.fine_amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="collect(f)">💳 Collect</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const fines = ref([]);

const totalPending = computed(() => fines.value.reduce((sum, f) => sum + Number(f.fine_amount), 0));

async function load() {
    loading.value = true;
    const { data } = await client.get('/library/fines');
    fines.value = data;
    loading.value = false;
}
load();

async function collect(fine) {
    await client.patch(`/library/fines/${fine.id}/collect`);
    pushToast(`Collected ₹${Number(fine.fine_amount).toLocaleString('en-IN')} from ${fine.member.member.name}.`, 'success');
    await load();
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
