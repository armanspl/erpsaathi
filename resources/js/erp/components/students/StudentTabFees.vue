<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Session fee position for {{ student.name }}
                <span v-if="due.session">· {{ due.session.name }}</span>
            </p>
            <router-link
                class="btn-primary inline-flex items-center gap-1.5 text-sm"
                :to="{ path: '/fee-management/fee-receipt', query: { student_id: student.id } }"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12M6 12h12"/></svg>
                Pay Fee
            </router-link>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800">
                <p class="text-lg font-bold text-slate-800 dark:text-slate-100">₹{{ money(due.total_fee) }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Total Fee</p>
            </div>
            <div class="rounded-lg bg-sky-50 p-3 text-center dark:bg-sky-500/10">
                <p class="text-lg font-bold text-sky-600 dark:text-sky-400">₹{{ money(due.total_discount) }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Discount / Waiver</p>
            </div>
            <div class="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-500/10">
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">₹{{ money(due.total_paid) }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Paid</p>
            </div>
            <div class="rounded-lg bg-rose-50 p-3 text-center dark:bg-rose-500/10">
                <p class="text-lg font-bold text-rose-600 dark:text-rose-400">₹{{ money(due.due) }}</p>
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
                        <td class="py-2.5 text-slate-500 dark:text-slate-400">₹{{ money(b.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Same receipt-by-receipt slide-down as Fee Paid -->
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Payment History</h4>
            <p v-if="historyLoading" class="py-6 text-center text-sm text-slate-400">Loading receipts…</p>
            <p v-else-if="historyError" class="py-4 text-center text-sm text-rose-500">
                Could not load payment details.
                <button type="button" class="ml-1 font-semibold underline" @click="loadHistory(true)">Retry</button>
            </p>
            <p v-else-if="!receipts.length" class="text-sm text-slate-400">No payments recorded yet.</p>
            <div v-else class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div
                    v-for="rc in receipts"
                    :key="rc.id"
                    class="border-b border-slate-100 last:border-b-0 dark:border-slate-800"
                >
                    <button
                        type="button"
                        class="grid w-full cursor-pointer grid-cols-[minmax(0,1.3fr)_minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_28px] items-center gap-2 px-3 py-2.5 text-left text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        :class="isOpen(rc.id) ? 'bg-primary-50/60 dark:bg-primary-900/10' : ''"
                        @click="toggle(rc.id)"
                    >
                        <span class="truncate font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ rc.receipt_no }}</span>
                        <span class="truncate text-slate-500 dark:text-slate-400">{{ fmtDate(rc.payment_date) }}</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">₹ {{ money2(rc.totals.paid) }}</span>
                        <span class="truncate text-slate-500 dark:text-slate-400">{{ rc.payment_mode || '—' }}</span>
                        <span>
                            <span
                                v-if="rc.status && rc.status !== 'Paid'"
                                class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:bg-rose-900/30"
                            >{{ rc.status }}</span>
                            <span v-else class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Paid</span>
                        </span>
                        <svg
                            class="h-4 w-4 justify-self-end text-slate-400 transition-transform"
                            :class="isOpen(rc.id) ? 'rotate-180' : ''"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <div v-if="isOpen(rc.id)" class="overflow-hidden">
                            <div class="border-t border-slate-100 bg-slate-50/60 px-3 py-3 sm:px-4 dark:border-slate-800 dark:bg-slate-900/60">
                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase text-slate-400">Receipt</p>
                                            <button
                                                type="button"
                                                class="mt-0.5 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                :title="`Open receipt ${rc.receipt_no}`"
                                                @click.stop="openReceipt(rc.id, rc.receipt_no)"
                                            >
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                                                {{ rc.receipt_no }}
                                            </button>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase text-slate-400">Paid Date</p>
                                            <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-200">{{ fmtDate(rc.payment_date) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase text-slate-400">Mode</p>
                                            <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-200">
                                                {{ rc.payment_mode || '—' }}
                                                <span v-if="rc.reference_no" class="text-xs text-slate-400"> · {{ rc.reference_no }}</span>
                                            </p>
                                        </div>
                                        <span
                                            v-if="rc.multi_session"
                                            class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                            title="This receipt also pays fees of another session"
                                        >Multi-session</span>
                                        <span
                                            v-if="rc.status && rc.status !== 'Paid'"
                                            class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:bg-rose-900/30"
                                        >{{ rc.status }}</span>
                                        <div class="ml-auto text-right">
                                            <p class="text-[11px] font-semibold uppercase text-slate-400">Paid Amount</p>
                                            <p class="mt-0.5 text-base font-bold text-emerald-600 dark:text-emerald-400">₹ {{ money2(rc.totals.paid) }}</p>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-xs text-slate-500">
                                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                                    <th class="px-4 py-2 font-semibold">#</th>
                                                    <th class="px-3 py-2 font-semibold">Fee Name</th>
                                                    <th class="px-3 py-2 font-semibold">Fee Type</th>
                                                    <th class="px-3 py-2 font-semibold">Session</th>
                                                    <th class="px-3 py-2 font-semibold">Pay Month</th>
                                                    <th class="px-3 py-2 font-semibold">Pay Year</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                                    <th class="px-3 py-2 text-right font-semibold">Discount</th>
                                                    <th class="px-4 py-2 text-right font-semibold">Final Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(l, i) in rc.lines"
                                                    :key="i"
                                                    class="border-b border-slate-50 last:border-b-0 hover:bg-slate-50/70 dark:border-slate-800/60 dark:hover:bg-slate-800/40"
                                                >
                                                    <td class="px-4 py-2 text-slate-400">{{ i + 1 }}</td>
                                                    <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ l.fee_name }}</td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ l.fee_type || '—' }}</td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ sessionLabel(l.session) }}</td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                        {{ l.month || '—' }}
                                                        <span
                                                            v-if="l.outside_session"
                                                            class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                                                            title="This month is outside the line's session — not counted in this session's dues"
                                                        >Outside session</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ l.year || '—' }}</td>
                                                    <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-200">₹ {{ money2(l.amount) }}</td>
                                                    <td class="px-3 py-2 text-right" :class="l.discount > 0 ? 'text-amber-600' : 'text-slate-500'">₹ {{ money2(l.discount) }}</td>
                                                    <td class="px-4 py-2 text-right font-semibold text-slate-800 dark:text-slate-100">₹ {{ money2(l.final) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="grid gap-2 border-t border-slate-100 bg-slate-50/50 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800 dark:bg-slate-800/30">
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-slate-500">Total Fee</span><span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ money2(rc.totals.total_fee) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-slate-500">Total Discount</span><span class="font-semibold text-amber-600">₹ {{ money2(rc.totals.total_discount) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-slate-500">Grand Total</span><span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ money2(rc.totals.grand_total) }}</span>
                                        </div>
                                        <div v-if="rc.totals.fine > 0" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-slate-500">Fine</span><span class="font-semibold text-rose-600">₹ {{ money2(rc.totals.fine) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-900/50 dark:bg-emerald-900/20">
                                            <span class="font-semibold text-emerald-700 dark:text-emerald-300">Paid Amount</span><span class="font-bold text-emerald-700 dark:text-emerald-300">₹ {{ money2(rc.totals.paid) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm" :class="rc.totals.balance > 0 ? 'border-rose-200 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-900/20' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'">
                                            <span class="text-slate-500">Total Balance</span><span class="font-semibold" :class="rc.totals.balance > 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200'">₹ {{ money2(rc.totals.balance) }}</span>
                                        </div>
                                        <div v-if="rc.totals.refunded > 0" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-slate-500">Refunded</span><span class="font-semibold text-rose-600">₹ {{ money2(rc.totals.refunded) }}</span>
                                        </div>
                                    </div>
                                    <p v-if="rc.remarks" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400 dark:border-slate-800">Remarks: {{ rc.remarks }}</p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import client from '../../api/client';
import { erpStore } from '../../store';
import { openPdfBlob } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const props = defineProps({ student: { type: Object, required: true } });

const loading = ref(true);
const due = ref({ session: null, breakdown: [], total_fee: 0, total_discount: 0, total_paid: 0, due: 0 });
const receipts = ref([]);
const historyLoading = ref(false);
const historyError = ref(false);
const openIds = ref(new Set());

async function load() {
    loading.value = true;
    openIds.value = new Set();
    try {
        const [dueRes] = await Promise.all([
            client.get(`/fee-management/students/${props.student.id}/due`),
            loadHistory(true),
        ]);
        due.value = dueRes.data;
    } finally {
        loading.value = false;
    }
}

async function loadHistory(force = false) {
    if (!force && receipts.value.length && !historyError.value) {
        return;
    }
    historyLoading.value = true;
    historyError.value = false;
    try {
        // Same payload Fee Paid uses for receipt-by-receipt expand.
        const { data } = await client.get(`/fee-management/fee-paid/${props.student.id}/details`);
        receipts.value = Array.isArray(data.receipts) ? data.receipts : [];
    } catch {
        historyError.value = true;
        receipts.value = [];
    } finally {
        historyLoading.value = false;
    }
}

watch([() => props.student.id, () => erpStore.currentSession], load, { immediate: true });

function isOpen(id) {
    return openIds.value.has(id);
}

function toggle(id) {
    const next = new Set(openIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    openIds.value = next;
}

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function money2(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(`${d}T00:00:00`);
    return Number.isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function sessionLabel(s) {
    return s ? String(s).replace(/\s*-\s*/, ' - ') : '—';
}

async function openReceipt(paymentId, receiptNo) {
    const tab = window.open('about:blank', '_blank');
    try {
        if (tab) tab.document.title = `Receipt ${receiptNo || ''}`.trim();
    } catch {
        // ignore
    }
    try {
        const { data } = await client.get(`/fee-management/payments/${paymentId}/pdf`, { responseType: 'blob' });
        openPdfBlob(data, tab);
    } catch {
        if (tab && !tab.closed) tab.close();
        pushToast('Could not open the receipt.', 'error');
    }
}
</script>
