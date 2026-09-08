<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Import / Export</h1>
            <Breadcrumb :items="['Dashboard', 'Import & Export', 'Import / Export']" class="mt-1" />
        </div>

        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="rounded-md px-4 py-1.5 text-sm font-medium transition" :class="activeTab === 'import' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="activeTab = 'import'">Import</button>
            <button type="button" class="rounded-md px-4 py-1.5 text-sm font-medium transition" :class="activeTab === 'export' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="activeTab = 'export'">Export</button>
            <button type="button" class="rounded-md px-4 py-1.5 text-sm font-medium transition" :class="activeTab === 'logs' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="activeTab = 'logs'">Logs</button>
        </div>

        <!-- Import tab -->
        <div v-if="activeTab === 'import'" class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Select Data To Import</h3>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                    <button
                        v-for="m in visibleImportEntities"
                        :key="m.key"
                        type="button"
                        class="flex flex-col items-start gap-1.5 rounded-lg border p-3 text-left transition"
                        :class="activeEntity === m.key ? 'border-primary-400 bg-primary-50 dark:border-primary-500 dark:bg-primary-500/10' : 'border-slate-100 hover:border-primary-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:border-primary-500/30 dark:hover:bg-slate-800'"
                        @click="selectEntity(m.key)"
                    >
                        <span class="text-lg">{{ m.icon }}</span>
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ m.label }}</span>
                    </button>
                </div>
            </div>

            <div v-if="activeEntity === 'student-pen' || activeEntity === 'global' || activeEntity === 'attendance' || activeEntity === 'exam-marks'" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Upload — {{ uploadTitle }}</h3>
                <p v-if="activeEntity === 'student-pen'" class="mb-3 text-xs text-slate-400">Upload the UDISE portal's "Students Details" export as-is — run this after Global Workbook Import (Student Master sheet). Its title row (row 1) is skipped automatically. Matching is by ENRL # (same value as Master Record Adm No.); only the Student PEN column is imported, everything else (including the masked Aadhaar) is ignored.</p>
                <div v-else-if="activeEntity === 'exam-marks'" class="mb-3 space-y-2 text-xs text-slate-400">
                    <p>Upload one <strong class="text-slate-600 dark:text-slate-300">CLASS_&lt;name&gt;_TERM-1_&lt;session&gt;.xlsx</strong> file. Every marks sheet is processed: <strong class="text-slate-600 dark:text-slate-300">PT-1, NB-1, SEA-1, UNIT TEST / UNIT 1, GRADE</strong>. ATTD is ignored (attendance summary, not marks).</p>
                    <ul class="list-disc space-y-1 pl-4">
                        <li>Students matched by Adm. No. — must already exist</li>
                        <li>Blank / empty subject cells are skipped (no empty marks rows). When the client later fills PT/NB/SEA, re-import to save them — no code change needed</li>
                        <li>Each sheet becomes its own exam (PT-1, NB-1, SEA-1, UNIT TEST 1, GRADE). Default max: PT=10, NB=5, SEA=5, Unit Test=100</li>
                        <li>Re-import overwrites the same exam+subject+student marks</li>
                    </ul>
                </div>
                <div v-else-if="activeEntity === 'attendance'" class="mb-3 space-y-2 text-xs text-slate-400">
                    <p>Upload the per-class attendance workbook — one sheet per class named <strong class="text-slate-600 dark:text-slate-300">NUR, LKG, UKG, 1st–8th</strong>. Each sheet's row 3 is the header, student rows start at row 4 (read until ENROL is blank). Only the raw "days present" month columns are imported — TOT/% formula columns are ignored.</p>
                    <ul class="list-disc space-y-1 pl-4">
                        <li>Students matched by ENROL (admission no.) — must already exist</li>
                        <li>Each filled month cell is stored as a monthly summary (working days from row 2, days present, %) — no daily Present/Absent rows are invented</li>
                        <li>Blank month cells are skipped (month not filled yet); a literal 0 is stored as 0 present</li>
                        <li>Re-import upserts the same student+month+year row instead of duplicating</li>
                    </ul>
                </div>
                <div v-else class="mb-3 space-y-2 text-xs text-slate-400">
                    <p>Upload the full school Excel workbook (.xlsx). Only <strong class="text-slate-600 dark:text-slate-300">INCOME</strong>, <strong class="text-slate-600 dark:text-slate-300">EXPENSES</strong>, <strong class="text-slate-600 dark:text-slate-300">TRANSPORT-*</strong>, and <strong class="text-slate-600 dark:text-slate-300">Student Master*</strong> sheets are imported.</p>
                    <ul class="list-disc space-y-1 pl-4">
                        <li>Student Master* (e.g. "Student Master 22-26") → runs first so new admissions in this sheet exist before INCOME rows try to match them</li>
                        <li>INCOME with Adm No. → Fee Receipts (students must already exist)</li>
                        <li>INCOME without Adm No. → Finance Income</li>
                        <li>EXPENSES → Finance Expenses (Part-1 becomes category)</li>
                        <li>TRANSPORT-NN (S.NO / STOPPAGE / FARE only) → updates fare on the matching route stop (Fee Structure → Transport); creates a new route+stop only for a stoppage with no existing match</li>
                        <li>Skipped sheets: SUMMARY, STUD_REC*, pivots, SALARY, BANK*, CHQ*, FUEL*, WORKING DAYS</li>
                        <li>Skipped columns: numeric headers (pasted totals), TOT_INCOME, and #REF! / INCOME / BALANCE junk columns</li>
                    </ul>
                </div>
                <label
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-10 text-center transition"
                    :class="dragOver ? 'border-primary-400 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 hover:border-primary-300 dark:border-slate-700'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <span class="text-3xl">📤</span>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Drag an Excel or CSV file here or click to browse</p>
                    <p class="text-xs text-slate-400">{{ activeEntity === 'global' || activeEntity === 'attendance' || activeEntity === 'exam-marks' ? 'Supports .xlsx / .xls up to 40MB' : 'Supports .xlsx, .xls, .csv up to 10MB' }}</p>
                    <input type="file" :accept="activeEntity === 'global' || activeEntity === 'attendance' || activeEntity === 'exam-marks' ? '.xlsx,.xls' : '.xlsx,.xls,.csv'" class="hidden" @change="onFileSelect" />
                </label>

                <div v-if="selectedFile" class="mt-3 space-y-2">
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                        <span class="flex items-center gap-2"><span>📄</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ selectedFile.name }}</span></span>
                        <button type="button" class="btn-primary !py-1 !text-xs" :disabled="importing" @click="submitImport">{{ importing ? 'Importing...' : 'Import' }}</button>
                    </div>

                    <div v-if="importing" class="space-y-1">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                class="h-full rounded-full bg-primary-600 transition-[width] duration-150"
                                :class="importPhase === 'processing' ? 'w-full animate-pulse' : ''"
                                :style="importPhase === 'uploading' ? { width: uploadProgress + '%' } : {}"
                            />
                        </div>
                        <p class="text-[11px] text-slate-400">
                            {{ importPhase === 'uploading' ? `Uploading... ${uploadProgress}%` : 'Uploaded — processing on server (large files can take a minute)...' }}
                        </p>
                    </div>
                </div>

                <div v-if="importResult" class="mt-4 space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800"><p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ importResult.log.total_rows }}</p><p class="text-[11px] text-slate-500">Rows</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-500/10"><p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ importResult.log.success_count }}</p><p class="text-[11px] text-slate-500">Imported</p></div>
                        <div class="rounded-lg bg-rose-50 p-3 text-center dark:bg-rose-500/10"><p class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ importResult.log.failed_count }}</p><p class="text-[11px] text-slate-500">Failed</p></div>
                    </div>
                    <p v-if="importResult.message" class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">{{ importResult.message }}</p>
                    <div v-if="resultBreakdown" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        <div v-for="item in resultBreakdown" :key="item.label" class="rounded-lg border border-slate-100 px-2.5 py-2 dark:border-slate-800">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.value }}</p>
                            <p class="text-[10px] text-slate-500">{{ item.label }}</p>
                        </div>
                    </div>
                    <div v-if="importResult.stats" class="rounded-lg border border-slate-100 p-3 text-xs text-slate-500 dark:border-slate-800">
                        <p v-if="importResult.stats.income_fee_payments != null">Fee payments: <strong class="text-slate-700 dark:text-slate-200">{{ importResult.stats.income_fee_payments }}</strong></p>
                        <p v-if="importResult.stats.income_misc != null">Misc income: <strong class="text-slate-700 dark:text-slate-200">{{ importResult.stats.income_misc }}</strong></p>
                        <p v-if="importResult.stats.expenses != null">Expenses: <strong class="text-slate-700 dark:text-slate-200">{{ importResult.stats.expenses }}</strong></p>
                        <p v-if="importResult.log.ignored_columns?.length" class="mt-1">Skipped columns: {{ importResult.log.ignored_columns.join(', ') }}</p>
                        <p v-if="importResult.stats.sheets_ignored?.length" class="mt-1">Skipped sheets: {{ importResult.stats.sheets_ignored.join(', ') }}</p>
                        <p v-if="importResult.stats.attendance_records_written != null">Attendance day-records written: <strong class="text-slate-700 dark:text-slate-200">{{ importResult.stats.attendance_records_written }}</strong></p>
                        <p v-if="importResult.stats.session">Session: <strong class="text-slate-700 dark:text-slate-200">{{ importResult.stats.session }}</strong></p>
                        <p v-if="importResult.stats.sheets_skipped_no_class?.length" class="mt-1 text-amber-600">No matching class: {{ importResult.stats.sheets_skipped_no_class.join('; ') }}</p>
                    </div>
                    <div v-if="importResult.failed_rows?.length" class="overflow-hidden rounded-lg border border-slate-100 dark:border-slate-800">
                        <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">Failed records (with reason)</div>
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white dark:bg-slate-900">
                                <tr>
                                    <th class="px-3 py-2 font-semibold uppercase text-slate-500">Sheet</th>
                                    <th class="px-3 py-2 font-semibold uppercase text-slate-500">Row</th>
                                    <th class="px-3 py-2 font-semibold uppercase text-slate-500">Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="r in importResult.failed_rows" :key="r.id">
                                    <td class="px-3 py-2 text-slate-500">{{ r.sheet || '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ r.row_number }}</td>
                                    <td class="px-3 py-2 text-rose-600 dark:text-rose-400">{{ r.error_message }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                Import isn't available yet for {{ activeEntityLabel }} — Student, Student PEN, Global Workbook, Attendance, and Exam Marks Import are currently supported. Export is available for every data type from the Export tab.
            </div>
        </div>

        <!-- Export tab -->
        <div v-else-if="activeTab === 'export'" class="space-y-4">
            <div v-if="canUseIeKey('student-export')" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Student Export</h3>
                <p class="mb-4 text-xs text-slate-400">Full session history in Master Import format. Filter by student status and academic sessions, then download.</p>
                <div class="max-w-md rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                    <StudentExportPanel :exporting="!!exportingKey" @export="onStudentExport" />
                </div>
            </div>

            <div v-if="canUseIeKey('student-udise-export')" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Student UDISE Export</h3>
                <p class="mb-4 text-xs text-slate-400">Only students marked In UDISE. Pick status, sessions, and columns — Excel includes checked columns in the order listed.</p>
                <div class="max-w-md rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                    <StudentUdiseExportPanel :exporting="!!exportingKey" @export="onStudentUdiseExport" />
                </div>
            </div>

            <div v-if="visibleOtherExportEntities.length" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Other Exports</h3>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="m in visibleOtherExportEntities" :key="m.key" class="flex items-center justify-between rounded-lg border border-slate-100 p-3.5 dark:border-slate-800" :class="m.key === 'global' && 'border-primary-200 bg-primary-50/40 dark:border-primary-500/30 dark:bg-primary-500/5'">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">{{ m.icon }}</span>
                            <div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ m.label }}</p>
                                <p v-if="m.key === 'global'" class="text-xs text-slate-400">All modules in one Excel file</p>
                            </div>
                        </div>
                        <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exportingKey === m.key" @click="exportEntity(m)">{{ exportingKey === m.key ? 'Exporting...' : 'Export' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs tab -->
        <div v-else class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Import / Export History</h3>
                        <p class="text-xs text-slate-400">Every run is kept here — click one to see exactly which rows failed and why.</p>
                    </div>
                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                        <button type="button" class="rounded-md px-2.5 py-1 text-xs font-medium transition" :class="logsDirectionFilter === null ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="setLogsDirectionFilter(null)">All</button>
                        <button type="button" class="rounded-md px-2.5 py-1 text-xs font-medium transition" :class="logsDirectionFilter === 'import' ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="setLogsDirectionFilter('import')">Import</button>
                        <button type="button" class="rounded-md px-2.5 py-1 text-xs font-medium transition" :class="logsDirectionFilter === 'export' ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'" @click="setLogsDirectionFilter('export')">Export</button>
                    </div>
                </div>

                <div v-if="logsLoading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!logs.length" class="py-10 text-center text-sm text-slate-400">No import/export runs yet.</div>
                <div v-else class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-3 py-2 font-semibold uppercase text-slate-500">Date</th>
                                <th class="px-3 py-2 font-semibold uppercase text-slate-500">Type</th>
                                <th class="px-3 py-2 font-semibold uppercase text-slate-500">File</th>
                                <th class="px-3 py-2 font-semibold uppercase text-slate-500">By</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">Rows</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">Success</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">Failed</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template v-for="log in logs" :key="log.id">
                                <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40" @click="toggleLogDetail(log)">
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ formatDate(log.created_at) }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300"><span class="capitalize">{{ log.direction }}</span> · {{ entityLabel(log.entity) }}</td>
                                    <td class="max-w-[220px] truncate px-3 py-2 text-slate-500" :title="log.filename">{{ log.filename || '—' }}</td>
                                    <td class="px-3 py-2 text-slate-500">{{ log.performed_by?.name || '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ log.total_rows }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-emerald-600 dark:text-emerald-400">{{ log.success_count }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums" :class="log.failed_count ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-400'">{{ log.failed_count }}</td>
                                    <td class="px-3 py-2 text-right text-slate-400">{{ expandedLogId === log.id ? '▲' : '▼' }}</td>
                                </tr>
                                <tr v-if="expandedLogId === log.id">
                                    <td colspan="8" class="bg-slate-50 px-3 py-3 dark:bg-slate-800/30">
                                        <div v-if="logRowsLoading" class="py-4 text-center text-xs text-slate-400">Loading details...</div>
                                        <div v-else>
                                            <p v-if="log.ignored_columns?.length" class="mb-2 text-xs text-slate-500">Skipped columns: {{ log.ignored_columns.join(', ') }}</p>
                                            <div v-if="!logFailedRows.length" class="py-3 text-center text-xs text-slate-400">No failed rows for this run.</div>
                                            <div v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                                <table class="w-full text-left text-xs">
                                                    <thead class="bg-white dark:bg-slate-900">
                                                        <tr>
                                                            <th class="px-3 py-2 font-semibold uppercase text-slate-500">Row</th>
                                                            <th class="px-3 py-2 font-semibold uppercase text-slate-500">Identifier</th>
                                                            <th class="px-3 py-2 font-semibold uppercase text-slate-500">Why it failed</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                        <tr v-for="r in logFailedRows" :key="r.id">
                                                            <td class="px-3 py-2 text-slate-500">{{ r.row_number }}</td>
                                                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ r.identifier || '—' }}</td>
                                                            <td class="px-3 py-2 text-rose-600 dark:text-rose-400">{{ r.error_message }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from '../components/common/Breadcrumb.vue';
import StudentExportPanel from '../components/export/StudentExportPanel.vue';
import StudentUdiseExportPanel from '../components/export/StudentUdiseExportPanel.vue';
import client from '../api/client';
import { erpStore } from '../store';
import { pushToast } from '../utils/toast';
import { downloadExport } from '../utils/downloadExport';

const ENTITIES = [
    { key: 'global', label: 'Global Workbook', icon: '📊', slug: 'global-workbook' },
    { key: 'student', label: 'Students', icon: '🎓', slug: 'student' },
    { key: 'attendance', label: 'Attendance', icon: '📅', slug: 'attendance' },
];

// Import-only — no export counterpart, so kept out of ENTITIES (which also drives the Export tab).
const EXTRA_IMPORT_ENTITIES = [
    { key: 'student-pen', label: 'Student PEN', icon: '🆔', slug: 'student-pen' },
    { key: 'exam-marks', label: 'Exam Marks', icon: '📝', slug: 'exam-marks' },
];

// Export-only entities (not shown on the Import tab entity picker).
const EXTRA_EXPORT_ENTITIES = [
    { key: 'student-udise', label: 'Student UDISE', icon: '📋', slug: 'student-udise' },
];

// Dedicated Student Master import removed — use Global Workbook (Student Master sheet) instead.
const IMPORT_PICKER_ENTITIES = [
    ...ENTITIES.filter((e) => e.key !== 'student'),
    ...EXTRA_IMPORT_ENTITIES,
];

const ALL_ROUTE_ENTITIES = [...ENTITIES, ...EXTRA_IMPORT_ENTITIES, ...EXTRA_EXPORT_ENTITIES];

const IMPORT_ENTITY_TO_MENU_KEY = {
    global: 'global-workbook-import',
    'student-pen': 'student-pen-import',
    attendance: 'attendance-import',
    'exam-marks': 'exam-marks-import',
};

const EXPORT_ENTITY_TO_MENU_KEY = {
    global: 'global-workbook-export',
    attendance: 'attendance-export',
};

function canUseIeKey(menuKey) {
    if (!erpStore.isDemoSchool) return true;
    return !(erpStore.demoHiddenImportExport || []).includes(menuKey);
}

const visibleImportEntities = computed(() =>
    IMPORT_PICKER_ENTITIES.filter((e) => {
        const key = IMPORT_ENTITY_TO_MENU_KEY[e.key];
        return !key || canUseIeKey(key);
    }),
);

const visibleOtherExportEntities = computed(() =>
    ENTITIES.filter((e) => e.key !== 'student').filter((e) => {
        const key = EXPORT_ENTITY_TO_MENU_KEY[e.key];
        return !key || canUseIeKey(key);
    }),
);

const route = useRoute();
const router = useRouter();
const typeParam = computed(() => route.query.type?.toString() || '');

const parsedType = computed(() => {
    const direction = typeParam.value.endsWith('-import') ? 'import' : typeParam.value.endsWith('-export') ? 'export' : null;
    const slug = direction ? typeParam.value.replace(/-(import|export)$/, '') : null;
    const entity = ALL_ROUTE_ENTITIES.find((e) => e.slug === slug);
    return { direction, entity };
});

const activeTab = ref(parsedType.value.direction || 'import');
const activeEntity = ref(
    parsedType.value.entity?.key === 'student' && parsedType.value.direction === 'import'
        ? 'global'
        : (parsedType.value.entity?.key || 'global'),
);
const activeEntityLabel = computed(() => ALL_ROUTE_ENTITIES.find((e) => e.key === activeEntity.value)?.label || '');
const uploadTitle = computed(() => {
    if (activeEntity.value === 'student') return 'Students';
    if (activeEntity.value === 'student-pen') return 'Student PEN';
    if (activeEntity.value === 'global') return 'Global Workbook';
    if (activeEntity.value === 'attendance') return 'Attendance';
    if (activeEntity.value === 'exam-marks') return 'Exam Marks (Class Term)';
    return activeEntityLabel.value;
});

const IMPORT_ENDPOINTS = {
    student: '/import-export/import/student-master',
    'student-pen': '/import-export/import/student-pen',
    global: '/import-export/import/global-workbook',
    attendance: '/import-export/import/attendance',
    'exam-marks': '/import-export/import/class-term-marks',
};

// Redirect legacy Student Import bookmark to Global Workbook Import.
watch(
    typeParam,
    (type) => {
        if (type === 'student-import') {
            router.replace({ path: route.path, query: { type: 'global-workbook-import' } });
        }
    },
    { immediate: true },
);

// The whole Import & Export sidebar group shares this one route (only `type` in the
// query differs), so Vue Router reuses this component instance across navigations —
// re-sync local state from the query on every change instead of only at setup.
watch(
    parsedType,
    ({ direction, entity }) => {
        if (direction) activeTab.value = direction;
        if (entity) {
            if (direction === 'import' && entity.key === 'student') {
                activeEntity.value = 'global';
            } else {
                activeEntity.value = entity.key;
            }
        }
        resetUpload();
    },
);

function selectEntity(key) {
    activeEntity.value = key;
    resetUpload();
}

const dragOver = ref(false);
const selectedFile = ref(null);
const importing = ref(false);
const importResult = ref(null);
const importPhase = ref('idle'); // idle | uploading | processing
const uploadProgress = ref(0);

const resultBreakdown = computed(() => {
    const b = importResult.value?.breakdown || importResult.value?.stats?.breakdown;
    if (!b) return null;
    const items = [
        { label: 'Skipped (header artifact)', value: b.skipped_header_artifact },
        { label: 'Skipped (duplicate)', value: b.skipped_duplicate },
        { label: 'Failed (student not found)', value: b.failed_student_not_found },
        { label: 'Failed (missing field)', value: b.failed_missing_field },
        { label: 'Failed (broken formula)', value: b.failed_broken_formula },
        { label: 'Failed (zero/negative amount)', value: b.failed_zero_amount },
        { label: 'Failed (invalid date)', value: b.failed_invalid_date },
        { label: 'Failed (other)', value: b.failed_other },
    ];
    return items.filter((i) => (i.value ?? 0) > 0);
});

function handleFile(file) {
    if (!file) return;
    dragOver.value = false;
    selectedFile.value = file;
    importResult.value = null;
}
function onDrop(e) {
    handleFile(e.dataTransfer.files[0]);
}
function onFileSelect(e) {
    handleFile(e.target.files[0]);
}
function resetUpload() {
    selectedFile.value = null;
    importResult.value = null;
    importPhase.value = 'idle';
    uploadProgress.value = 0;
}

watch(
    visibleImportEntities,
    (list) => {
        if (activeTab.value !== 'import') return;
        if (!list.length) return;
        if (!list.some((e) => e.key === activeEntity.value)) {
            activeEntity.value = list[0].key;
            resetUpload();
        }
    },
    { immediate: true },
);

async function submitImport() {
    const endpoint = IMPORT_ENDPOINTS[activeEntity.value];
    if (!endpoint || !selectedFile.value) return;
    importing.value = true;
    importPhase.value = 'uploading';
    uploadProgress.value = 0;
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        const { data } = await client.post(endpoint, formData, {
            // Large Master / PEN / workbook files need a long client timeout.
            timeout: 600000,
            onUploadProgress: (event) => {
                if (!event.total) return;
                uploadProgress.value = Math.round((event.loaded / event.total) * 100);
                // Upload done, server is now parsing/writing rows — no further progress
                // events arrive (the app has no background-job/polling mechanism for that),
                // so the bar switches to an indeterminate "processing" state instead of
                // sitting frozen at 100%.
                if (uploadProgress.value >= 100) importPhase.value = 'processing';
            },
        });
        importResult.value = data;
        const failed = data.log?.failed_count || 0;
        pushToast(data.message || `Imported ${data.log.success_count} of ${data.log.total_rows} row(s).`, failed ? 'error' : 'success');
    } finally {
        importing.value = false;
        importPhase.value = 'idle';
    }
}

const exportingKey = ref(null);
async function exportEntity(entity, format = 'xlsx') {
    exportingKey.value = entity.key;
    try {
        await downloadExport(entity.key === 'global' ? 'global' : entity.key, format);
        pushToast(`${entity.label} exported.`, 'success');
    } finally {
        exportingKey.value = null;
    }
}

async function onStudentExport({ format, status, sessions, columns }) {
    exportingKey.value = `student-${format}`;
    try {
        await downloadExport('student', format, { status, sessions, columns });
        pushToast(`Students exported as ${format.toUpperCase()}.`, 'success');
    } finally {
        exportingKey.value = null;
    }
}

async function onStudentUdiseExport({ format, status, sessions, columns }) {
    exportingKey.value = `student-udise-${format}`;
    try {
        await downloadExport('student-udise', format, { status, sessions, columns });
        pushToast(`Student UDISE exported as ${format.toUpperCase()}.`, 'success');
    } finally {
        exportingKey.value = null;
    }
}

// --- Logs tab ---
const logs = ref([]);
const logsLoading = ref(false);
const logsDirectionFilter = ref('import');
const expandedLogId = ref(null);
const logRowsLoading = ref(false);
const logFailedRows = ref([]);

const ENTITY_LABELS = {
    'student-master': 'Students',
    'student-pen': 'Student PEN',
    'global-workbook': 'Global Workbook',
    student: 'Students',
    'student-udise': 'Student UDISE',
    global: 'Global Workbook',
    attendance: 'Attendance',
    'exam-marks': 'Exam Marks',
    'class-term-marks': 'Exam Marks',
};
function entityLabel(entityKey) {
    return ENTITY_LABELS[entityKey] || entityKey;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function loadLogs() {
    logsLoading.value = true;
    try {
        const { data } = await client.get('/import-export/logs', {
            params: logsDirectionFilter.value ? { direction: logsDirectionFilter.value } : {},
        });
        logs.value = Array.isArray(data) ? data : [];
    } finally {
        logsLoading.value = false;
    }
}

function setLogsDirectionFilter(direction) {
    logsDirectionFilter.value = direction;
    expandedLogId.value = null;
    loadLogs();
}

async function toggleLogDetail(log) {
    if (expandedLogId.value === log.id) {
        expandedLogId.value = null;
        return;
    }
    expandedLogId.value = log.id;
    logFailedRows.value = [];
    if (!log.failed_count) return;
    logRowsLoading.value = true;
    try {
        const { data } = await client.get(`/import-export/logs/${log.id}/rows`);
        logFailedRows.value = (Array.isArray(data) ? data : []).filter((r) => r.status === 'Failed');
    } finally {
        logRowsLoading.value = false;
    }
}

watch(activeTab, (tab) => {
    if (tab === 'logs' && !logs.value.length) loadLogs();
});
</script>
