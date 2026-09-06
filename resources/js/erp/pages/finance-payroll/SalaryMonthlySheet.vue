<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Salary Sheet</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload the whole salary workbook — every month's sheet is detected and processed in one go.</p>
        </div>

        <!-- Import -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Import salary workbook</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Unrelated sheets (Staff Details, Staff Detail Salary, ...) are skipped automatically. Every figure is recomputed from Present / CL / Basic Salary / Days in Month — nothing is copied blindly from the file.
            </p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[260px]">
                    <label class="form-label">File</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onFileSelected" />
                </div>
                <button type="button" class="btn-outline" :disabled="!selectedFile || scanning" @click="scan">
                    {{ scanning ? 'Scanning...' : 'Rescan' }}
                </button>
            </div>

            <!-- Preview / review -->
            <div v-if="sheets.length" class="mt-5 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Detected sheets — review before importing</p>

                <div v-for="s in sheets" :key="s.name" class="rounded-xl border p-3.5" :class="s.detected ? 'border-slate-200 dark:border-slate-700' : 'border-slate-100 bg-slate-50 opacity-60 dark:border-slate-800 dark:bg-slate-800/40'">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ s.name }}</p>
                            <p v-if="!s.detected" class="text-xs text-slate-400">Not a salary sheet — skipped.</p>
                            <p v-else class="text-xs text-slate-500 dark:text-slate-400">
                                {{ s.employee_count }} employee{{ s.employee_count === 1 ? '' : 's' }}
                                <span v-if="s.new_count" class="font-semibold text-amber-600 dark:text-amber-400">· {{ s.new_count }} new</span>
                                <span v-if="s.unresolved_count" class="font-semibold text-rose-600 dark:text-rose-400">· {{ s.unresolved_count }} unresolved (not in designation master)</span>
                            </p>
                        </div>

                        <div v-if="s.detected" class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <input v-model="s.include" type="checkbox" class="rounded" />
                                Include
                            </label>
                            <template v-if="s.period">
                                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30">
                                    {{ s.period_label }}
                                </span>
                            </template>
                            <template v-else>
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Period unclear —</span>
                            </template>
                            <select v-model="s.month" class="form-input !w-auto !py-1.5 !text-xs">
                                <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                            </select>
                            <select v-model.number="s.year" class="form-input !w-auto !py-1.5 !text-xs">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ includedCount }} sheet{{ includedCount === 1 ? '' : 's' }} will be imported.</p>
                    <button type="button" class="btn-primary" :disabled="!includedCount || importing" @click="runImport">
                        {{ importing ? 'Importing...' : 'Confirm & Import All' }}
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div v-if="importResults" class="mt-5 space-y-3">
                <div v-if="importResults.new_employees.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        {{ importResults.new_employees.length }} new profile{{ importResults.new_employees.length === 1 ? '' : 's' }} created — incomplete, please complete from People
                    </p>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">The sheet only has EMPL_CODE, Name and Basic Salary — class/department/license etc. still need to be filled in.</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300">
                        <li v-for="e in importResults.new_employees" :key="`${e.type}-${e.id}`">
                            {{ e.name }} — {{ e.employee_id }} <span class="capitalize">({{ e.type }})</span>
                        </li>
                    </ul>
                </div>

                <div v-for="r in importResults.results" :key="r.sheet" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ r.sheet }} <span class="font-normal text-slate-400">· {{ r.period }}</span></p>
                    <p v-if="r.error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ r.error }}</p>
                    <template v-else>
                        <div class="mt-1 flex flex-wrap gap-4 text-sm">
                            <span class="text-slate-600 dark:text-slate-300">Rows read: <b>{{ r.log.total_rows }}</b></span>
                            <span class="text-emerald-600 dark:text-emerald-400">Success: <b>{{ r.log.success_count }}</b></span>
                            <span v-if="r.log.failed_count" class="text-rose-600 dark:text-rose-400">Failed: <b>{{ r.log.failed_count }}</b></span>
                        </div>
                        <div v-if="r.failed_rows.length" class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-rose-100 dark:border-rose-500/20">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold">Row</th>
                                        <th class="px-3 py-2 font-semibold">EMPL_CODE</th>
                                        <th class="px-3 py-2 font-semibold">Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-rose-50 dark:divide-rose-500/10">
                                    <tr v-for="fr in r.failed_rows" :key="fr.row_number">
                                        <td class="px-3 py-2 text-slate-500">{{ fr.row_number }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ fr.empl_code }}</td>
                                        <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.error_message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Export -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Export salary sheet</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Downloads one period grouped Teaching Staff → Class IV/Office Staff → Drivers, with subtotal and grand total rows, matching the school's printable format.</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="form-label">Month</label>
                    <select v-model="exportMonth" class="form-input">
                        <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Year</label>
                    <select v-model.number="exportYear" class="form-input">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <button type="button" class="btn-outline" :disabled="exporting" @click="runExport">
                    {{ exporting ? 'Exporting...' : 'Export' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const currentYear = new Date().getFullYear();
const years = [currentYear - 1, currentYear, currentYear + 1];

const fileInput = ref(null);
const selectedFile = ref(null);
const scanning = ref(false);
const sheets = ref([]);
const importing = ref(false);
const importResults = ref(null);

const includedCount = computed(() => sheets.value.filter((s) => s.detected && s.include).length);

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
            return {
                ...s,
                include: s.detected,
                month: m,
                year: Number(y),
            };
        });
        if (!sheets.value.some((s) => s.detected)) {
            pushToast('No salary sheets recognized in this file.', 'error');
        }
    } catch {
        pushToast('Could not read this file.', 'error');
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
        const payload = sheets.value
            .filter((s) => s.detected && s.include)
            .map((s) => ({ name: s.name, period: `${s.year}-${s.month}` }));
        body.append('sheets', JSON.stringify(payload));

        const { data } = await client.post('/finance-payroll/salary-monthly/import', body);
        importResults.value = data;
        const totalSuccess = data.results.reduce((sum, r) => sum + (r.log?.success_count || 0), 0);
        const totalRows = data.results.reduce((sum, r) => sum + (r.log?.total_rows || 0), 0);
        pushToast(`Imported ${totalSuccess} of ${totalRows} rows across ${data.results.length} sheet(s).`, 'success');
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Import failed.', 'error');
    } finally {
        importing.value = false;
    }
}

const exportMonth = ref(String(new Date().getMonth() + 1).padStart(2, '0'));
const exportYear = ref(currentYear);
const exporting = ref(false);

async function runExport() {
    exporting.value = true;
    try {
        const period = `${exportYear.value}-${exportMonth.value}`;
        const response = await client.get('/finance-payroll/salary-monthly/export', {
            params: { period },
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `salary-sheet-${period}.xlsx`;
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
