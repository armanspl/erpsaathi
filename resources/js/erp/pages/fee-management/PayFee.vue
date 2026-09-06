<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Pay Fee</h1>
                <p class="mt-1 text-sm text-slate-500">Quick counter collection — select student, unpaid months, and collect.</p>
            </div>
            <router-link to="/fee-management/fee-receipt" class="btn-outline inline-flex items-center gap-1.5 text-sm">
                Open Fee Receipt
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </router-link>
        </div>

        <!-- Student search -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Search student</label>
            <div class="relative">
                <input
                    v-model="search"
                    type="search"
                    class="form-input"
                    placeholder="Search by name or admission no..."
                    @focus="showResults = true; ensureStudentsLoaded()"
                    @blur="hideResultsSoon"
                />
                <div v-if="showResults && search && filteredStudents.length" class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <button
                        v-for="s in filteredStudents"
                        :key="s.id"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                        @mousedown.prevent="selectStudent(s)"
                    >
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                        <span class="text-xs text-slate-400">{{ s.admission_no }} · {{ studentClassLabel(s) }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!student" class="rounded-2xl border border-dashed border-slate-200 px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-700">
            Search and select a student to collect fees.
        </div>

        <template v-else>
            <div class="flex flex-col gap-3 rounded-2xl border border-primary-100 bg-primary-50/50 px-4 py-3 text-sm dark:border-primary-500/20 dark:bg-primary-500/10 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-slate-900 dark:text-slate-100">
                        {{ student.name }}
                        <span class="font-normal text-slate-500">- {{ student.admission_no }}</span>
                    </p>
                    <p class="mt-0.5 text-slate-600 dark:text-slate-300">{{ studentPlaceLabel }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-slate-500">Remaining: <strong class="text-rose-600">₹{{ money(selectedRemainingDue) }}</strong></p>
                    <button type="button" class="btn-outline !text-xs" @click="clearStudent">Change</button>
                </div>
            </div>

            <div v-if="loadingDue" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                Loading fee details...
            </div>

            <template v-else-if="due">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs text-slate-400">Selected charge</p>
                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">₹{{ money(selectedRemainingDue) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs text-slate-400">Session paid</p>
                        <p class="mt-1 text-lg font-bold text-emerald-600">₹{{ money(due.total_paid) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs text-slate-400">Collecting now</p>
                        <p class="mt-1 text-lg font-bold text-primary-600">₹{{ money(collectingTotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs text-slate-400">After collect</p>
                        <p class="mt-1 text-lg font-bold text-rose-600">₹{{ money(Math.max(0, selectedRemainingDue - collectingTotal)) }}</p>
                    </div>
                </div>

                <!-- Months -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Months to pay</h3>
                        <p class="text-xs text-slate-400">Paid months are locked. Only unpaid months are collected.</p>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="block in monthBlocks"
                            :key="block.key"
                            class="rounded-xl border p-3"
                            :class="block.key === 'curr' ? 'border-primary-200 bg-primary-50/40 dark:border-primary-500/30 dark:bg-primary-500/10' : 'border-slate-100 dark:border-slate-800'"
                        >
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ block.label }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="m in block.months"
                                    :key="m.key"
                                    class="inline-flex items-center gap-1.5 text-sm"
                                    :class="monthLabelClass(m.key)"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-primary-600 disabled:opacity-60"
                                        :checked="selectedMonths.includes(m.key)"
                                        :disabled="isMonthLocked(m.key)"
                                        @change="toggleMonth(m.key, $event.target.checked)"
                                    />
                                    <span>{{ m.label }}</span>
                                    <span v-if="isMonthPaid(m.key)" class="text-[10px] font-semibold uppercase tracking-wide text-emerald-600">(Paid)</span>
                                    <span v-else-if="isMonthBeforeFeeStart(m.key)" class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">(Before start)</span>
                                </label>
                            </div>
                        </div>
                        <p v-if="!monthBlocks.length" class="text-sm text-slate-400">No session months available.</p>
                    </div>
                </div>

                <!-- Transport -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-slate-100">Transport</h3>
                    <label class="mb-3 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="transport.apply" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Apply school transport for this receipt
                    </label>
                    <div v-if="transport.apply" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Route</label>
                            <select v-model="transport.route_id" class="form-input" @change="onTransportRouteChange">
                                <option :value="null">Select route</option>
                                <option v-for="r in transportRoutes" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Address</label>
                            <select v-model="transport.stop_id" class="form-input" :disabled="!transport.route_id" @change="onTransportStopChange">
                                <option :value="null">Select address</option>
                                <option v-for="s in transportStops" :key="s.id" :value="s.id">{{ s.stop_name }}{{ s.fare != null ? ` (₹${money(s.fare)})` : '' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Amount</label>
                            <input :value="money(transportDue)" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                            <p v-if="transportFare" class="mt-1 text-xs text-slate-400">₹{{ money(transportFare) }}/month</p>
                        </div>
                        <div>
                            <label class="form-label">Paid</label>
                            <input v-model.number="transport.fee" type="number" min="0" step="0.01" class="form-input" placeholder="0" />
                        </div>
                    </div>
                </div>

                <!-- Collect -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-slate-100">Collect payment</h3>
                    <div v-if="!due.breakdown?.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                        No fee structure for this student/session. Set one up in Fee Structure first.
                    </div>
                    <div v-else-if="!chargeableBreakdown.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                        All fee types for the selected months are settled. Pick unpaid months to collect remaining dues.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="item in chargeableBreakdown"
                            :key="item.fee_head_id"
                            class="flex flex-col gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <label class="flex min-w-0 flex-1 items-start gap-3">
                                <input v-model="selectedHeadIds" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" :value="item.fee_head_id" @change="syncFeePaid(item)" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ item.fee_head_name }}</span>
                                    <span class="text-xs text-slate-400">{{ frequencyLabel(item.frequency) }} · ₹{{ money(item.amount) }}</span>
                                    <span class="mt-0.5 block text-xs text-amber-600">Due ₹{{ money(feeBase(item)) }}</span>
                                </span>
                            </label>
                            <div>
                                <label class="form-label !mb-1">Paid</label>
                                <input
                                    v-model.number="amounts[item.fee_head_id]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-input w-32"
                                    :disabled="!selectedHeadIds.includes(item.fee_head_id)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="form-label">Payment date</label>
                            <input v-model="paymentDate" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Payment mode</label>
                            <select v-model="paymentMode" class="form-input">
                                <option>Cash</option>
                                <option>UPI</option>
                                <option>Card</option>
                                <option>Bank Transfer</option>
                                <option>Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Fine (₹)</label>
                            <input v-model.number="fineAmount" type="number" min="0" step="0.01" class="form-input" />
                        </div>
                        <div v-if="paymentMode === 'Bank Transfer'">
                            <label class="form-label">Bank account</label>
                            <select v-model="bankAccountId" class="form-input">
                                <option :value="null">Select bank account</option>
                                <option v-for="acc in bankAccounts" :key="acc.id" :value="acc.id">{{ acc.account_name }} — {{ acc.bank_name }} ({{ acc.account_number }})</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Remarks</label>
                        <input v-model="remarks" type="text" class="form-input" placeholder="Optional notes" />
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <div>
                            <p class="text-sm text-slate-500">Collecting now</p>
                            <p class="text-2xl font-bold text-primary-600">₹{{ money(collectingTotal) }}</p>
                        </div>
                        <button type="button" class="btn-primary" :disabled="!canSubmit || collecting" @click="collect">
                            {{ collecting ? 'Collecting...' : 'Collect & open receipt' }}
                        </button>
                    </div>
                </div>
            </template>
        </template>

        <ConfirmDialog
            v-model:open="confirmCollectOpen"
            title="Collect fee"
            :message="confirmCollectMessage"
            confirm-label="Collect & open receipt"
            :busy="collecting"
            busy-label="Collecting…"
            @confirm="collectConfirmed"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { fetchFeeStudentsLite } from '../../api/feeManagement';
import { erpStore } from '../../store';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';

const search = ref('');
const showResults = ref(false);
const students = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const feeMeta = ref(null);

const student = ref(null);
const due = ref(null);
const loadingDue = ref(false);
const paidMonths = ref([]);
const feeStartMonth = ref(null);
const paidByHead = ref({});
const paidByHeadDiscount = ref({});
const paidByHeadMonth = ref({});
const paidByHeadMonthDiscount = ref({});
const selectedMonths = ref([]);
const selectedHeadIds = ref([]);
const amounts = reactive({});
const fineAmount = ref(0);
const paymentDate = ref(new Date().toISOString().slice(0, 10));
const paymentMode = ref('Cash');
const bankAccounts = ref([]);
const bankAccountId = ref(null);
const remarks = ref('');
const collecting = ref(false);
const confirmCollectOpen = ref(false);
const confirmCollectMessage = ref('');
const transportRoutes = ref([]);
const transportStops = ref([]);
const transportHeadId = ref(null);
const transportFeeStartMonth = ref(null);
const studentTransport = ref(null);
const transport = reactive({
    apply: false,
    route_id: null,
    stop_id: null,
    fee: 0,
});

const filteredStudents = computed(() => {
    if (!search.value) return [];
    const q = search.value.toLowerCase();
    return students.value
        .filter((s) => s.status !== 'Inactive' && `${s.name} ${s.admission_no}`.toLowerCase().includes(q))
        .slice(0, 10);
});

const monthBlocks = computed(() => {
    const blocks = [];
    if (feeMeta.value?.previous_session?.months?.length) {
        blocks.push({ key: 'prev', label: 'Previous session', months: feeMeta.value.previous_session.months });
    }
    if (feeMeta.value?.current_session?.months?.length) {
        blocks.push({ key: 'curr', label: 'Current session', months: feeMeta.value.current_session.months });
    }
    if (feeMeta.value?.next_session?.months?.length) {
        blocks.push({ key: 'next', label: 'Next session', months: feeMeta.value.next_session.months });
    }
    return blocks;
});

const studentPlaceLabel = computed(() => {
    const s = student.value;
    if (!s) return '—';
    return studentClassLabel(s);
});

const collectingTotal = computed(() => {
    const items = selectedHeadIds.value.reduce((sum, id) => sum + (Number(amounts[id]) || 0), 0);
    const transportPaid = transport.apply ? (Number(transport.fee) || 0) : 0;
    return items + transportPaid + (Number(fineAmount.value) || 0);
});

const transportFare = computed(() => {
    const stop = transportStops.value.find((s) => s.id === transport.stop_id);
    if (stop?.fare != null) return Number(stop.fare) || 0;
    return Number(studentTransport.value?.routeStop?.fare) || 0;
});

const transportDue = computed(() => {
    const months = transportBillableMonths(unpaidSelectedMonths());
    const fare = transportFare.value;
    const headId = transportHeadId.value;
    if (!months.length || fare <= 0) return 0;
    let prior = 0;
    if (headId) {
        for (const m of months) {
            prior += alreadyPaidForHeadMonth({ fee_head_id: headId }, m);
        }
    }
    return Math.max(0, Math.round((fare * months.length - prior) * 100) / 100);
});

function transportBillableMonths(months) {
    const start = transportFeeStartMonth.value;
    if (!start) return months;
    return months.filter((m) => m >= start);
}

const selectedRemainingDue = computed(() => {
    let total = 0;
    for (const item of due.value?.breakdown || []) {
        total += feeBase(item);
    }
    if (transport.apply || studentTransport.value?.status === 'Active') {
        total += transportDue.value;
    }
    return Math.round(total * 100) / 100;
});

const canSubmit = computed(() => {
    if (!student.value) return false;
    if (!unpaidSelectedMonths().length) return false;
    const hasFee = selectedHeadIds.value.some((id) => (Number(amounts[id]) || 0) > 0);
    const hasTransport = transport.apply && (Number(transport.fee) || 0) > 0;
    return hasFee || hasTransport || (Number(fineAmount.value) || 0) > 0;
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function frequencyLabel(f) {
    return { monthly: 'Monthly', quarterly: 'Quarterly', annual: 'Annual', one_time: 'One time' }[f] || String(f || '').replace('_', ' ');
}

function studentClassLabel(s) {
    const branch = s.branch?.name || branches.value.find((b) => b.id === s.branch_id)?.name || '—';
    const klass = s.school_class?.name || s.schoolClass?.name || classes.value.find((c) => c.id === s.school_class_id)?.name || '—';
    const section = s.section?.name || sections.value.find((sec) => sec.id === s.section_id)?.name || '—';
    return `${branch} / ${klass} / ${section}`;
}

function isMonthPaid(key) {
    return paidMonths.value.includes(key);
}

function isMonthBeforeFeeStart(key) {
    return !!(feeStartMonth.value && key < feeStartMonth.value);
}

function isMonthLocked(key) {
    return isMonthPaid(key) || isMonthBeforeFeeStart(key);
}

function monthLabelClass(key) {
    if (isMonthPaid(key)) return 'cursor-not-allowed text-emerald-600';
    if (isMonthBeforeFeeStart(key)) return 'cursor-not-allowed text-slate-400 line-through';
    return 'text-slate-700 dark:text-slate-200';
}

function unpaidSelectedMonths() {
    return selectedMonths.value.filter((m) => !isMonthLocked(m));
}

function toggleMonth(key, checked) {
    if (isMonthLocked(key)) return;
    if (checked) {
        if (!selectedMonths.value.includes(key)) selectedMonths.value = [...selectedMonths.value, key];
    } else {
        selectedMonths.value = selectedMonths.value.filter((m) => m !== key);
    }
}

function feeFrequency(item) {
    return String(item?.frequency || 'monthly').toLowerCase().replace(' ', '_');
}

function headKey(item) {
    return String(item?.fee_head_id ?? '');
}

function alreadyPaidForHead(item) {
    const key = headKey(item);
    return (Number(paidByHead.value[key]) || 0) + (Number(paidByHeadDiscount.value[key]) || 0);
}

function alreadyPaidForHeadMonth(item, monthKey) {
    const key = headKey(item);
    const byMonth = paidByHeadMonth.value[key] || {};
    const discByMonth = paidByHeadMonthDiscount.value[key] || {};
    return (Number(byMonth[monthKey]) || 0) + (Number(discByMonth[monthKey]) || 0);
}

function quarterlyMonths(months) {
    return months.filter((key) => {
        const monthNum = Number(String(key).slice(5, 7));
        return [4, 7, 10, 1].includes(monthNum);
    });
}

function unitsForFee(item) {
    const months = unpaidSelectedMonths();
    if (!months.length) return 0;
    const freq = feeFrequency(item);
    if (freq === 'monthly') {
        return months.filter((m) => Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHeadMonth(item, m)) > 0.0001).length;
    }
    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        const targets = qMonths.length ? qMonths : months.slice(0, 1);
        return targets.filter((m) => Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHeadMonth(item, m)) > 0.0001).length;
    }
    return Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHead(item)) > 0.0001 ? 1 : 0;
}

function feeBase(item) {
    const amount = Number(item.amount) || 0;
    const freq = feeFrequency(item);
    const months = unpaidSelectedMonths();

    if (freq === 'annual' || freq === 'one_time') {
        return Math.max(0, amount - alreadyPaidForHead(item));
    }

    if (freq === 'monthly') {
        return months.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }

    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        const targets = qMonths.length ? qMonths : (months.length ? [months[0]] : []);
        return targets.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }

    return Math.max(0, amount - alreadyPaidForHead(item));
}

const chargeableBreakdown = computed(() =>
    (due.value?.breakdown || []).filter((item) => feeBase(item) > 0.0001),
);

function syncFeePaid(item) {
    if (!selectedHeadIds.value.includes(item.fee_head_id)) {
        amounts[item.fee_head_id] = 0;
        return;
    }
    amounts[item.fee_head_id] = feeBase(item);
}

function refreshSelectedAmounts() {
    const chargeableIds = chargeableBreakdown.value.map((i) => i.fee_head_id);
    const chargeableSet = new Set(chargeableIds);
    let nextIds = selectedHeadIds.value.filter((id) => chargeableSet.has(id));
    if (!nextIds.length && chargeableIds.length) {
        nextIds = [...chargeableIds];
    }
    const same =
        nextIds.length === selectedHeadIds.value.length &&
        nextIds.every((id) => selectedHeadIds.value.includes(id));
    if (!same) {
        selectedHeadIds.value = nextIds;
    }
    for (const item of due.value?.breakdown || []) {
        if (!chargeableSet.has(item.fee_head_id)) {
            amounts[item.fee_head_id] = 0;
            continue;
        }
        if (!selectedHeadIds.value.includes(item.fee_head_id)) {
            amounts[item.fee_head_id] = 0;
            continue;
        }
        syncFeePaid(item);
    }
}

function hideResultsSoon() {
    setTimeout(() => {
        showResults.value = false;
    }, 150);
}

async function boot() {
    const [lookups, meta] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/fee-management/due/meta').then((r) => r.data).catch(() => null),
    ]);
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    feeMeta.value = meta;
    client.get('/finance-payroll/bank-accounts').then((r) => { bankAccounts.value = r.data || []; }).catch(() => {});
    // Warm student cache in background for search.
    fetchFeeStudentsLite()
        .then((st) => {
            students.value = Array.isArray(st) ? st : [];
        })
        .catch(() => {});
}

async function ensureStudentsLoaded() {
    if (students.value.length) return;
    const st = await fetchFeeStudentsLite();
    students.value = Array.isArray(st) ? st : [];
}

async function selectStudent(s) {
    student.value = s;
    search.value = '';
    showResults.value = false;
    await loadDue();
}

async function loadDue() {
    if (!student.value) return;
    loadingDue.value = true;
    selectedHeadIds.value = [];
    Object.keys(amounts).forEach((k) => delete amounts[k]);
    selectedMonths.value = [];
    paidMonths.value = [];
    feeStartMonth.value = null;
    paidByHead.value = {};
    paidByHeadDiscount.value = {};
    paidByHeadMonth.value = {};
    paidByHeadMonthDiscount.value = {};
    fineAmount.value = 0;
    remarks.value = '';
    paymentDate.value = new Date().toISOString().slice(0, 10);
    transport.apply = false;
    transport.route_id = null;
    transport.stop_id = null;
    transport.fee = 0;
    transportHeadId.value = null;
    transportFeeStartMonth.value = null;
    transportStops.value = [];
    studentTransport.value = null;
    try {
        const [dueRes, transportRes] = await Promise.all([
            client.get(`/fee-management/students/${student.value.id}/due`),
            client.get('/transport/student-transport', { params: { student_id: student.value.id } }).catch(() => ({ data: [] })),
        ]);
        const data = dueRes.data;
        due.value = data;
        paidMonths.value = Array.isArray(data.paid_months) ? data.paid_months : [];
        feeStartMonth.value = data.fee_start_month || null;
        transportFeeStartMonth.value = data.transport_fee_start_month || null;
        paidByHead.value = data.paid_by_head && typeof data.paid_by_head === 'object' ? data.paid_by_head : {};
        paidByHeadDiscount.value = data.paid_by_head_discount && typeof data.paid_by_head_discount === 'object'
            ? data.paid_by_head_discount
            : {};
        paidByHeadMonth.value = data.paid_by_head_month && typeof data.paid_by_head_month === 'object'
            ? data.paid_by_head_month
            : {};
        paidByHeadMonthDiscount.value = data.paid_by_head_month_discount && typeof data.paid_by_head_month_discount === 'object'
            ? data.paid_by_head_month_discount
            : {};
        transportHeadId.value = data.transport_head_id || null;
        (data.breakdown || []).forEach((item) => {
            amounts[item.fee_head_id] = 0;
        });

        const assignment = (Array.isArray(transportRes.data) ? transportRes.data : []).find((row) => row.status === 'Active') || transportRes.data?.[0] || null;
        studentTransport.value = assignment;
        if (assignment) {
            if (!transportFeeStartMonth.value) {
                transportFeeStartMonth.value = (assignment.fee_start_month || assignment.start_date || '').toString().slice(0, 7) || null;
            }
            transport.apply = true;
            transport.route_id = assignment.route_id || assignment.route?.id || null;
            transport.stop_id = assignment.route_stop_id || assignment.routeStop?.id || null;
            await ensureTransportRoutes();
            if (transport.route_id) await loadTransportStops(transport.route_id);
        }

        const currMonths = feeMeta.value?.current_session?.months || [];
        const allMonths = monthBlocks.value.flatMap((b) => b.months.map((m) => m.key));
        const nowKey = new Date().toISOString().slice(0, 7);
        const isBillable = (k) => !feeStartMonth.value || k >= feeStartMonth.value;
        const unpaidCurr = currMonths.map((m) => m.key).filter((k) => isBillable(k) && !paidMonths.value.includes(k));
        const unpaidAll = allMonths.filter((k) => isBillable(k) && !paidMonths.value.includes(k));
        if (unpaidCurr.includes(nowKey)) selectedMonths.value = unpaidCurr.filter((k) => k <= nowKey);
        else if (unpaidCurr.length) selectedMonths.value = [unpaidCurr[0]];
        else if (unpaidAll.length) selectedMonths.value = [unpaidAll[0]];
        else selectedMonths.value = [];
        selectedHeadIds.value = chargeableBreakdown.value.map((i) => i.fee_head_id);
        refreshSelectedAmounts();
        syncTransportPaid();
    } finally {
        loadingDue.value = false;
    }
}

function clearStudent() {
    student.value = null;
    due.value = null;
    selectedMonths.value = [];
    paidMonths.value = [];
    feeStartMonth.value = null;
    paidByHead.value = {};
    paidByHeadDiscount.value = {};
    paidByHeadMonth.value = {};
    paidByHeadMonthDiscount.value = {};
    selectedHeadIds.value = [];
    transport.apply = false;
    transport.route_id = null;
    transport.stop_id = null;
    transport.fee = 0;
    studentTransport.value = null;
}

function syncTransportPaid() {
    if (!transport.apply) return;
    transport.fee = transportDue.value;
}

const transportRoutesLoading = ref(false);
async function ensureTransportRoutes() {
    if (transportRoutes.value.length || transportRoutesLoading.value) return;
    transportRoutesLoading.value = true;
    try {
        const { data } = await client.get('/transport/routes');
        transportRoutes.value = Array.isArray(data) ? data : [];
    } catch {
        transportRoutes.value = [];
    } finally {
        transportRoutesLoading.value = false;
    }
}

async function onTransportRouteChange() {
    transport.stop_id = null;
    transport.fee = 0;
    await loadTransportStops(transport.route_id);
}

async function loadTransportStops(routeId) {
    if (!routeId) {
        transportStops.value = [];
        return;
    }
    const { data } = await client.get('/transport/stops', { params: { route_id: routeId } });
    transportStops.value = Array.isArray(data) ? data : [];
}

function onTransportStopChange() {
    syncTransportPaid();
}

function allocateHeadAcrossMonths(item, months, paidAmt) {
    const amount = Number(item.amount) || 0;
    const freq = feeFrequency(item);
    const sorted = [...months].sort();

    if (freq === 'annual' || freq === 'one_time') {
        const dueAmt = Math.max(0, amount - alreadyPaidForHead(item));
        if (paidAmt <= 0) return [];
        return [{
            fee_head_id: item.fee_head_id,
            amount: paidAmt,
            charge: Math.max(dueAmt, paidAmt),
            months: sorted.length ? [sorted[0]] : [],
        }];
    }

    let paidLeft = paidAmt;
    const targets = freq === 'quarterly'
        ? (quarterlyMonths(sorted).length ? quarterlyMonths(sorted) : sorted.slice(0, 1))
        : sorted;
    const lines = [];

    for (const m of targets) {
        const unitDue = Math.max(0, amount - alreadyPaidForHeadMonth(item, m));
        if (unitDue <= 0.0001) continue;
        const paidTake = Math.min(paidLeft, unitDue);
        paidLeft -= paidTake;
        if (paidTake <= 0.0001) continue;
        lines.push({
            fee_head_id: item.fee_head_id,
            amount: paidTake,
            charge: unitDue,
            months: [m],
        });
    }

    if (paidLeft > 0.0001 && lines.length) {
        lines[lines.length - 1].amount = Math.round((lines[lines.length - 1].amount + paidLeft) * 100) / 100;
        lines[lines.length - 1].charge = Math.max(lines[lines.length - 1].charge, lines[lines.length - 1].amount);
    }

    return lines.filter((l) => l.amount > 0);
}

function openDepositReceipt(paymentId) {
    if (!paymentId) return;
    downloadPdf(`/fee-management/payments/${paymentId}/pdf`, `fee-receipt-${paymentId}.pdf`).catch(() => {
        pushToast('Could not open receipt PDF.', 'error');
    });
}

async function collect() {
    if (!canSubmit.value) {
        pushToast(unpaidSelectedMonths().length ? 'Enter at least one paid amount.' : 'Select unpaid months to collect.', 'error');
        return;
    }
    const who = student.value?.name || 'this student';
    const amt = money(collectingTotal.value);
    confirmCollectMessage.value = `Collect ₹${amt} for ${who} and open the receipt?`;
    confirmCollectOpen.value = true;
}

async function collectConfirmed() {
    if (collecting.value) return;
    confirmCollectOpen.value = false;
    collecting.value = true;
    try {
        const months = unpaidSelectedMonths();
        const items = [];
        for (const id of selectedHeadIds.value) {
            const item = (due.value?.breakdown || []).find((row) => row.fee_head_id === id);
            const paidAmt = Number(amounts[id]) || 0;
            if (paidAmt <= 0 || !item) continue;
            items.push(...allocateHeadAcrossMonths(item, months, paidAmt));
        }

        const transportFee = transport.apply ? (Number(transport.fee) || 0) : 0;
        const transportCharge = transport.apply ? Math.max(transportDue.value, transportFee) : 0;
        if (!items.length && transportFee <= 0) {
            pushToast('Enter amounts for selected fee types.', 'error');
            return;
        }

        const { data } = await client.post('/fee-management/payments', {
            student_id: student.value.id,
            items,
            months,
            fine_amount: Number(fineAmount.value) || 0,
            payment_mode: paymentMode.value,
            bank_account_id: paymentMode.value === 'Bank Transfer' ? bankAccountId.value : null,
            payment_date: paymentDate.value,
            remarks: remarks.value || null,
            transport_fee: transportFee,
            transport_charge: transportCharge,
            transport_route_id: transport.apply ? transport.route_id : null,
            transport_stop_id: transport.apply ? transport.stop_id : null,
        });

        pushToast(`Payment collected — ${data.receipt_no}.`, 'success');
        openDepositReceipt(data.id);
        await loadDue();
    } catch (e) {
        const errors = e?.response?.data?.errors;
        pushToast(errors?.months?.[0] || e?.response?.data?.message || 'Could not collect payment.', 'error');
    } finally {
        collecting.value = false;
    }
}

watch(selectedMonths, () => {
    refreshSelectedAmounts();
    syncTransportPaid();
}, { deep: true });
watch(paymentMode, (mode) => {
    if (mode !== 'Bank Transfer') bankAccountId.value = null;
});
watch(() => transport.apply, (on) => {
    if (on) {
        ensureTransportRoutes();
        syncTransportPaid();
    } else {
        transport.fee = 0;
    }
});
watch(() => transport.stop_id, () => syncTransportPaid());
watch(() => erpStore.currentSession, async () => {
    const { data } = await client.get('/fee-management/due/meta').catch(() => ({ data: null }));
    feeMeta.value = data;
    await fetchFeeStudentsLite({ force: true }).then((rows) => {
        students.value = rows || [];
    });
    if (student.value) await loadDue();
});

onMounted(boot);
</script>
