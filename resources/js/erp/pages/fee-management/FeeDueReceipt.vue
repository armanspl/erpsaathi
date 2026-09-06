<template>
    <div class="min-h-screen bg-slate-100 px-4 py-6 print:bg-white print:p-0">
        <div class="mx-auto max-w-3xl">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 print:hidden">
                <p class="text-sm text-slate-500">Due Receipt — ready to print</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-primary" :disabled="loading || !receipt || busy" @click="printPdf">{{ busy ? 'Opening...' : 'Print PDF' }}</button>
                    <button type="button" class="btn-outline" @click="closeTab">Close</button>
                </div>
            </div>

            <div v-if="loading" class="rounded-xl bg-white px-6 py-16 text-center text-sm text-slate-400 shadow">Loading receipt...</div>
            <div v-else-if="error" class="rounded-xl bg-white px-6 py-16 text-center text-sm text-rose-600 shadow">{{ error }}</div>

            <div v-else-if="receipt" id="due-receipt-print" class="rounded-sm border border-slate-200 bg-white px-8 py-5 text-slate-800 shadow-xl print:border-0 print:shadow-none">
                <div class="text-center">
                    <div class="grid grid-cols-[3.5rem_1fr_3.5rem] items-center gap-2">
                        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-xl">
                            <img v-if="receipt.school.logo_path" :src="receipt.school.logo_path" alt="Logo" class="h-full w-full object-contain" />
                            <span v-else>🏫</span>
                        </div>
                        <div class="justify-self-center rounded bg-rose-700 px-4 py-1 text-sm font-bold uppercase tracking-wide text-white">
                            Fee Due Receipt
                        </div>
                        <div class="justify-self-end flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-xl">
                            <img v-if="receipt.school.logo_path" :src="receipt.school.logo_path" alt="Logo" class="h-full w-full object-contain" />
                            <span v-else>🏫</span>
                        </div>
                    </div>
                    <h1 class="mt-1 text-lg font-bold leading-tight text-blue-700">{{ receipt.school.name }}</h1>
                    <p v-if="receipt.school.address" class="mt-0.5 text-xs leading-snug text-slate-500">Address : {{ receipt.school.address }}</p>
                    <p class="mt-0.5 text-sm font-semibold leading-tight text-slate-600">{{ receipt.session.name }}</p>
                </div>

                <div class="mt-3 rounded bg-[#e8dcc8] px-3 py-1.5 text-center text-sm font-medium text-slate-700">
                    {{ receipt.months_bar }}
                </div>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded bg-[#e8dcc8] px-3 py-1.5 text-sm">
                    <span><strong>Receipt No :</strong> {{ receipt.receipt_no }}</span>
                    <span><strong>Date :</strong> {{ receipt.printed_at }}</span>
                </div>

                <div class="mt-4 grid gap-4 border-b border-slate-200 pb-4 text-sm sm:grid-cols-2">
                    <div class="space-y-1">
                        <p><span class="text-slate-500">Student Name :</span> <strong class="uppercase">{{ receipt.student.name }}</strong></p>
                        <p><span class="text-slate-500">Father Name :</span> <strong class="uppercase">{{ receipt.student.father || '—' }}</strong></p>
                        <p><span class="text-slate-500">Mother Name :</span> <strong class="uppercase">{{ receipt.student.mother || '—' }}</strong></p>
                    </div>
                    <div class="space-y-1 sm:text-right">
                        <p><span class="text-slate-500">Adm. No. :</span> <strong>{{ receipt.student.admission_no }}</strong></p>
                        <p><span class="text-slate-500">Branch :</span> <strong>{{ receipt.student.branch || '—' }}</strong></p>
                        <p><span class="text-slate-500">Class :</span> <strong>{{ receipt.student.class || '—' }}<span v-if="receipt.student.section"> / {{ receipt.student.section }}</span></strong></p>
                        <p><span class="text-slate-500">Roll No. :</span> <strong>{{ receipt.student.roll_no || '—' }}</strong></p>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="rounded bg-[#e8dcc8] px-3 py-1 text-center text-xs font-bold uppercase tracking-wide">Outstanding dues</div>
                    <div class="border border-t-0 border-slate-300 px-2 py-1.5 text-sm text-slate-800">
                        <p v-if="!compactDueGroups.length" class="px-1 py-2 italic text-slate-400">No outstanding dues</p>
                        <div v-else class="flex flex-wrap gap-x-5 gap-y-1 text-[13px]">
                            <div v-for="(g, i) in compactDueGroups" :key="i" class="whitespace-nowrap">
                                <span class="mr-1.5 font-bold text-slate-900">{{ g.month }}</span>
                                <template v-for="(item, j) in g.items" :key="j">
                                    <span v-if="j" class="px-1 text-slate-300">|</span>
                                    <span class="text-slate-600">{{ item.name }}</span>
                                    <span class="pl-1 font-semibold text-slate-900">₹{{ item.amount }}</span>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-1.5 flex justify-end rounded bg-rose-700 px-3 py-1.5 text-sm font-bold text-white">
                        TOTAL DUE ₹{{ money(receipt.remaining_due) }}
                    </div>
                </div>

                <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <p><strong>IN WORDS:</strong> {{ amountInWords(receipt.remaining_due) }}</p>
                        <p><strong>PAYMENT METHOD:</strong> {{ receipt.payment_method }}</p>
                        <p><strong>DUE MONTHS:</strong> {{ (receipt.due_months || []).join(', ') || '—' }}</p>
                        <p><strong>PAID MONTHS:</strong> {{ receipt.paid_months?.length ? receipt.paid_months.join(', ') : '—' }}</p>
                        <p><strong>DESCRIPTION:</strong> {{ receipt.description }}</p>
                    </div>
                    <div class="space-y-1 sm:text-right">
                        <p>Amount Due: <strong>₹{{ money(receipt.amount_due) }}</strong></p>
                        <p>Total Paid: <strong>₹{{ money(receipt.total_paid) }}</strong></p>
                        <p class="inline-block rounded bg-[#e8dcc8] px-3 py-1 font-bold">Remaining Due: ₹{{ money(receipt.remaining_due) }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="rounded bg-[#e8dcc8] px-3 py-1 text-center text-xs font-bold uppercase tracking-wide">Payment History</div>
                    <div class="border border-t-0 border-slate-200 px-2 py-1.5 text-sm text-slate-700">
                        <p v-if="!compactPayments.length" class="px-1 py-2 italic text-slate-400">*No payment recorded yet*</p>
                        <div v-else class="flex flex-wrap gap-x-5 gap-y-1 text-[13px]">
                            <div v-for="(p, i) in compactPayments" :key="i" class="whitespace-nowrap">
                                <span class="font-bold text-slate-900">{{ p.date }}</span>
                                <span class="px-1.5 text-slate-300">|</span>
                                <span class="text-slate-600">{{ p.mode }}</span>
                                <span class="px-1.5 text-slate-300">|</span>
                                <span class="font-semibold text-slate-900">₹{{ money(p.amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-end justify-between gap-4 text-xs text-slate-500">
                    <p class="max-w-md italic">This is a computer generated receipt. No signature required. Please retain this document for your records. For queries, contact the school office.</p>
                    <p class="shrink-0 font-semibold uppercase tracking-wide text-slate-700">Authorized Signatory</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import client from '../../api/client';
import { downloadPdf } from '../../utils/documentPdf';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const receipt = ref(null);
const busy = ref(false);
const pdfPath = ref('');
const pdfParams = ref({});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function shortFee(name) {
    return String(name || 'Fee')
        .replace(/\s*\(QUARTERLY\)\s*/gi, '')
        .replace(/\s+FEE$/i, '')
        .trim()
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase()) || 'Fee';
}

function titleMode(mode) {
    return String(mode || 'Other')
        .trim()
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase()) || 'Other';
}

function shortMonth(monthKey, duration) {
    if (/^\d{4}-\d{2}$/.test(String(monthKey || ''))) {
        const [y, m] = String(monthKey).split('-');
        const names = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        return `${names[Number(m) - 1] || '???'} ${y}`;
    }
    const match = String(duration || '').trim().match(/^([A-Za-z]+)\s+(\d{4})$/);
    if (match) return `${match[1].slice(0, 3).toUpperCase()} ${match[2]}`;
    return String(duration || 'OTHER').toUpperCase();
}

const compactDueGroups = computed(() => {
    const lines = receipt.value?.lines || [];
    // Hide ₹0 dues. Group by month only — never merge lines that share a fee name.
    const groups = new Map();
    for (const line of lines) {
        const amt = Object.prototype.hasOwnProperty.call(line, 'balance')
            ? Number(line.balance || 0)
            : Number(line.dues ?? line.due ?? line.amount ?? 0);
        if (amt <= 0.0001) continue;
        const key = line.month_key || line.duration || 'Other';
        if (!groups.has(key)) {
            groups.set(key, {
                sort: line.month_key || key,
                month: shortMonth(line.month_key, line.duration),
                items: [],
            });
        }
        groups.get(key).items.push({ name: shortFee(line.fee_particulars || line.name), amount: money(amt) });
    }
    return [...groups.values()]
        .filter((g) => g.items.length)
        .sort((a, b) => String(a.sort).localeCompare(String(b.sort)));
});

const compactPayments = computed(() => {
    const payments = receipt.value?.payment_history || [];
    const groups = new Map();
    for (const p of payments) {
        const key = `${String(p.date || '').toLowerCase()}|${String(p.payment_mode || '').toLowerCase()}`;
        if (!groups.has(key)) {
            groups.set(key, { date: p.date || '', mode: titleMode(p.payment_mode), amount: 0 });
        }
        groups.get(key).amount += Number(p.amount || 0);
    }
    return [...groups.values()].filter((g) => g.amount > 0.0001);
});

function amountInWords(n) {
    const num = Math.round(Number(n) || 0);
    if (num === 0) return 'ZERO ONLY';
    const ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
    const tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
    const chunk = (x) => {
        if (x === 0) return '';
        if (x < 20) return ones[x];
        if (x < 100) return `${tens[Math.floor(x / 10)]}${x % 10 ? ` ${ones[x % 10]}` : ''}`.trim();
        return `${ones[Math.floor(x / 100)]} HUNDRED${x % 100 ? ` ${chunk(x % 100)}` : ''}`.trim();
    };
    let words = '';
    const crore = Math.floor(num / 10000000);
    const lakh = Math.floor((num % 10000000) / 100000);
    const thousand = Math.floor((num % 100000) / 1000);
    const rest = num % 1000;
    if (crore) words += `${chunk(crore)} CRORE `;
    if (lakh) words += `${chunk(lakh)} LAKH `;
    if (thousand) words += `${chunk(thousand)} THOUSAND `;
    if (rest) words += `${chunk(rest)} `;
    return `${words.trim()} ONLY`;
}

async function printPdf() {
    if (!pdfPath.value) return;
    busy.value = true;
    try {
        await downloadPdf(pdfPath.value, 'fee-due-receipt.pdf', pdfParams.value);
    } finally {
        busy.value = false;
    }
}

function closeTab() {
    window.close();
    if (!window.closed) {
        window.history.length > 1 ? window.history.back() : (window.location.href = '/erp/dashboard/fee-management/fee-due');
    }
}

onMounted(async () => {
    const studentId = route.query.student_id;
    const type = (route.query.type || 'manual').toString();
    const scope = (route.query.scope || 'till_current').toString();
    if (!studentId) {
        error.value = 'Missing student.';
        loading.value = false;
        return;
    }
    pdfPath.value = type === 'automatic'
        ? `/fee-management/due/automatic/${studentId}/pdf`
        : `/fee-management/due/manual/${studentId}/pdf`;
    pdfParams.value = type === 'automatic' ? { scope } : {};
    try {
        if (route.query.autoprint === '1') {
            await downloadPdf(pdfPath.value, 'fee-due-receipt.pdf', pdfParams.value);
            loading.value = false;
            return;
        }
        const url = type === 'automatic'
            ? `/fee-management/due/automatic/${studentId}/receipt`
            : `/fee-management/due/manual/${studentId}/receipt`;
        const { data } = await client.get(url, { params: type === 'automatic' ? { scope } : {} });
        receipt.value = data;
        document.title = `Due Receipt — ${data.student?.name || studentId}`;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load due receipt.';
    } finally {
        loading.value = false;
    }
});
</script>
