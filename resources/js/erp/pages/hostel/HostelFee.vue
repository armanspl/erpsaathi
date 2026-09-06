<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Hostel Fee</h1>
            <Breadcrumb :items="['Dashboard', 'Hostel', 'Hostel Fee']" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <label class="form-label">Period</label>
                    <input v-model="period" type="month" class="form-input" @change="load" />
                </div>
                <button type="button" class="btn-primary" :disabled="generating" @click="generate">{{ generating ? 'Generating...' : `⚙️ Generate Fees for ${period}` }}</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Fees" :value="fees.length" color="indigo" icon="🧾" />
            <StatCard label="Paid" :value="fees.filter((f) => f.status === 'Paid').length" color="emerald" icon="✅" />
            <StatCard label="Pending" :value="fees.filter((f) => f.status === 'Pending').length" color="amber" icon="⏳" />
            <StatCard label="Total Amount" :value="`₹${totalAmount.toLocaleString('en-IN')}`" color="sky" icon="💰" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Room / Bed</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!fees.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No fees generated for this period yet.</td>
                    </tr>
                    <tr v-for="f in fees" :key="f.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ f.allocation.student.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ f.allocation.bed.room.room_no }} — {{ f.allocation.bed.bed_no }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">₹{{ Number(f.amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(f.status === 'Paid' ? 'Active' : 'Inactive')">{{ f.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="f.status === 'Pending'" type="button" class="btn-outline !py-1 !text-xs" @click="openPay(f)">💳 Mark Paid</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="`Collect Fee — ${paying?.allocation.student.name}`" @close="drawerOpen = false">
            <div>
                <label class="form-label">Payment Mode</label>
                <select v-model="payForm.payment_mode" class="form-input">
                    <option>Cash</option>
                    <option>Bank</option>
                </select>
            </div>
            <div>
                <label class="form-label">Paid On</label>
                <input v-model="payForm.paid_on" type="date" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="markPaid">{{ saving ? 'Saving...' : 'Confirm Payment' }}</button>
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
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const generating = ref(false);
const fees = ref([]);
const period = ref(new Date().toISOString().slice(0, 7));
const drawerOpen = ref(false);
const paying = ref(null);

const payForm = reactive({ payment_mode: 'Cash', paid_on: new Date().toISOString().slice(0, 10) });

const totalAmount = computed(() => fees.value.reduce((sum, f) => sum + Number(f.amount), 0));

async function load() {
    loading.value = true;
    const { data } = await client.get('/hostel/fees', { params: { period: period.value } });
    fees.value = data;
    loading.value = false;
}
load();

async function generate() {
    generating.value = true;
    try {
        const { data } = await client.post('/hostel/fees/generate', { period: period.value });
        pushToast(`Generated ${data.generated} fee(s) for ${period.value}.`, 'success');
        await load();
    } finally {
        generating.value = false;
    }
}

function openPay(fee) {
    paying.value = fee;
    Object.assign(payForm, { payment_mode: 'Cash', paid_on: new Date().toISOString().slice(0, 10) });
    drawerOpen.value = true;
}

async function markPaid() {
    saving.value = true;
    try {
        await client.patch(`/hostel/fees/${paying.value.id}/pay`, payForm);
        pushToast(`Fee collected from ${paying.value.allocation.student.name}.`, 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
