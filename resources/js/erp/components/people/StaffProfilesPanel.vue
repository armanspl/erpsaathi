<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Staff Profiles — Import / Export</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Upload the <b>SALARY DETAILS</b> workbook. Teacher, Staff and Driver profiles are created automatically from POST
                    (Asst. Teacher → Teacher, Driver → Driver, everything else → Staff). Bio-data comes from <b>STAFF DETAILS</b> and the current basic salary from <b>STAFF 2026</b>.
                    Optional <b>PHONE</b> / <b>EMAIL</b> columns fill phone number and Gmail. Existing people are updated, never duplicated.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline" :disabled="downloadingTemplate" @click="downloadTemplate">
                    {{ downloadingTemplate ? 'Downloading...' : 'Download Template' }}
                </button>
                <button type="button" class="btn-primary" :disabled="!selectedFile || importing || !writableCount" @click="runImport">
                    {{ importing ? 'Importing...' : writableCount ? `Import ${writableCount} profile${writableCount === 1 ? '' : 's'}` : 'Import' }}
                </button>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[260px] flex-1">
                <label class="form-label">Staff profile Excel file</label>
                <input ref="fileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onFileSelected" />
            </div>
            <button type="button" class="btn-outline" :disabled="!selectedFile || scanning" @click="scan">
                {{ scanning ? 'Scanning...' : 'Rescan' }}
            </button>
        </div>

        <!-- Preview -->
        <div v-if="preview" class="mt-5 space-y-3">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-slate-700">
                    <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ preview.total_rows }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Employees in file</p>
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

            <div class="flex flex-wrap gap-2 text-xs">
                <span v-for="t in TYPES" :key="t.key" class="rounded-md bg-slate-100 px-2.5 py-1 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ t.plural }}: <b>{{ preview.by_type?.[t.key]?.create || 0 }}</b> new · <b>{{ preview.by_type?.[t.key]?.update || 0 }}</b> update
                </span>
                <span class="rounded-md px-2.5 py-1" :class="preview.staff_sheet_found ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                    STAFF DETAILS: {{ preview.staff_sheet_found ? `${preview.bio_matched_count}/${preview.bio_row_count} matched` : 'not found — no DOB/address/phone' }}
                </span>
                <span class="rounded-md px-2.5 py-1" :class="preview.current_sheet ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                    {{ preview.current_sheet ? `${preview.current_sheet}: ${preview.current_matched_count}/${preview.current_row_count} salaries matched` : 'No "STAFF <year>" salary sheet' }}
                </span>
            </div>

            <div v-if="preview.unmatched_side_rows?.length" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Not in SALARY DETAILS, so skipped:
                <span v-for="(u, i) in preview.unmatched_side_rows" :key="`${u.sheet}-${u.row_number}`">{{ i ? ', ' : ' ' }}{{ u.name }} ({{ u.code || 'no code' }}, {{ u.sheet }} row {{ u.row_number }})</span>.
                Add them to SALARY DETAILS with a POST to import them.
            </div>

            <div v-if="preview.failed_rows?.length" class="max-h-56 overflow-y-auto rounded-lg border border-rose-100 dark:border-rose-500/20">
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

            <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="showRows = !showRows">
                {{ showRows ? 'Hide' : 'Review' }} all {{ preview.rows?.length || 0 }} rows
            </button>
            <div v-if="showRows" class="max-h-96 overflow-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead class="sticky top-0 bg-slate-50 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Row</th>
                            <th class="px-3 py-2 font-semibold">EMP_CODE</th>
                            <th class="px-3 py-2 font-semibold">Name</th>
                            <th class="px-3 py-2 font-semibold">Post</th>
                            <th class="px-3 py-2 font-semibold">Saved as</th>
                            <th class="px-3 py-2 font-semibold">Status</th>
                            <th class="px-3 py-2 text-right font-semibold">Basic salary</th>
                            <th class="px-3 py-2 font-semibold">Phone</th>
                            <th class="px-3 py-2 font-semibold">Email</th>
                            <th class="px-3 py-2 font-semibold">Bio-data</th>
                            <th class="px-3 py-2 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in preview.rows" :key="r.row_number">
                            <td class="px-3 py-1.5 text-slate-400">{{ r.row_number }}</td>
                            <td class="px-3 py-1.5 font-mono text-slate-500">{{ r.empl_code }}</td>
                            <td class="px-3 py-1.5 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-3 py-1.5 text-slate-500">{{ r.post }}</td>
                            <td class="px-3 py-1.5 capitalize text-slate-600 dark:text-slate-300">{{ r.type }}</td>
                            <td class="px-3 py-1.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(r.status)">{{ r.status }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right text-slate-700 dark:text-slate-200">{{ r.salary ? Number(r.salary).toLocaleString('en-IN') : '—' }}</td>
                            <td class="px-3 py-1.5 text-slate-500">{{ r.phone || '—' }}</td>
                            <td class="px-3 py-1.5 text-slate-500">{{ r.email || '—' }}</td>
                            <td class="px-3 py-1.5" :class="r.has_bio ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">{{ r.has_bio ? 'Yes' : '—' }}</td>
                            <td class="px-3 py-1.5 font-medium" :class="OUTCOME_CLASS[r.outcome]">{{ OUTCOME_LABEL[r.outcome] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Results -->
        <div v-if="results" class="mt-5 space-y-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                    Import done — {{ results.created_count }} created, {{ results.updated_count }} updated<span v-if="results.failed_count">, {{ results.failed_count }} need review</span>.
                </p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                    Open
                    <RouterLink to="/people/teachers" class="font-semibold underline">Teachers</RouterLink>,
                    <RouterLink to="/people/staff" class="font-semibold underline">Staff</RouterLink> or
                    <RouterLink to="/people/drivers" class="font-semibold underline">Drivers</RouterLink>
                    and click <b>View</b> to see each full profile.
                </p>
            </div>

            <div v-if="incompleteNew.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    {{ incompleteNew.length }} new profile{{ incompleteNew.length === 1 ? '' : 's' }} without STAFF DETAILS bio-data (DOB, address, phone)
                </p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Add their rows to STAFF DETAILS and import again, or fill them in from People.</p>
                <p class="mt-2 text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                    <span v-for="(e, i) in incompleteNew" :key="`${e.type}-${e.id}`">{{ i ? ', ' : '' }}{{ e.name }} <span class="text-amber-600">({{ e.employee_id }})</span></span>
                </p>
            </div>

            <div v-if="results.failed_rows?.length" class="max-h-56 overflow-y-auto rounded-lg border border-rose-100 dark:border-rose-500/20">
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
                        <tr v-for="fr in results.failed_rows" :key="fr.row_number">
                            <td class="px-3 py-2 text-slate-500">{{ fr.row_number }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ fr.empl_code }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.name }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ fr.error_message }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export -->
        <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Export staff profiles</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Downloads the same SALARY DETAILS + STAFF DETAILS layout (with phone and Gmail). Edit it in Excel and upload it above to update everyone in one go.
            </p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="form-label">Who</label>
                    <select v-model="exportType" class="form-input">
                        <option value="all">Teachers, Staff &amp; Drivers</option>
                        <option v-for="t in TYPES" :key="t.key" :value="t.key">{{ t.plural }} only</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="exportStatus" class="form-input">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="button" class="btn-outline" :disabled="exporting" @click="runExport">
                    {{ exporting ? 'Exporting...' : 'Export profiles' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import client from '../../api/client';
import { invalidatePeopleLookups } from '../../api/people';
import { statusBadgeClass } from '../../utils/colors';
import { downloadImportTemplate, downloadStaffProfiles } from '../../utils/downloadExport';
import { pushToast } from '../../utils/toast';

const TYPES = [
    { key: 'teacher', plural: 'Teachers' },
    { key: 'staff', plural: 'Staff' },
    { key: 'driver', plural: 'Drivers' },
];
const OUTCOME_LABEL = { create: 'New', update: 'Update', failed: 'Review' };
const OUTCOME_CLASS = {
    create: 'text-emerald-600 dark:text-emerald-400',
    update: 'text-sky-600 dark:text-sky-400',
    failed: 'text-rose-600 dark:text-rose-400',
};

const fileInput = ref(null);
const selectedFile = ref(null);
const scanning = ref(false);
const preview = ref(null);
const showRows = ref(false);
const importing = ref(false);
const results = ref(null);
const downloadingTemplate = ref(false);

const writableCount = computed(() => (preview.value ? preview.value.create_count + preview.value.update_count : 0));
const incompleteNew = computed(() => (results.value?.new_employees || []).filter((e) => e.incomplete));

function errorMessage(err, fallback) {
    if (err?.response?.status === 403) return 'You do not have permission for this (Salary Sheet → Upload / Import / Export).';
    return err?.response?.data?.message || fallback;
}

async function downloadTemplate() {
    downloadingTemplate.value = true;
    try {
        await downloadImportTemplate('employee-master');
        pushToast('Template downloaded.', 'success');
    } catch (err) {
        pushToast(errorMessage(err, 'Could not download template.'), 'error');
    } finally {
        downloadingTemplate.value = false;
    }
}

function onFileSelected(event) {
    selectedFile.value = event.target.files?.[0] || null;
    preview.value = null;
    results.value = null;
    if (selectedFile.value) scan();
}

async function scan() {
    if (!selectedFile.value) return;
    scanning.value = true;
    preview.value = null;
    results.value = null;
    showRows.value = false;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const { data } = await client.post('/finance-payroll/staff-profiles/preview', body);
        preview.value = data;
    } catch (err) {
        pushToast(errorMessage(err, 'Could not read this file.'), 'error');
    } finally {
        scanning.value = false;
    }
}

async function runImport() {
    if (!selectedFile.value || !writableCount.value) return;
    importing.value = true;
    results.value = null;
    try {
        const body = new FormData();
        body.append('file', selectedFile.value);
        const { data } = await client.post('/finance-payroll/staff-profiles/import', body);
        results.value = data;
        preview.value = null;
        invalidatePeopleLookups();
        pushToast(`${data.created_count} created, ${data.updated_count} updated${data.failed_count ? `, ${data.failed_count} need review` : ''}.`, 'success');
    } catch (err) {
        pushToast(errorMessage(err, 'Import failed.'), 'error');
    } finally {
        importing.value = false;
    }
}

const exportType = ref('all');
const exportStatus = ref('all');
const exporting = ref(false);

async function runExport() {
    exporting.value = true;
    try {
        await downloadStaffProfiles({ type: exportType.value, status: exportStatus.value });
    } catch (err) {
        pushToast(errorMessage(err, 'Could not export profiles.'), 'error');
    } finally {
        exporting.value = false;
    }
}
</script>
