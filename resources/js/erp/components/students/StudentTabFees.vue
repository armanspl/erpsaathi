<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else class="space-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800">
                <p class="text-lg font-bold text-slate-800 dark:text-slate-100">₹{{ due.total_fee.toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Total Fee</p>
            </div>
            <div class="rounded-lg bg-sky-50 p-3 text-center dark:bg-sky-500/10">
                <p class="text-lg font-bold text-sky-600 dark:text-sky-400">₹{{ due.total_discount.toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Discount</p>
            </div>
            <div class="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-500/10">
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">₹{{ due.total_paid.toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Paid</p>
            </div>
            <div class="rounded-lg bg-rose-50 p-3 text-center dark:bg-rose-500/10">
                <p class="text-lg font-bold text-rose-600 dark:text-rose-400">₹{{ due.due.toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Due</p>
            </div>
        </div>

        <div v-if="!due.session" class="text-sm text-slate-400">No academic session available — fee structure can't be computed.</div>

        <div v-if="due.breakdown?.length">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Fee Structure — {{ due.session?.name }}</h4>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs text-slate-400 dark:border-slate-800">
                        <th class="pb-2 font-medium">Head</th>
                        <th class="pb-2 font-medium">Frequency</th>
                        <th class="pb-2 font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="b in due.breakdown" :key="b.fee_head_id">
                        <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ b.fee_head_name }}</td>
                        <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ b.frequency }}</td>
                        <td class="py-2.5 text-slate-500 dark:text-slate-400">₹{{ b.amount.toLocaleString('en-IN') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Payment History</h4>
            <table v-if="payments.length" class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs text-slate-400 dark:border-slate-800">
                        <th class="pb-2 font-medium">Receipt No.</th>
                        <th class="pb-2 font-medium">Date</th>
                        <th class="pb-2 font-medium">Amount</th>
                        <th class="pb-2 font-medium">Mode</th>
                        <th class="pb-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="p in payments" :key="p.id">
                        <td class="py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ p.receipt_no }}</td>
                        <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ formatDate(p.payment_date) }}</td>
                        <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">₹{{ Number(p.amount).toLocaleString('en-IN') }}</td>
                        <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ p.payment_mode }}</td>
                        <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(p.status)">{{ p.status }}</span></td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No payments recorded yet.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { erpStore } from '../../store';

const props = defineProps({ student: { type: Object, required: true } });

const loading = ref(true);
const due = ref({ session: null, breakdown: [], total_fee: 0, total_discount: 0, total_paid: 0, due: 0 });
const payments = ref([]);

async function load() {
    loading.value = true;
    const [dueRes, paymentsRes] = await Promise.all([
        client.get(`/fee-management/students/${props.student.id}/due`),
        client.get('/fee-management/payments', { params: { student_id: props.student.id } }),
    ]);
    due.value = dueRes.data;
    payments.value = paymentsRes.data;
    loading.value = false;
}
watch([() => props.student.id, () => erpStore.currentSession], load, { immediate: true });

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
