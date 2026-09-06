<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Employee Master Import</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Upload a "SALARY DETAILS" + "STAFF DETAILS" workbook to create or update Teacher, Staff and Driver profiles.
                This only sets designation, status, subject/section, and bio-data — it does not touch attendance or pay.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[260px]">
                    <label class="form-label">File</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onFileSelected" />
                </div>
                <button type="button" class="btn-outline" :disabled="!selectedFile || scanning" @click="scan">
                    {{ scanning ? 'Scanning...' : 'Rescan' }}
                </button>
            </div>

            <!-- Preview -->
            <div v-if="preview" class="mt-5 space-y-3">
                <p v-if="!preview.staff_sheet_found" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                    No "STAFF DETAILS" sheet found — designation/status will still be applied, but bio-data (DOB, phone, address, etc.) will be left blank for every new profile.
                </p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-slate-700">
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ preview.total_rows }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Employee rows</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ preview.create_count }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">New profiles</p>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-center dark:border-sky-500/30 dark:bg-sky-500/10">
                        <p class="text-xl font-bold text-sky-700 dark:text-sky-400">{{ preview.update_count }}</p>
                        <p class="text-xs text-sky-600 dark:text-sky-400">Will update</p>
                    </div>
                    <div class="rounded-xl border p-3 text-center" :class="preview.failed_count ? 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 dark:border-slate-700'">
                        <p class="text-xl font-bold" :class="preview.failed_count ? 'text-rose-700 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100'">{{ preview.failed_count }}</p>
                        <p class="text-xs" :class="preview.failed_count ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">Need review</p>
                    </div>
                </div>

                <div v-if="preview.failed_rows.length" class="max-h-56 overflow-y-auto rounded-lg border border-rose-100 dark:border-rose-500/20">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Row</th>
                                <th class="px-3 py-2 font-semibold">EMP_CODE</th>
                                <th class="px-3 py-2 font-semibold">Name</th>
                                <th class="px-3 py-2 font-semibold">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-50 dark:divide-rose-500/10">
                            <tr v-for="fr in preview.failed_rows" :key="fr.row_number">
                                <td class="px-3 py-2 text-slate-500">{{ fr.row_number }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ fr.empl_code }}</td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.name }}</td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ preview.create_count + preview.update_count }} row{{ preview.create_count + preview.update_count === 1 ? '' : 's' }} will be written; {{ preview.failed_count }} skipped for review.
                    </p>
                    <button type="button" class="btn-primary" :disabled="importing || !(preview.create_count + preview.update_count)" @click="runImport">
                        {{ importing ? 'Importing...' : 'Confirm & Import' }}
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div v-if="importResults" class="mt-5 space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ importResults.created_count }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">Created</p>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-center dark:border-sky-500/30 dark:bg-sky-500/10">
                        <p class="text-xl font-bold text-sky-700 dark:text-sky-400">{{ importResults.updated_count }}</p>
                        <p class="text-xs text-sky-600 dark:text-sky-400">Updated</p>
                    </div>
                    <div class="rounded-xl border p-3 text-center" :class="importResults.failed_count ? 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-slate-200 dark:border-slate-700'">
                        <p class="text-xl font-bold" :class="importResults.failed_count ? 'text-rose-700 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100'">{{ importResults.failed_count }}</p>
                        <p class="text-xs" :class="importResults.failed_count ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">Failed</p>
                    </div>
                </div>

                <div v-if="importResults.new_employees.some((e) => e.incomplete)" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        {{ importResults.new_employees.filter((e) => e.incomplete).length }} new profile{{ importResults.new_employees.filter((e) => e.incomplete).length === 1 ? '' : 's' }} created without bio-data — please complete from People.
                    </p>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">No matching row was found for these in "STAFF DETAILS", so DOB/phone/address etc. are still blank.</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300">
                        <li v-for="e in importResults.new_employees.filter((e) => e.incomplete)" :key="`${e.type}-${e.id}`">
                            {{ e.name }} — {{ e.employee_id }} <span class="capitalize">({{ e.type }})</span>
                        </li>
                    </ul>
                </div>

                <div v-if="importResults.failed_rows.length" class="max-h-56 overflow-y-auto rounded-lg border border-rose-100 dark:border-rose-500/20">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Row</th>
                                <th class="px-3 py-2 font-semibold">EMP_CODE</th>
                                <th class="px-3 py-2 font-semibold">Name</th>
                                <th class="px-3 py-2 font-semibold">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-50 dark:divide-rose-500/10">
                            <tr v-for="fr in importResults.failed_rows" :key="fr.row_number">
                                <td class="px-3 py-2 text-slate-500">{{ fr.row_number }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ fr.empl_code }}</td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.name }}</td>
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.error_message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const fileInput = ref(null);
const selectedFile = ref(null);
const scanning = ref(false);
const preview = ref(null);
const importing = ref(false);
const importResults = ref(null);

function onFileSelected(event) {
    selectedFile.value = event.target.files?.[0] || null;
    preview.value = null;
    importResults.value = null;
    if (selectedFile.value) scan();
}

async function scan() {
    if (!selectedFile.value) return;
    scanning.value = true;
    preview.value = null;
    importResults.value = null;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const { data } = await client.post('/people/employee-master/preview', body);
        preview.value = data;
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not read this file.', 'error');
    } finally {
        scanning.value = false;
    }
}

async function runImport() {
    if (!selectedFile.value) return;
    importing.value = true;
    importResults.value = null;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const { data } = await client.post('/people/employee-master/import', body);
        importResults.value = data;
        preview.value = null;
        pushToast(`${data.created_count} created, ${data.updated_count} updated, ${data.failed_count} need review.`, 'success');
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Import failed.', 'error');
    } finally {
        importing.value = false;
    }
}
</script>
