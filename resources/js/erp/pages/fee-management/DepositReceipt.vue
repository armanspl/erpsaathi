<template>
    <div class="min-h-screen bg-slate-100 px-4 py-6 print:bg-white print:p-0">
        <div class="mx-auto max-w-3xl">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 print:hidden">
                <p class="text-sm text-slate-500">Deposit Receipt — ready to print</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-primary" :disabled="loading || !receipt || busy" @click="printPdf">{{ busy === 'print' ? 'Opening...' : 'Print PDF' }}</button>
                    <button type="button" class="btn-outline" :disabled="loading || !receipt || busy" @click="downloadTemplatePdf">{{ busy === 'download' ? 'Downloading...' : 'Download PDF' }}</button>
                    <button type="button" class="btn-outline" @click="closeTab">Close</button>
                </div>
            </div>

            <div v-if="loading" class="rounded-xl bg-white px-6 py-16 text-center text-sm text-slate-400 shadow">Loading receipt...</div>
            <div v-else-if="error" class="rounded-xl bg-white px-6 py-16 text-center text-sm text-rose-600 shadow">{{ error }}</div>

            <div
                v-else-if="receipt"
                id="deposit-receipt-print"
                class="rounded-sm border border-slate-300 bg-white px-8 py-6 text-slate-800 shadow-xl print:border-0 print:shadow-none"
            >
                <div class="text-center">
                    <div class="grid grid-cols-[3.5rem_1fr_3.5rem] items-center gap-2">
                        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-xl">
                            <img v-if="receipt.school.logo_path" :src="receipt.school.logo_path" alt="Logo" class="h-full w-full object-contain" />
                            <span v-else>🏫</span>
                        </div>
                        <div class="justify-self-center rounded bg-emerald-700 px-4 py-1 text-sm font-bold uppercase tracking-wide text-white">
                            Fee Receipt
                        </div>
                        <div class="justify-self-end flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-xl">
                            <img v-if="receipt.school.logo_path" :src="receipt.school.logo_path" alt="Logo" class="h-full w-full object-contain" />
                            <span v-else>🏫</span>
                        </div>
                    </div>
                    <h1 class="mt-1 text-lg font-bold leading-tight text-blue-700">{{ receipt.school.name }}</h1>
                    <p v-if="receipt.school.address" class="mt-0.5 text-xs leading-snug text-slate-500">Address : {{ receipt.school.address }}</p>
                    <p v-if="receipt.school.phone" class="mt-0.5 text-xs font-semibold leading-snug text-slate-600">Contact No. {{ receipt.school.phone }}</p>
                    <p class="mt-0.5 text-sm font-semibold leading-tight text-slate-700">{{ receipt.session.name }}</p>
                    <p class="text-sm leading-tight text-slate-600">{{ receipt.months_bar }}</p>
                </div>

                <div class="mt-3 flex flex-wrap items-start justify-between gap-3 border-y border-slate-300 py-3 text-sm">
                    <div class="min-w-[200px] space-y-1">
                        <p><strong>Receipt No :</strong> {{ receipt.receipt_no }}</p>
                        <p><span class="text-slate-500">Student Name :</span> <strong class="uppercase">{{ receipt.student.name }}</strong></p>
                        <p><span class="text-slate-500">Father Name :</span> <strong class="uppercase">{{ receipt.student.father || '—' }}</strong></p>
                        <p><span class="text-slate-500">Mother Name :</span> <strong class="uppercase">{{ receipt.student.mother || '—' }}</strong></p>
                    </div>
                    <div class="min-w-[180px] space-y-1 sm:text-right">
                        <p><strong>Date :</strong> {{ receipt.printed_at }}</p>
                        <p><span class="text-slate-500">Adm. No. :</span> <strong>{{ receipt.student.admission_no }}</strong></p>
                        <p><span class="text-slate-500">Branch :</span> <strong>{{ receipt.student.branch || '—' }}</strong></p>
                        <p><span class="text-slate-500">Class :</span> <strong>{{ receipt.student.class || '—' }}<span v-if="receipt.student.section"> / {{ receipt.student.section }}</span></strong></p>
                        <p><span class="text-slate-500">Roll No. :</span> <strong>{{ receipt.student.roll_no || '—' }}</strong></p>
                    </div>
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden border border-slate-400 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                        <img v-if="receipt.student.photo_url" :src="receipt.student.photo_url" alt="Photo" class="h-full w-full object-cover" />
                        <span v-else>Photo</span>
                    </div>
                </div>

                <table class="mt-4 w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="bg-[#e8dcc8]">
                            <th class="border border-slate-400 px-2 py-2 text-xs font-semibold uppercase">S. No.</th>
                            <th class="border border-slate-400 px-2 py-2 text-xs font-semibold uppercase">Fee Particulars</th>
                            <th class="border border-slate-400 px-2 py-2 text-xs font-semibold uppercase">Duration</th>
                            <th class="border border-slate-400 px-2 py-2 text-right text-xs font-semibold uppercase">Payable</th>
                            <th class="border border-slate-400 px-2 py-2 text-right text-xs font-semibold uppercase">Paid</th>
                            <th class="border border-slate-400 px-2 py-2 text-right text-xs font-semibold uppercase">Dues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in receipt.lines" :key="line.sno">
                            <td class="border border-slate-300 px-2 py-2">{{ line.sno }}</td>
                            <td class="border border-slate-300 px-2 py-2 uppercase">{{ line.fee_particulars }}</td>
                            <td class="border border-slate-300 px-2 py-2">{{ line.duration }}</td>
                            <td class="border border-slate-300 px-2 py-2 text-right">₹{{ money(line.payable) }}</td>
                            <td class="border border-slate-300 px-2 py-2 text-right">₹{{ money(line.paid) }}</td>
                            <td class="border border-slate-300 px-2 py-2 text-right">₹{{ money(line.dues) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <p><strong>IN WORDS:</strong> {{ amountInWords(receipt.amount_paid) }}</p>
                        <p><strong>PAY MODE:</strong> {{ receipt.payment_mode }}</p>
                        <p><strong>Paid At:</strong> {{ receipt.paid_at || 'SCHOOL' }}</p>
                    </div>
                    <div class="space-y-0 border border-slate-300 text-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-1.5">
                            <span>Amt. Payable</span>
                            <strong>₹{{ money(receipt.amount_payable) }}</strong>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-1.5">
                            <span>Counter Discount</span>
                            <strong>₹{{ money(receipt.counter_discount) }}</strong>
                        </div>
                        <div class="flex items-center justify-between bg-[#e8dcc8] px-3 py-1.5 font-bold">
                            <span>Amt Paid</span>
                            <span>₹{{ money(receipt.amount_paid) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-end justify-between gap-4 text-xs text-slate-500">
                    <p class="max-w-md italic">This is a computer generated receipt. No signature required. Please retain this document for your records. For queries, contact the school office.</p>
                    <div class="shrink-0 text-center">
                        <div class="mb-1 h-8 border-b border-slate-400" />
                        <p class="font-semibold uppercase tracking-wide text-slate-700">Authorized Signatory</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import client from '../../api/client';
import { downloadPdf } from '../../utils/documentPdf';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const receipt = ref(null);
const busy = ref('');
const paymentId = ref(null);

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

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
    if (!paymentId.value) return;
    busy.value = 'print';
    try {
        await downloadPdf(`/fee-management/payments/${paymentId.value}/pdf`, `fee-receipt-${paymentId.value}.pdf`);
    } finally {
        busy.value = '';
    }
}

function closeTab() {
    window.close();
    if (!window.closed) {
        window.history.length > 1
            ? window.history.back()
            : (window.location.href = '/erp/dashboard/fee-management/fee-receipt');
    }
}

async function downloadTemplatePdf() {
    if (!paymentId.value) return;
    busy.value = 'download';
    try {
        const name = receipt.value?.receipt_no || 'fee-receipt';
        await downloadPdf(`/fee-management/payments/${paymentId.value}/pdf`, `${name}.pdf`);
    } finally {
        busy.value = '';
    }
}

onMounted(async () => {
    paymentId.value = route.query.payment_id;
    if (!paymentId.value) {
        error.value = 'Missing payment.';
        loading.value = false;
        return;
    }
    try {
        // Prefer Template Builder PDF when print/download is requested.
        if (route.query.download === '1' || route.query.autoprint === '1') {
            await downloadPdf(`/fee-management/payments/${paymentId.value}/pdf`, `fee-receipt-${paymentId.value}.pdf`);
            if (route.query.autoprint === '1') {
                // Tab already opened by downloadPdf for viewing/printing.
            }
            loading.value = false;
            return;
        }
        const { data } = await client.get(`/fee-management/payments/${paymentId.value}/receipt`);
        receipt.value = data;
        document.title = `Deposit Receipt — ${data.receipt_no || paymentId.value}`;
        await nextTick();
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load deposit receipt.';
    } finally {
        loading.value = false;
    }
});
</script>
