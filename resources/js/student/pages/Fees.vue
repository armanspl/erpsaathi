<template>
    <div class="sd space-y-5">
        <div>
            <h1 class="sd-page-title">Fees</h1>
            <p v-if="summary?.session" class="sd-page-sub">Session {{ summary.session.name }} · figures till {{ tillLabel }}</p>
        </div>

        <!-- Totals: always on top so paid / due are visible from every tab -->
        <section v-if="summary?.summary" class="sd-kpis" aria-label="Fee totals">
            <article class="sd-kpi">
                <div class="sd-kpi__top">
                    <p class="sd-kpi__label">Total Charge</p>
                    <span class="sd-kpi__icon"><AppIcon name="wallet" class="h-5 w-5" /></span>
                </div>
                <p class="sd-kpi__value">{{ money(summary.summary.total_charge) }}</p>
                <p class="sd-kpi__sub">Till {{ tillLabel }}</p>
            </article>
            <article class="sd-kpi is-amber">
                <div class="sd-kpi__top">
                    <p class="sd-kpi__label">Concession</p>
                    <span class="sd-kpi__icon"><AppIcon name="rupee" class="h-5 w-5" /></span>
                </div>
                <p class="sd-kpi__value">{{ money(summary.summary.concession) }}</p>
                <p class="sd-kpi__sub">Discount given</p>
            </article>
            <article class="sd-kpi is-emerald">
                <div class="sd-kpi__top">
                    <p class="sd-kpi__label">Fee Paid</p>
                    <span class="sd-kpi__icon"><AppIcon name="check-circle" class="h-5 w-5" /></span>
                </div>
                <p class="sd-kpi__value">{{ money(summary.summary.paid) }}</p>
                <div class="sd-kpi__bar"><span :style="{ width: paidPercent + '%' }"></span></div>
                <p class="sd-kpi__sub">{{ paidPercent }}% of payable</p>
            </article>
            <article class="sd-kpi is-rose">
                <div class="sd-kpi__top">
                    <p class="sd-kpi__label">Fee Due</p>
                    <span class="sd-kpi__icon"><AppIcon name="alert" class="h-5 w-5" /></span>
                </div>
                <p class="sd-kpi__value">{{ money(summary.summary.due) }}</p>
                <p class="sd-kpi__sub">{{ summary.summary.due > 0 ? 'Please pay at the school office' : 'All clear 🎉' }}</p>
            </article>
        </section>

        <nav class="sd-tabs">
            <button
                v-for="t in visibleTabs"
                :key="t.key"
                type="button"
                class="sd-tab"
                :class="{ 'is-active': tab === t.key }"
                @click="tab = t.key"
            >
                {{ t.label }}
                <span v-if="t.key === 'pending' && pending.length" class="sd-tab__count">{{ pending.length }}</span>
            </button>
        </nav>

        <!-- Fee Summary: fee head-wise -->
        <section v-if="tab === 'summary'" class="sd-card">
            <header class="sd-card__head"><h2><AppIcon name="layers" class="h-4 w-4" /> Fee head-wise summary</h2></header>
            <div v-if="summary" class="overflow-x-auto">
                <table class="sd-table">
                    <thead>
                        <tr><th>Fee Head</th><th class="text-right">Charge</th><th class="text-right">Concession</th><th class="text-right">Paid</th><th class="text-right">Due</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, idx) in summary.categories" :key="c.fee_head || idx">
                            <td class="font-semibold">{{ c.fee_head || c.category || c.label || '—' }}</td>
                            <td class="text-right">{{ money(c.charge) }}</td>
                            <td class="text-right">{{ money(c.concession) }}</td>
                            <td class="text-right sd-ok">{{ money(c.paid) }}</td>
                            <td class="text-right" :class="c.due > 0 ? 'sd-bad font-semibold' : ''">{{ money(c.due) }}</td>
                        </tr>
                        <tr v-if="!summary.categories?.length"><td colspan="5" class="sd-empty">No fee structure set for this session.</td></tr>
                    </tbody>
                    <tfoot v-if="summary.categories?.length">
                        <tr>
                            <td class="font-bold">Total</td>
                            <td class="text-right font-bold">{{ money(headTotal('charge')) }}</td>
                            <td class="text-right font-bold">{{ money(headTotal('concession')) }}</td>
                            <td class="text-right font-bold sd-ok">{{ money(headTotal('paid')) }}</td>
                            <td class="text-right font-bold" :class="headTotal('due') > 0 ? 'sd-bad' : ''">{{ money(headTotal('due')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-else class="sd-empty">{{ loadingTab ? 'Loading…' : 'No active academic session.' }}</p>
        </section>

        <!-- Fee Paid: same record as ERP → Fee Management → Fee Paid -->
        <template v-else-if="tab === 'paid'">
            <section class="sd-card">
                <header class="sd-card__head">
                    <h2><AppIcon name="check-circle" class="h-4 w-4" /> Fee paid — {{ paidData?.session || 'this session' }}</h2>
                    <span v-if="paidData?.row?.scope_label" class="sd-pill">{{ paidData.row.scope_label }}</span>
                </header>
                <p v-if="loadingTab && !paidData" class="sd-empty">Loading…</p>
                <div v-else class="sd-chips">
                    <div v-for="chip in paidChips" :key="chip.label" class="sd-chip">
                        <p class="sd-chip__label">{{ chip.label }}</p>
                        <p class="sd-chip__value" :class="chip.cls">{{ chip.value }}</p>
                    </div>
                </div>
            </section>

            <p v-if="paidData && !paidData.receipts.length" class="sd-card sd-empty">No payments recorded in this session yet.</p>

            <section v-for="rc in paidData?.receipts || []" :key="rc.id" class="sd-card sd-receipt">
                <header class="sd-receipt__head">
                    <div><p class="sd-chip__label">Receipt</p><p class="sd-receipt__no">{{ rc.receipt_no }}</p></div>
                    <div><p class="sd-chip__label">Paid Date</p><p>{{ formatDate(rc.payment_date) }}</p></div>
                    <div><p class="sd-chip__label">Mode</p><p>{{ rc.payment_mode || '—' }}<span v-if="rc.reference_no" class="sd-muted"> · {{ rc.reference_no }}</span></p></div>
                    <span v-if="rc.status && rc.status !== 'Paid'" class="sd-pill is-bad">{{ rc.status }}</span>
                    <div class="sd-receipt__paid">
                        <p class="sd-chip__label">Paid Amount</p>
                        <p class="sd-ok">{{ money(rc.totals.paid) }}</p>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="sd-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Fee Name</th><th>Fee Type</th><th>Pay Month</th><th>Pay Year</th>
                                <th class="text-right">Amount</th><th class="text-right">Discount</th><th class="text-right">Final Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(l, i) in rc.lines" :key="i">
                                <td class="sd-muted">{{ i + 1 }}</td>
                                <td class="font-semibold">{{ l.fee_name }}</td>
                                <td>{{ l.fee_type || '—' }}</td>
                                <td>{{ l.month || '—' }}</td>
                                <td>{{ l.year || '—' }}</td>
                                <td class="text-right">{{ money(l.amount) }}</td>
                                <td class="text-right">{{ l.discount > 0 ? money(l.discount) : '—' }}</td>
                                <td class="text-right font-semibold">{{ money(l.final) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="sd-totals">
                    <div><span>Total Fee</span><b>{{ money(rc.totals.total_fee) }}</b></div>
                    <div><span>Total Discount</span><b>{{ money(rc.totals.total_discount) }}</b></div>
                    <div><span>Grand Total</span><b>{{ money(rc.totals.grand_total) }}</b></div>
                    <div v-if="rc.totals.fine > 0"><span>Fine</span><b class="sd-bad">{{ money(rc.totals.fine) }}</b></div>
                    <div class="is-ok"><span>Paid Amount</span><b>{{ money(rc.totals.paid) }}</b></div>
                    <div :class="{ 'is-bad': rc.totals.balance > 0 }"><span>Total Balance</span><b>{{ money(rc.totals.balance) }}</b></div>
                    <div v-if="rc.totals.refunded > 0"><span>Refunded</span><b class="sd-bad">{{ money(rc.totals.refunded) }}</b></div>
                </div>
                <div class="sd-receipt__foot">
                    <span v-if="rc.remarks" class="sd-muted">Remarks: {{ rc.remarks }}</span>
                    <button v-if="isModuleVisible('receipts')" type="button" class="btn-outline !py-1 !text-xs" @click="downloadReceipt(rc)">Download receipt</button>
                </div>
            </section>
        </template>

        <!-- Fee Due -->
        <section v-else-if="tab === 'pending'" class="sd-card">
            <header class="sd-card__head">
                <h2><AppIcon name="alert" class="h-4 w-4" /> Fee due — month wise</h2>
                <span v-if="pending.length" class="sd-pill is-bad">Total due {{ money(pendingTotal) }}</span>
            </header>
            <div class="overflow-x-auto">
                <table class="sd-table">
                    <thead><tr><th>Month</th><th>Fee Heads</th><th class="text-right">Charge</th><th class="text-right">Concession</th><th class="text-right">Paid</th><th class="text-right">Due</th></tr></thead>
                    <tbody>
                        <tr v-for="(m, idx) in pending" :key="m.month_key || m.month || idx">
                            <td class="font-semibold">{{ m.month_label || m.month }}</td>
                            <td class="sd-muted sd-wrap">{{ m.categories || '—' }}</td>
                            <td class="text-right">{{ money(m.charge) }}</td>
                            <td class="text-right">{{ money(m.concession) }}</td>
                            <td class="text-right sd-ok">{{ money(m.paid) }}</td>
                            <td class="text-right sd-bad font-semibold">{{ money(m.due) }}</td>
                        </tr>
                        <tr v-if="!pending.length"><td colspan="6" class="sd-empty">{{ loadingTab ? 'Loading…' : "No pending dues — you're all caught up 🎉" }}</td></tr>
                    </tbody>
                    <tfoot v-if="pending.length">
                        <tr><td colspan="5" class="text-right font-semibold">Total due</td><td class="text-right sd-bad font-bold">{{ money(pendingTotal) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Fee History: session-wise ledger -->
        <template v-else-if="tab === 'history'">
            <p v-if="loadingTab && !history.length" class="sd-card sd-empty">Loading…</p>
            <p v-else-if="!history.length" class="sd-card sd-empty">No fee history yet.</p>
            <section v-for="h in history" :key="h.session" class="sd-card">
                <header class="sd-card__head">
                    <h2><AppIcon name="history" class="h-4 w-4" /> Session {{ h.session }}</h2>
                    <span class="sd-pill" :class="h.is_current ? 'is-ok' : ''">{{ h.is_current ? 'Current session' : 'Previous session' }}</span>
                </header>
                <div class="sd-chips">
                    <div v-if="h.total !== null" class="sd-chip"><p class="sd-chip__label">Total Fee (year)</p><p class="sd-chip__value">{{ money(h.total) }}</p></div>
                    <div v-if="h.concession !== null" class="sd-chip"><p class="sd-chip__label">Concession</p><p class="sd-chip__value">{{ money(h.concession) }}</p></div>
                    <div class="sd-chip"><p class="sd-chip__label">Received</p><p class="sd-chip__value sd-ok">{{ money(h.received) }}</p></div>
                    <div v-if="h.balance !== null" class="sd-chip"><p class="sd-chip__label">Balance (year)</p><p class="sd-chip__value" :class="h.balance > 0 ? 'sd-bad' : ''">{{ money(h.balance) }}</p></div>
                    <div class="sd-chip"><p class="sd-chip__label">Receipts</p><p class="sd-chip__value">{{ h.receipts.length }}</p></div>
                </div>
                <div v-if="h.receipts.length" class="overflow-x-auto">
                    <table class="sd-table">
                        <thead>
                            <tr><th class="w-8"></th><th>Receipt No.</th><th>Date</th><th>Fee Heads</th><th>Mode</th><th class="text-right">Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <template v-for="r in h.receipts" :key="r.id">
                                <tr class="sd-row-click" @click="toggle(`${h.session}-${r.id}`)">
                                    <td><span class="sd-caret" :class="{ 'is-open': open[`${h.session}-${r.id}`] }">›</span></td>
                                    <td class="font-semibold">{{ r.receipt_no }}</td>
                                    <td>{{ formatDate(r.payment_date) }}</td>
                                    <td class="sd-muted sd-wrap">{{ r.fee_heads || '—' }}</td>
                                    <td><span class="sd-pill">{{ r.payment_mode || '—' }}</span></td>
                                    <td class="text-right font-semibold">{{ money(r.amount) }}</td>
                                    <td><span class="sd-pill" :class="statusClass(r.status)">{{ r.status || 'Paid' }}</span></td>
                                </tr>
                                <tr v-if="open[`${h.session}-${r.id}`]" class="sd-detail">
                                    <td></td>
                                    <td colspan="6">
                                        <table class="sd-table sd-table--inner">
                                            <thead><tr><th>Fee Head</th><th>Month(s)</th><th class="text-right">Charge</th><th class="text-right">Discount</th><th class="text-right">Paid</th></tr></thead>
                                            <tbody>
                                                <tr v-for="(l, i) in r.lines" :key="i">
                                                    <td class="font-semibold">{{ l.fee_head }}</td>
                                                    <td class="sd-muted sd-wrap">{{ l.months || '—' }}</td>
                                                    <td class="text-right">{{ money(l.charge) }}</td>
                                                    <td class="text-right">{{ l.discount ? money(l.discount) : '—' }}</td>
                                                    <td class="text-right font-semibold">{{ money(l.amount) }}</td>
                                                </tr>
                                                <tr v-if="!r.lines?.length"><td colspan="5" class="sd-empty">No line details on this receipt.</td></tr>
                                            </tbody>
                                        </table>
                                        <div class="sd-detail__foot">
                                            <span v-if="r.discount_amount">Discount: <b>{{ money(r.discount_amount) }}</b></span>
                                            <span v-if="r.fine_amount">Late fine: <b>{{ money(r.fine_amount) }}</b></span>
                                            <span v-if="r.refunded_amount">Refunded: <b>{{ money(r.refunded_amount) }}</b></span>
                                            <span>Total paid: <b>{{ money(r.amount) }}</b></span>
                                            <span v-if="r.remarks" class="sd-muted">Remarks: {{ r.remarks }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import client from '../api/client';
import { isModuleVisible } from '../store';
import { downloadFile } from '../utils/download';
import AppIcon from '../../erp/components/common/AppIcon.vue';

const ALL_TABS = [
    { key: 'summary', label: 'Fee Summary', moduleKey: 'fee_summary' },
    { key: 'paid', label: 'Fee Paid', moduleKey: 'paid_fees' },
    { key: 'pending', label: 'Fee Due', moduleKey: 'pending_fees' },
    { key: 'history', label: 'Fee History', moduleKey: 'fee_history' },
];
const visibleTabs = ALL_TABS.filter((t) => isModuleVisible(t.moduleKey));
const route = useRoute();
const tab = ref(visibleTabs.find((t) => t.key === route.query.tab)?.key || visibleTabs[0]?.key || 'summary');

const summary = ref(null);
const paidData = ref(null);
const pending = ref([]);
const history = ref([]);
const open = reactive({});
const loadingTab = ref(false);
const loaded = { summary: false, paid: false, pending: false, history: false };

const tillLabel = new Date().toLocaleDateString('en-IN', { month: 'short', year: 'numeric' });
const pendingTotal = computed(() => pending.value.reduce((s, m) => s + Number(m.due || 0), 0));
const paidPercent = computed(() => {
    const s = summary.value?.summary;
    const payable = s ? Number(s.total_charge || 0) - Number(s.concession || 0) : 0;
    return payable > 0 ? Math.min(100, Math.round((Number(s.paid || 0) / payable) * 100)) : 0;
});

/** Same chips as the ERP Fee Paid row (summary of this session). */
const paidChips = computed(() => {
    const r = paidData.value?.row;
    const s = paidData.value?.summary || {};
    if (!r) {
        return [
            { label: 'Receipts', value: String(s.receipts ?? 0) },
            { label: 'Total received', value: money(s.paid), cls: 'sd-ok' },
        ];
    }
    const chips = [
        { label: 'Receipts', value: String(s.receipts ?? 0) },
        { label: 'Total received', value: money(s.paid), cls: 'sd-ok' },
        { label: 'Counted toward dues', value: money(r.counted_paid), cls: 'sd-ok' },
        { label: 'Charged (till now)', value: money(r.total_fee) },
        { label: 'Concession', value: money(r.total_discount) },
        { label: 'Outstanding (till now)', value: money(r.due), cls: r.due > 0 ? 'sd-bad' : '' },
        { label: 'Paid Through', value: r.paid_through_label || '—' },
    ];
    if (r.advance_months_label && r.advance_months_label !== '—') {
        chips.push({ label: 'Advance Months', value: r.advance_months_label });
    }
    return chips;
});

function headTotal(col) {
    return (summary.value?.categories || []).reduce((s, c) => s + Number(c[col] || 0), 0);
}
function money(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function formatDate(d) {
    if (!d) return '—';
    return new Date(`${d}T00:00:00`).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
function statusClass(status) {
    const s = String(status || 'Paid').toLowerCase();
    return s.includes('refund') || s.includes('cancel') ? 'is-bad' : 'is-ok';
}
function toggle(key) {
    open[key] = !open[key];
}
function downloadReceipt(row) {
    downloadFile(`/fees/receipt/${row.id}`, `${String(row.receipt_no || 'receipt').replace(/[\\/]/g, '-')}.pdf`);
}

const inflight = {};
function loadTab(key) {
    if (loaded[key]) return Promise.resolve();
    return (inflight[key] ??= fetchTab(key).finally(() => delete inflight[key]));
}

async function fetchTab(key) {
    loadingTab.value = true;
    try {
        if (key === 'summary') {
            if (!isModuleVisible('fee_summary')) return;
            const { data } = await client.get('/fees/summary');
            summary.value = data.session ? data : null;
        } else if (key === 'paid') {
            const { data } = await client.get('/fees/paid');
            paidData.value = data;
        } else if (key === 'pending') {
            const { data } = await client.get('/fees/pending');
            pending.value = data.months || [];
        } else if (key === 'history') {
            const { data } = await client.get('/fees/history');
            history.value = data.sessions || [];
        }
        loaded[key] = true;
    } finally {
        loadingTab.value = false;
    }
}

// The totals cards come from the summary, whichever tab opens first; the Fee Due count badge needs pending.
loadTab('summary');
if (isModuleVisible('pending_fees')) loadTab('pending');
watch(tab, (key) => loadTab(key), { immediate: true });
</script>
