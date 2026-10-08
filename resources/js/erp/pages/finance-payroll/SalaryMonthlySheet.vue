<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Salary Sheet</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload the whole salary workbook — every month's sheet is detected and processed in one go.</p>
        </div>

        <!-- Staff profiles (Teacher / Staff / Driver) — create these first so monthly sheets match by EMPL_CODE -->
        <StaffProfilesPanel />

        <!-- Import -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-2xl">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Monthly salary — Import</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Upload the salary workbook (e.g. <b>GAS SALARY 2026-27.xlsx</b>). Every month sheet — <b>AUG -26</b>, <b>AUG-26-2</b>, ... — is found automatically and saved as salary slips.
                        Pay is recalculated like the sheet (Basic ÷ Days × (Present + CL) − ADV), so broken Excel formulas don't carry over.
                        <b>STAFF DETAIL</b> (designations, LEFT) and <b>STAFF DETAILS</b> (bio-data) are saved to the people profiles too. Slips already marked <b>Paid</b> are never changed.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-outline" :disabled="downloadingTemplate" @click="downloadTemplate">
                        {{ downloadingTemplate ? 'Downloading...' : 'Download Template' }}
                    </button>
                    <button type="button" class="btn-primary" :disabled="!includedCount || importing" @click="runImport">
                        {{ importing ? 'Importing...' : includedCount ? `Import ${includedCount} sheet${includedCount === 1 ? '' : 's'}` : 'Import' }}
                    </button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[260px] flex-1">
                    <label class="form-label">Salary Excel file</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onFileSelected" />
                </div>
                <button type="button" class="btn-outline" :disabled="!selectedFile || scanning" @click="scan">
                    {{ scanning ? 'Scanning...' : 'Rescan' }}
                </button>
            </div>

            <!-- Preview / review -->
            <div v-if="sheets.length" class="mt-5 space-y-3">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-slate-700">
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ detectedSheets.length }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Month sheets found</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ totals.create }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">New slips</p>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-center dark:border-sky-500/30 dark:bg-sky-500/10">
                        <p class="text-xl font-bold text-sky-700 dark:text-sky-400">{{ totals.update }}</p>
                        <p class="text-xs text-sky-600 dark:text-sky-400">Will update</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-slate-700">
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ totals.paid }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Already paid — kept</p>
                    </div>
                </div>

                <!-- Save as paid -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 text-xs dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <label class="flex items-start gap-2">
                        <input v-model="markPaid" type="checkbox" class="mt-0.5 rounded" />
                        <span class="text-slate-700 dark:text-slate-200">
                            <b class="text-slate-800 dark:text-slate-100">Save these salaries as Paid</b> — the sheet's salaries have already been paid.
                            Untick to import them as Pending and pay later from Salary Slips.
                        </span>
                    </label>
                    <div v-if="markPaid" class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="form-label">Paid by</label>
                            <select v-model="payMode" class="form-input !py-1.5 !text-xs">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div v-if="payMode === 'Bank'">
                            <label class="form-label">Bank account</label>
                            <select v-model="payBank" class="form-input !py-1.5 !text-xs">
                                <option :value="null">{{ bankAccounts.length ? 'Select bank account' : 'No bank account added yet' }}</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}{{ b.account_number ? ` (A/c ••${String(b.account_number).slice(-4)})` : '' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Paid on</label>
                            <select v-model="paidOnMode" class="form-input !py-1.5 !text-xs">
                                <option value="month_end">Last day of each salary month</option>
                                <option value="date">One date for all…</option>
                            </select>
                            <input v-if="paidOnMode === 'date'" v-model="paidOn" type="date" class="form-input mt-1 !py-1.5 !text-xs" />
                        </div>
                    </div>
                    <p v-if="markPaid" class="mt-2 text-slate-500 dark:text-slate-400">
                        <template v-if="payMode === 'Bank'">Each salary is also debited from the chosen bank account (one Withdrawal per slip, CATEGORY "SALARY &lt;MONTH&gt;").</template>
                        Rows that already say how they were paid (SIGNATURE "PAID …" / PMNT MODE in an exported file) keep their own date and mode. Slips already Paid are not changed.
                    </p>
                </div>

                <!-- Profiles from the workbook -->
                <label v-if="profiles" class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-700">
                    <input v-model="updateProfiles" type="checkbox" class="mt-0.5 rounded" />
                    <span class="text-slate-600 dark:text-slate-300">
                        <b class="text-slate-800 dark:text-slate-100">Also update people profiles from this file</b> —
                        STAFF DETAIL: {{ profiles.designation_matched }}/{{ profiles.designation_rows }} designations matched<span v-if="profiles.left_count"> ({{ profiles.left_count }} marked LEFT → inactive)</span>;
                        STAFF DETAILS: {{ profiles.bio_matched }}/{{ profiles.bio_rows }} bio-data rows matched; present basic salary from the latest month.
                    </span>
                </label>

                <!-- People nobody knows: admin picks the type -->
                <div v-if="unknownPeople.length" class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-500/30 dark:bg-sky-500/10">
                    <p class="text-sm font-semibold text-sky-800 dark:text-sky-300">{{ unknownPeople.length }} new {{ unknownPeople.length === 1 ? 'person' : 'people' }} will be created automatically</p>
                    <p class="mt-1 text-xs text-sky-700 dark:text-sky-400">
                        These names aren't in People or STAFF DETAIL. Each is created with the type shown (guessed from who they're listed with in the sheet) and their full salary history —
                        inactive if they're not on the latest month. Change the type if it's wrong, or choose "Skip" to leave their rows out.
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div v-for="u in unknownPeople" :key="u.key" class="flex items-center justify-between gap-2 rounded-lg bg-white/70 px-3 py-2 dark:bg-slate-900/40">
                            <div class="min-w-0 text-xs">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ u.name }} <span class="font-mono font-normal text-slate-400">{{ (u.codes || [u.code]).join(' / ') }}</span></p>
                                <p class="truncate text-slate-500">{{ u.sheets.length }} sheet{{ u.sheets.length === 1 ? '' : 's' }} · last {{ periodLabel(u.last_period) }}</p>
                            </div>
                            <select v-model="unknownTypes[u.key]" class="form-input !w-auto !py-1 !text-xs">
                                <option value="teacher">Teacher</option>
                                <option value="staff">Staff</option>
                                <option value="driver">Driver</option>
                                <option value="skip">Skip</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="ambiguous.length" class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                    <p v-for="a in ambiguous" :key="a">{{ a }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sheets — untick any you don't want, fix a month if it was read wrong</p>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="text-primary-600 hover:underline dark:text-primary-400" @click="setAll(true)">Select all</button>
                        <button type="button" class="text-primary-600 hover:underline dark:text-primary-400" @click="setAll(false)">Select none</button>
                    </div>
                </div>

                <div class="max-h-[28rem] overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full min-w-[720px] text-left text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Import</th>
                                <th class="px-3 py-2 font-semibold">Sheet</th>
                                <th class="px-3 py-2 font-semibold">Month</th>
                                <th class="px-3 py-2 font-semibold">People</th>
                                <th class="px-3 py-2 font-semibold">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="s in sheets" :key="s.name" :class="s.detected ? '' : 'opacity-50'">
                                <td class="px-3 py-1.5"><input v-if="s.detected" v-model="s.include" type="checkbox" class="rounded" /></td>
                                <td class="px-3 py-1.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                                <td class="px-3 py-1.5">
                                    <span v-if="!s.detected" class="text-slate-400">{{ s.kind === 'designations' ? 'Designations → profiles' : s.kind === 'bio' ? 'Bio-data → profiles' : 'Not a salary sheet — skipped' }}</span>
                                    <div v-else class="flex items-center gap-1">
                                        <select v-model="s.month" class="form-input !w-auto !py-1 !text-xs" :class="s.period ? '' : '!border-amber-400'">
                                            <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m.slice(0, 3) }}</option>
                                        </select>
                                        <select v-model.number="s.year" class="form-input !w-auto !py-1 !text-xs">
                                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                        </select>
                                        <span class="text-slate-400">{{ s.days_in_month || '?' }} days</span>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-slate-600 dark:text-slate-300">{{ s.detected ? s.employee_count : '' }}</td>
                                <td class="px-3 py-1.5">
                                    <template v-if="s.detected">
                                        <span v-if="s.create_count" class="text-emerald-600 dark:text-emerald-400">+{{ s.create_count }} new</span>
                                        <span v-if="s.update_count" class="ml-1.5 text-sky-600 dark:text-sky-400">{{ s.update_count }} update</span>
                                        <span v-if="s.paid_count" class="ml-1.5 text-slate-500">{{ s.paid_count }} paid (kept)</span>
                                        <span v-if="s.unresolved_count" class="ml-1.5 text-amber-600 dark:text-amber-400" :title="(s.unresolved || []).join('\n')">{{ s.unresolved_count }} need review</span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Results -->
            <div v-if="importResults" class="mt-5 space-y-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                        Imported {{ resultTotals.created }} new slip{{ resultTotals.created === 1 ? '' : 's' }}, updated {{ resultTotals.updated }}<span v-if="resultTotals.marked_paid"> ({{ resultTotals.marked_paid }} saved as Paid)</span><span v-if="resultTotals.skipped_paid">, kept {{ resultTotals.skipped_paid }} already-paid</span><span v-if="resultTotals.failed">, {{ resultTotals.failed }} need review</span>
                        across {{ importResults.results.length }} sheet{{ importResults.results.length === 1 ? '' : 's' }}.
                    </p>
                    <p v-if="importResults.profiles" class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                        Profiles: {{ importResults.profiles.designations }} designations, {{ importResults.profiles.bio }} bio-data, {{ importResults.profiles.salaries }} present salaries updated<span v-if="importResults.profiles.marked_left">, {{ importResults.profiles.marked_left }} marked inactive (LEFT)</span>.
                        Each sheet can be undone below in <b>Salary history</b>.
                    </p>
                </div>

                <div v-if="importResults.new_employees.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        {{ importResults.new_employees.length }} new profile{{ importResults.new_employees.length === 1 ? '' : 's' }} created — complete them from People
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                        <span v-for="(e, i) in importResults.new_employees" :key="`${e.type}-${e.id}`">{{ i ? ', ' : '' }}{{ e.name }} <span class="text-amber-600">({{ e.employee_id }}, {{ e.type }})</span></span>
                    </p>
                </div>

                <template v-for="r in importResults.results" :key="r.sheet">
                    <div v-if="r.error || r.failed_rows?.length" class="rounded-xl border border-rose-200 p-3 dark:border-rose-500/30">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ r.sheet }} <span class="font-normal text-slate-400">· {{ periodLabel(r.period) }}</span></p>
                        <p v-if="r.error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ r.error }}</p>
                        <ul v-else class="mt-1 space-y-0.5 text-xs text-rose-700 dark:text-rose-300">
                            <li v-for="fr in r.failed_rows" :key="fr.row_number">Row {{ fr.row_number }} · {{ fr.empl_code }} {{ fr.name }} — {{ fr.error_message }}</li>
                        </ul>
                    </div>
                </template>
            </div>
        </div>

        <!-- Export -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Monthly salary — Export</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Same layout as the school's Excel: one sheet per salary sheet (e.g. AUG -26 and AUG-26-2), school name, "SALARY PAYMENT DETAILS AUGUST-2026", days in month,
                the same blocks with subtotals, and live formulas. Edit it in Excel and import it back above.
            </p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="form-label">Export</label>
                    <select v-model="exportScope" class="form-input">
                        <option value="month">One month</option>
                        <option value="session">Whole session (April – March)</option>
                    </select>
                </div>
                <div v-if="exportScope === 'month'">
                    <label class="form-label">Month</label>
                    <select v-model="exportMonth" class="form-input">
                        <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ exportScope === 'month' ? 'Year' : 'Session' }}</label>
                    <select v-model.number="exportYear" class="form-input">
                        <option v-for="y in years" :key="y" :value="y">{{ exportScope === 'month' ? y : `${y}-${String(y + 1).slice(2)}` }}</option>
                    </select>
                </div>
                <button type="button" class="btn-outline" :disabled="exporting" @click="runExport">
                    {{ exporting ? 'Exporting...' : 'Export' }}
                </button>
            </div>
        </div>

        <!-- History -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <SalaryHistoryPanel :refresh-key="historyKey" />
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import client from '../../api/client';
import SalaryHistoryPanel from '../../components/finance/SalaryHistoryPanel.vue';
import StaffProfilesPanel from '../../components/people/StaffProfilesPanel.vue';
import { downloadImportTemplate } from '../../utils/downloadExport';
import { pushToast } from '../../utils/toast';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const currentYear = new Date().getFullYear();
// Salary workbooks carry years of history (APR-24 ... AUG-26) — offer from 2020 onwards.
const years = Array.from({ length: currentYear + 2 - 2020 }, (_, i) => 2020 + i);

const fileInput = ref(null);
const selectedFile = ref(null);
const scanning = ref(false);
const sheets = ref([]);
const profiles = ref(null);
const unknownPeople = ref([]);
const unknownTypes = reactive({});
const ambiguous = ref([]);
const updateProfiles = ref(true);
const importing = ref(false);
const importResults = ref(null);
const downloadingTemplate = ref(false);
const historyKey = ref(0);

const detectedSheets = computed(() => sheets.value.filter((s) => s.detected));
const includedCount = computed(() => detectedSheets.value.filter((s) => s.include).length);
const totals = computed(() => detectedSheets.value.filter((s) => s.include).reduce(
    (t, s) => ({ create: t.create + (s.create_count || 0), update: t.update + (s.update_count || 0), paid: t.paid + (s.paid_count || 0) }),
    { create: 0, update: 0, paid: 0 },
));
const resultTotals = computed(() => (importResults.value?.results || []).reduce(
    (t, r) => ({
        created: t.created + (r.stats?.created || 0),
        updated: t.updated + (r.stats?.updated || 0),
        skipped_paid: t.skipped_paid + (r.stats?.skipped_paid || 0),
        failed: t.failed + (r.stats?.failed || 0),
        marked_paid: t.marked_paid + (r.stats?.marked_paid || 0),
    }),
    { created: 0, updated: 0, skipped_paid: 0, failed: 0, marked_paid: 0 },
));

// "Save as Paid" options for the import.
const markPaid = ref(true);
const payMode = ref('Cash');
const payBank = ref(null);
const paidOnMode = ref('month_end');
const paidOn = ref(new Date().toISOString().slice(0, 10));
const bankAccounts = ref([]);

async function loadBanks() {
    try {
        const { data } = await client.get('/finance-payroll/bank-accounts');
        bankAccounts.value = Array.isArray(data) ? data : [];
        if (bankAccounts.value.length === 1 && !payBank.value) payBank.value = bankAccounts.value[0].id;
    } catch {
        bankAccounts.value = [];
    }
}
loadBanks();

function periodLabel(period) {
    if (!period) return '—';
    const [y, m] = String(period).split('-');
    return `${MONTHS[Number(m) - 1] || m} ${y}`;
}

function setAll(value) {
    detectedSheets.value.forEach((s) => { s.include = value; });
}

async function downloadTemplate() {
    downloadingTemplate.value = true;
    try {
        await downloadImportTemplate('salary-monthly');
        pushToast('Template downloaded.', 'success');
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not download template.', 'error');
    } finally {
        downloadingTemplate.value = false;
    }
}

function onFileSelected(event) {
    selectedFile.value = event.target.files?.[0] || null;
    sheets.value = [];
    importResults.value = null;
    if (selectedFile.value) scan();
}

async function scan() {
    if (!selectedFile.value) return;
    scanning.value = true;
    sheets.value = [];
    importResults.value = null;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const { data } = await client.post('/finance-payroll/salary-monthly/preview', body);
        sheets.value = (data.sheets || []).map((s) => {
            const [y, m] = (s.period || `${currentYear}-01`).split('-');
            return { ...s, include: !!(s.detected && s.period), month: m, year: Number(y) };
        });
        profiles.value = data.profiles || null;
        unknownPeople.value = data.unknown_people || [];
        ambiguous.value = data.ambiguous || [];
        Object.keys(unknownTypes).forEach((k) => delete unknownTypes[k]);
        unknownPeople.value.forEach((u) => { unknownTypes[u.key] = u.suggested_type || 'staff'; });
        if (!detectedSheets.value.length) {
            pushToast('No salary sheets recognized in this file.', 'error');
        }
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not read this file.', 'error');
    } finally {
        scanning.value = false;
    }
}

async function runImport() {
    if (!includedCount.value) return;
    importing.value = true;
    importResults.value = null;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const payload = detectedSheets.value
            .filter((s) => s.include)
            .map((s) => ({ name: s.name, period: `${s.year}-${s.month}` }));
        body.append('sheets', JSON.stringify(payload));
        body.append('update_profiles', updateProfiles.value ? '1' : '0');
        body.append('mark_paid', markPaid.value ? '1' : '0');
        if (markPaid.value) {
            body.append('payment_mode', payMode.value);
            if (payMode.value === 'Bank' && payBank.value) body.append('bank_account_id', String(payBank.value));
            if (paidOnMode.value === 'date' && paidOn.value) body.append('paid_on', paidOn.value);
        }
        body.append('unknown_types', JSON.stringify({ ...unknownTypes }));

        const { data } = await client.post('/finance-payroll/salary-monthly/import', body);
        importResults.value = data;
        sheets.value = [];
        historyKey.value += 1;
        const t = resultTotals.value;
        pushToast(`Imported ${t.created} new and updated ${t.updated} salary slips across ${data.results.length} sheet(s).`, 'success');
    } catch (err) {
        pushToast(err?.response?.status === 403 ? 'You do not have permission to import salary.' : (err?.response?.data?.message || 'Import failed.'), 'error');
    } finally {
        importing.value = false;
    }
}

const exportScope = ref('month');
const exportMonth = ref(String(new Date().getMonth() + 1).padStart(2, '0'));
const exportYear = ref(currentYear);
const exporting = ref(false);

async function runExport() {
    exporting.value = true;
    try {
        const params = exportScope.value === 'month'
            ? { period: `${exportYear.value}-${exportMonth.value}` }
            : { session: exportYear.value };
        const response = await client.get('/finance-payroll/salary-monthly/export', { params, responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = exportScope.value === 'month'
            ? `salary-sheet-${params.period}.xlsx`
            : `salary-sheet-${exportYear.value}-${String(exportYear.value + 1).slice(2)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        pushToast('No salary data found for this period.', 'error');
    } finally {
        exporting.value = false;
    }
}
</script>
