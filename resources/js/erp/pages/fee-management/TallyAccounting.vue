<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Tally Accounting</h1>
            <p class="mt-1 text-sm text-slate-500">Map fee heads to Tally ledgers and export receipt vouchers as CSV or XML.</p>
        </div>

        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- LEDGERS -->
        <template v-if="activeTab === 'ledgers'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Company & receipt ledgers</h2>
                <p class="mt-1 text-sm text-slate-500">Cash/UPI/Card/Bank receipts post to cash or bank ledger; fee heads credit mapped income ledgers.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="form-label">Tally company name</label>
                        <input v-model="tally.company_name" type="text" class="form-input" placeholder="School company in Tally" />
                    </div>
                    <div>
                        <label class="form-label">Cash ledger</label>
                        <input v-model="tally.cash_ledger" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Bank ledger</label>
                        <input v-model="tally.bank_ledger" type="text" class="form-input" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Fallback / balancing ledger</label>
                        <input v-model="tally.party_ledger" type="text" class="form-input" />
                        <p class="mt-1 text-xs text-slate-400">Used when fee heads are unmapped or amounts need balancing.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Fee head → income ledger</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee Head</th>
                                <th class="px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Tally Ledger Name</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!feeHeads.length">
                                <td colspan="2" class="px-2 py-8 text-center text-slate-400">No fee heads. Add them in Fee Settings.</td>
                            </tr>
                            <tr v-for="h in feeHeads" :key="h.id">
                                <td class="px-2 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ h.name }}</td>
                                <td class="px-2 py-2.5">
                                    <input v-model="tally.fee_ledgers[h.id]" type="text" class="form-input" :placeholder="h.name" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" class="btn-primary" :disabled="saving" @click="saveTally">
                        {{ saving ? 'Saving...' : 'Save ledger mapping' }}
                    </button>
                </div>
            </div>
        </template>

        <!-- EXPORT -->
        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]">
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="filters.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="filters.to" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input">
                            <option :value="null">All branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="btn-primary w-full" :disabled="previewLoading" @click="loadPreview">
                            {{ previewLoading ? 'Loading...' : 'Preview' }}
                        </button>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" class="btn-outline w-full" @click="exportFile('csv')">CSV</button>
                        <button type="button" class="btn-outline w-full" @click="exportFile('xml')">XML</button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary-100 bg-primary-50/70 px-4 py-3 text-sm dark:border-primary-500/20 dark:bg-primary-500/10">
                <p class="font-semibold text-slate-800 dark:text-slate-100">
                    Vouchers: {{ summary.vouchers }}
                    <span class="mx-2 text-slate-300">·</span>
                    Amount: <span class="text-primary-700">₹{{ money(summary.amount) }}</span>
                </p>
                <p class="text-xs text-slate-500">Import CSV into Excel or XML via Tally → Import Data → Vouchers.</p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="previewLoading" class="px-6 py-16 text-center text-sm text-slate-400">Building vouchers...</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Voucher</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Narration</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entries</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!vouchers.length">
                                <td colspan="5" class="px-4 py-12 text-center text-slate-400">No receipts in this range. Adjust dates and Preview.</td>
                            </tr>
                            <tr v-for="v in vouchers" :key="v.voucher_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-3 whitespace-nowrap text-slate-500">{{ v.date }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-700">{{ v.voucher_no }}</td>
                                <td class="max-w-[280px] truncate px-3 py-3 text-slate-600" :title="v.narration">{{ v.narration }}</td>
                                <td class="px-3 py-3 text-xs text-slate-500">
                                    <div v-for="(e, i) in v.entries" :key="i">
                                        {{ e.ledger }}
                                        <span v-if="e.dr"> Dr ₹{{ money(e.dr) }}</span>
                                        <span v-else> Cr ₹{{ money(e.cr) }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">₹{{ money(v.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'ledgers', label: 'Ledger mapping' },
    { id: 'export', label: 'Export vouchers' },
];
const activeTab = ref('ledgers');
const saving = ref(false);
const previewLoading = ref(false);
const feeHeads = ref([]);
const branches = ref([]);
const vouchers = ref([]);
const summary = reactive({ vouchers: 0, amount: 0 });

const tally = reactive({
    company_name: '',
    cash_ledger: 'Cash',
    bank_ledger: 'Bank',
    party_ledger: 'Fee Receivable',
    fee_ledgers: {},
});

const filters = reactive({
    from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    branch_id: null,
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function filterParams() {
    const p = {};
    if (filters.from) p.from = filters.from;
    if (filters.to) p.to = filters.to;
    if (filters.branch_id) p.branch_id = filters.branch_id;
    return p;
}

async function loadSettings() {
    const [{ data }, lookups] = await Promise.all([
        client.get('/fee-management/settings'),
        fetchAcademicsLookups(),
    ]);
    feeHeads.value = data.fee_heads || [];
    branches.value = lookups.branches || [];
    Object.assign(tally, {
        company_name: data.tally?.company_name || '',
        cash_ledger: data.tally?.cash_ledger || 'Cash',
        bank_ledger: data.tally?.bank_ledger || 'Bank',
        party_ledger: data.tally?.party_ledger || 'Fee Receivable',
        fee_ledgers: { ...(data.tally?.fee_ledgers || {}) },
    });
    for (const h of feeHeads.value) {
        if (tally.fee_ledgers[h.id] == null) tally.fee_ledgers[h.id] = h.name;
    }
}

async function saveTally() {
    saving.value = true;
    try {
        await client.put('/fee-management/settings/tally', {
            company_name: tally.company_name,
            cash_ledger: tally.cash_ledger,
            bank_ledger: tally.bank_ledger,
            party_ledger: tally.party_ledger,
            fee_ledgers: tally.fee_ledgers,
        });
        pushToast('Tally ledger mapping saved.', 'success');
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not save mapping.', 'error');
    } finally {
        saving.value = false;
    }
}

async function loadPreview() {
    previewLoading.value = true;
    try {
        const { data } = await client.get('/fee-management/tally/preview', { params: filterParams() });
        vouchers.value = data.vouchers || [];
        Object.assign(summary, data.summary || { vouchers: 0, amount: 0 });
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not build preview.', 'error');
    } finally {
        previewLoading.value = false;
    }
}

async function exportFile(format) {
    try {
        const params = new URLSearchParams({ ...filterParams(), format });
        const response = await client.get(`/fee-management/tally/export?${params}`, { responseType: 'blob' });
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `tally-fee-vouchers.${format}`;
        a.click();
        URL.revokeObjectURL(url);
        pushToast(`Exported ${format.toUpperCase()}.`, 'success');
    } catch (e) {
        pushToast('Export failed.', 'error');
    }
}

watch(activeTab, (tab) => {
    if (tab === 'export' && !vouchers.value.length) loadPreview();
});

onMounted(loadSettings);
</script>
