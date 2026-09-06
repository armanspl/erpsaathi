<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Configure fee heads, fines, discounts, and receipt preferences.</p>
        </div>

        <nav class="flex flex-wrap gap-6 border-b border-slate-200 dark:border-slate-800">
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

        <!-- HEADS -->
        <template v-if="activeTab === 'heads'">
            <div class="flex justify-end">
                <button type="button" class="btn-primary" @click="openHeadAdd">+ Add Fee Head</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loadingHeads"><td colspan="3" class="px-4 py-10 text-center text-slate-400">Loading...</td></tr>
                        <tr v-else-if="!heads.length"><td colspan="3" class="px-4 py-10 text-center text-slate-400">No fee heads yet.</td></tr>
                        <tr v-for="h in heads" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ h.name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ h.description || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="mr-1 text-xs font-semibold text-primary-600" @click="openHeadEdit(h)">Edit</button>
                                <button type="button" class="text-xs font-semibold text-rose-600" @click="removeHead(h)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- FINES -->
        <template v-else-if="activeTab === 'fines'">
            <div class="flex justify-end">
                <button type="button" class="btn-primary" @click="openFineAdd">+ Add Fine Rule</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grace Days</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loadingFines"><td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td></tr>
                        <tr v-else-if="!fines.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">No fine rules yet.</td></tr>
                        <tr v-for="r in fines" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 capitalize text-slate-500">{{ String(r.type).replace('_', ' ') }}</td>
                            <td class="px-4 py-3">₹{{ money(r.amount) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ r.grace_days }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="mr-1 text-xs font-semibold text-primary-600" @click="openFineEdit(r)">Edit</button>
                                <button type="button" class="text-xs font-semibold text-rose-600" @click="removeFine(r)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- DISCOUNTS -->
        <template v-else-if="activeTab === 'discounts'">
            <div class="flex justify-end">
                <button type="button" class="btn-primary" @click="openDiscountAdd">+ Add Discount</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee Head</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Value</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="loadingDiscounts"><td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td></tr>
                        <tr v-else-if="!discounts.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">No discounts yet.</td></tr>
                        <tr v-for="d in discounts" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                                {{ d.student?.name }}
                                <div class="font-mono text-xs font-normal text-slate-400">{{ d.student?.admission_no }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ d.fee_head?.name || 'All Fees' }}</td>
                            <td class="px-4 py-3 capitalize text-slate-500">{{ d.type }}</td>
                            <td class="px-4 py-3">{{ d.type === 'percentage' ? `${d.value}%` : `₹${money(d.value)}` }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ d.reason || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="mr-1 text-xs font-semibold text-primary-600" @click="openDiscountEdit(d)">Edit</button>
                                <button type="button" class="text-xs font-semibold text-rose-600" @click="removeDiscount(d)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- PREFERENCES -->
        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Collection preferences</h2>
                <p class="mt-1 text-sm text-slate-500">Defaults used on Pay Fee, Fee Receipt, and printed deposit receipts.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Default payment mode</label>
                        <select v-model="prefs.default_payment_mode" class="form-input">
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Card</option>
                            <option>Bank Transfer</option>
                            <option>Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Receipt “Paid At” label</label>
                        <input v-model="prefs.receipt_paid_at" type="text" class="form-input" placeholder="SCHOOL" />
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input v-model="prefs.auto_select_current_month" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                    Auto-select current unpaid month when opening Pay Fee / Fee Receipt
                </label>
                <div class="mt-5 flex justify-end">
                    <button type="button" class="btn-primary" :disabled="savingPrefs" @click="savePrefs">
                        {{ savingPrefs ? 'Saving...' : 'Save preferences' }}
                    </button>
                </div>
            </div>
            <div class="rounded-xl border border-primary-100 bg-primary-50/60 px-4 py-3 text-sm text-slate-600 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-slate-300">
                Tally company name and ledger mappings live under
                <router-link class="font-semibold text-primary-700 underline" to="/fee-management/tally-accounting">Tally Accounting</router-link>.
            </div>
        </template>

        <!-- Head drawer -->
        <SlideOver :open="headOpen" :title="headEditing ? 'Edit Fee Head' : 'Add Fee Head'" @close="headOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="headForm.name" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Description</label>
                <input v-model="headForm.description" type="text" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="headOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveHead">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>

        <!-- Fine drawer -->
        <SlideOver :open="fineOpen" :title="fineEditing ? 'Edit Fine Rule' : 'Add Fine Rule'" @close="fineOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="fineForm.name" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="fineForm.type" class="form-input">
                        <option value="per_day">Per Day</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Amount (₹)</label>
                    <input v-model.number="fineForm.amount" type="number" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Grace Days</label>
                <input v-model.number="fineForm.grace_days" type="number" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="fineOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveFine">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>

        <!-- Discount drawer -->
        <SlideOver :open="discountOpen" :title="discountEditing ? 'Edit Discount' : 'Add Discount'" @close="discountOpen = false">
            <div>
                <label class="form-label">Student</label>
                <select v-model="discountForm.student_id" class="form-input">
                    <option :value="null">Select student</option>
                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.admission_no }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Fee Head (optional)</label>
                <select v-model="discountForm.fee_head_id" class="form-input">
                    <option :value="null">All Fees</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="discountForm.type" class="form-input">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Value</label>
                    <input v-model.number="discountForm.value" type="number" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Reason</label>
                <input v-model="discountForm.reason" type="text" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="discountOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveDiscount">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { fetchFeeLookups, fetchFeeStudentsLite, invalidateFeeLookups } from '../../api/feeManagement';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'heads', label: 'Fee Heads' },
    { id: 'fines', label: 'Fine Rules' },
    { id: 'discounts', label: 'Discounts' },
    { id: 'prefs', label: 'Preferences' },
];
const activeTab = ref('heads');
const saving = ref(false);
const savingPrefs = ref(false);

const heads = ref([]);
const fines = ref([]);
const discounts = ref([]);
const students = ref([]);
const loadingHeads = ref(false);
const loadingFines = ref(false);
const loadingDiscounts = ref(false);

const prefs = reactive({
    default_payment_mode: 'Cash',
    receipt_paid_at: 'SCHOOL',
    auto_select_current_month: true,
});

const headOpen = ref(false);
const headEditing = ref(null);
const headForm = reactive({ name: '', description: '' });

const fineOpen = ref(false);
const fineEditing = ref(null);
const fineForm = reactive({ name: '', type: 'fixed', amount: null, grace_days: 0 });

const discountOpen = ref(false);
const discountEditing = ref(null);
const discountForm = reactive({ student_id: null, fee_head_id: null, type: 'percentage', value: null, reason: '' });

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

async function loadHeads() {
    loadingHeads.value = true;
    try {
        const fee = await fetchFeeLookups({ force: true });
        heads.value = fee.heads || [];
    } finally {
        loadingHeads.value = false;
    }
}

async function loadFines() {
    loadingFines.value = true;
    try {
        const fee = await fetchFeeLookups({ force: true });
        fines.value = fee.fine_rules || [];
    } finally {
        loadingFines.value = false;
    }
}

async function loadDiscounts() {
    loadingDiscounts.value = true;
    try {
        const [dRes, st] = await Promise.all([
            client.get('/fee-management/discounts'),
            fetchFeeStudentsLite(),
        ]);
        discounts.value = dRes.data || [];
        students.value = st || [];
        if (!heads.value.length) await loadHeads();
    } finally {
        loadingDiscounts.value = false;
    }
}

async function loadPrefs() {
    const { data } = await client.get('/fee-management/settings');
    Object.assign(prefs, data.preferences || {});
}

function openHeadAdd() {
    headEditing.value = null;
    Object.assign(headForm, { name: '', description: '' });
    headOpen.value = true;
}
function openHeadEdit(h) {
    headEditing.value = h;
    Object.assign(headForm, { name: h.name, description: h.description || '' });
    headOpen.value = true;
}
async function saveHead() {
    saving.value = true;
    try {
        if (headEditing.value) await client.put(`/fee-management/heads/${headEditing.value.id}`, headForm);
        else await client.post('/fee-management/heads', headForm);
        invalidateFeeLookups();
        headOpen.value = false;
        pushToast('Fee head saved.', 'success');
        await loadHeads();
    } finally {
        saving.value = false;
    }
}
async function removeHead(h) {
    await client.delete(`/fee-management/heads/${h.id}`);
    invalidateFeeLookups();
    pushToast('Fee head deleted.', 'success');
    await loadHeads();
}

function openFineAdd() {
    fineEditing.value = null;
    Object.assign(fineForm, { name: '', type: 'fixed', amount: null, grace_days: 0 });
    fineOpen.value = true;
}
function openFineEdit(r) {
    fineEditing.value = r;
    Object.assign(fineForm, { name: r.name, type: r.type, amount: Number(r.amount), grace_days: r.grace_days });
    fineOpen.value = true;
}
async function saveFine() {
    saving.value = true;
    try {
        if (fineEditing.value) await client.put(`/fee-management/fine-rules/${fineEditing.value.id}`, fineForm);
        else await client.post('/fee-management/fine-rules', fineForm);
        invalidateFeeLookups();
        fineOpen.value = false;
        pushToast('Fine rule saved.', 'success');
        await loadFines();
    } finally {
        saving.value = false;
    }
}
async function removeFine(r) {
    await client.delete(`/fee-management/fine-rules/${r.id}`);
    invalidateFeeLookups();
    pushToast('Fine rule deleted.', 'success');
    await loadFines();
}

function openDiscountAdd() {
    discountEditing.value = null;
    Object.assign(discountForm, { student_id: null, fee_head_id: null, type: 'percentage', value: null, reason: '' });
    discountOpen.value = true;
}
function openDiscountEdit(d) {
    discountEditing.value = d;
    Object.assign(discountForm, {
        student_id: d.student?.id,
        fee_head_id: d.fee_head?.id ?? null,
        type: d.type,
        value: Number(d.value),
        reason: d.reason || '',
    });
    discountOpen.value = true;
}
async function saveDiscount() {
    saving.value = true;
    try {
        if (discountEditing.value) await client.put(`/fee-management/discounts/${discountEditing.value.id}`, discountForm);
        else await client.post('/fee-management/discounts', discountForm);
        invalidateFeeLookups();
        discountOpen.value = false;
        pushToast('Discount saved.', 'success');
        await loadDiscounts();
    } finally {
        saving.value = false;
    }
}
async function removeDiscount(d) {
    await client.delete(`/fee-management/discounts/${d.id}`);
    invalidateFeeLookups();
    pushToast('Discount deleted.', 'success');
    await loadDiscounts();
}

async function savePrefs() {
    savingPrefs.value = true;
    try {
        await client.put('/fee-management/settings/preferences', prefs);
        pushToast('Preferences saved.', 'success');
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not save preferences.', 'error');
    } finally {
        savingPrefs.value = false;
    }
}

watch(activeTab, (tab) => {
    if (tab === 'heads') loadHeads();
    if (tab === 'fines') loadFines();
    if (tab === 'discounts') loadDiscounts();
    if (tab === 'prefs') loadPrefs();
});

onMounted(() => {
    loadHeads();
});
</script>
