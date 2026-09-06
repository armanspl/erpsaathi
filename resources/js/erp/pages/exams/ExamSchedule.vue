<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Exam Management</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage exam definitions, schedules, and grading ranges.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button v-if="scheduleView === 'list'" type="button" class="btn-outline inline-flex items-center gap-1.5" @click="window.print()">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v8H6Z"/></svg>
                    Print
                </button>
                <button v-if="scheduleView === 'list'" type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openImportModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Import Excel
                </button>
                <button v-if="scheduleView === 'list'" type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreateSchedule">
                    <span class="text-lg leading-none">+</span> Create Schedule
                </button>
            </div>
        </div>

        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="tab.id === 'schedule' ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                @click="onTabClick(tab.id)"
            >
                {{ tab.label }}
                <span v-if="tab.id === 'schedule'" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <template v-if="scheduleView === 'list'">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="scheduleFiltersOpen = !scheduleFiltersOpen">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                        Filters
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition" :class="scheduleFiltersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-show="scheduleFiltersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Exam</label>
                            <select v-model="scheduleFilters.exam_id" class="form-input">
                                <option value="">All exams</option>
                                <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Branch</label>
                            <select v-model="scheduleFilters.branch_id" class="form-input">
                                <option value="">All branches</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="selectedFilterExam" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <ExamPdfColorPicker :exam="selectedFilterExam" @update="onExamColorUpdate" />
                        <p class="mt-1 text-xs text-slate-400">Applies to every exam schedule (and admit card / report card) PDF downloaded for {{ selectedFilterExam.name }}.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                    <button
                        v-for="mode in viewModes"
                        :key="mode.id"
                        type="button"
                        class="rounded-md p-1.5 transition"
                        :class="scheduleViewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                        :title="mode.label"
                        @click="scheduleViewMode = mode.id"
                    >
                        <span v-html="mode.icon" />
                    </button>
                </div>

                <div v-if="sheetsLoading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!filteredSheets.length" class="px-6 py-20 text-center text-sm text-slate-400">No exam schedules found.</div>

                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" disabled /></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Exam ↕</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch ↕</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Session ↕</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dates ↕</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Sittings ↕</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Updated ↕</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in filteredSheets" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" /></td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.exam?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.branch?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.session_name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.dates_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.sittings_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(s.updated_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="View" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Download" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="downloadSheetPdf(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- SHEET EDIT VIEW -->
        <template v-else>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-xs" @click="closeSheetEdit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editingSheet ? 'Edit Exam Schedule' : 'Create Exam Schedule' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ sheetSubtitle }}</p>
                    </div>
                    <button type="button" class="btn-primary" :disabled="sheetSaving" @click="saveSheet">{{ sheetSaving ? 'Saving...' : 'Save' }}</button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="sheetForm.exam_id" class="form-input">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="sheetForm.branch_id" class="form-input">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Sittings</h3>
                    <button type="button" class="btn-outline !py-1 !text-xs" @click="addSitting">+ Add</button>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div v-for="(sitting, i) in sheetForm.sittings" :key="i" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <label class="form-label">Label</label>
                        <input v-model="sitting.label" type="text" class="form-input" />
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div>
                                <label class="form-label">From</label>
                                <input v-model="sitting.start_time" type="time" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">To</label>
                                <input v-model="sitting.end_time" type="time" class="form-input" />
                            </div>
                        </div>
                        <button type="button" class="mt-3 w-full rounded-lg bg-rose-600 py-1.5 text-xs font-semibold text-white hover:bg-rose-700" @click="sheetForm.sittings.splice(i, 1)">Remove</button>
                    </div>
                </div>
            </div>

            <div v-if="importWarnings" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Review before saving — this timetable was pre-filled from an Excel import</p>
                    <button type="button" class="shrink-0 text-xs font-medium text-amber-700 hover:underline dark:text-amber-400" @click="importWarnings = null">Dismiss</button>
                </div>
                <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300">
                    <li v-for="(note, i) in importWarnings.notes" :key="`note-${i}`">{{ note }}</li>
                </ul>
                <div v-if="importWarnings.unmatched_subjects.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ importWarnings.unmatched_subjects.length }} subject(s) couldn't be matched — pick the right one from the dropdown in the grid below (marked with ⚠):</p>
                    <ul class="mt-1 max-h-28 space-y-0.5 overflow-y-auto text-xs text-amber-700 dark:text-amber-400">
                        <li v-for="(u, i) in importWarnings.unmatched_subjects" :key="`unm-${i}`">{{ u.date }} · {{ u.class_label }} · "{{ u.raw_text }}"</li>
                    </ul>
                </div>
                <div v-if="importWarnings.suspected_holidays.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ importWarnings.suspected_holidays.length }} date(s) had no subjects for any class — possibly a holiday the file just omitted a row for. Check the "possible holiday?" flags in the grid.</p>
                </div>
                <div v-if="importWarnings.classes_without_sections.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">These classes have no sections configured, so their imported subjects couldn't be placed anywhere: {{ importWarnings.classes_without_sections.join(', ') }}. Add a section to that class first, then re-import.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Timetable</h3>
                    <button type="button" class="btn-outline !py-1 !text-xs" @click="openImportModal">Import Excel</button>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">Add dates and mark a row as holiday to clear all class-section cells for that day.</p>
                <p class="mt-0.5 text-xs text-slate-500">The printed schedule prints exactly what's entered below — a class with only one subject for a date prints one sitting; add "Oral / Sitting 2" only where a second sitting really happens.</p>

                <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Date</th>
                                <th v-for="col in scheduleColumns" :key="col.section_id" class="whitespace-nowrap px-3 py-2 font-semibold text-slate-500">
                                    {{ col.label }}
                                    <button type="button" class="ml-1 inline-flex items-center gap-0.5 text-primary-600 hover:underline" title="Copy first row down this column" @click="copyColumnDown(col)">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="1"/><path d="M5 15V5a1 1 0 011-1h10"/></svg>
                                        Copy
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!sheetForm.dates.length">
                                <td :colspan="scheduleColumns.length + 1" class="px-3 py-6 text-center text-slate-400">No dates added yet.</td>
                            </tr>
                            <tr v-for="(row, ri) in sheetForm.dates" :key="ri" :class="row.is_holiday ? 'bg-amber-50/60 dark:bg-amber-500/5' : ''">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 dark:bg-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span>{{ row.date }}</span>
                                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                                            <input type="checkbox" v-model="row.is_holiday" class="h-3.5 w-3.5 rounded border-slate-300" /> Holiday
                                        </label>
                                        <span v-if="row.suspected_holiday" class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-400" title="No subjects were found for any class on this date in the imported file">possible holiday?</span>
                                        <button type="button" class="text-rose-500 hover:underline" @click="sheetForm.dates.splice(ri, 1)">×</button>
                                    </div>
                                </td>
                                <td v-for="col in scheduleColumns" :key="col.section_id" class="min-w-[200px] px-3 py-2 align-top">
                                    <span v-if="row.is_holiday" class="text-slate-400">Holiday</span>
                                    <div v-else class="space-y-2">
                                        <div v-for="(entry, ei) in row.cells[colKey(col)]" :key="ei" class="rounded-lg border p-2" :class="entry.unmatched_label ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'">
                                            <p class="text-[11px] font-semibold text-slate-400">Sitting {{ ei + 1 }}</p>
                                            <p v-if="entry.unmatched_label" class="text-[11px] font-medium text-rose-600 dark:text-rose-400">⚠ "{{ entry.unmatched_label }}" — pick the matching subject:</p>
                                            <select v-model="entry.subject_id" class="form-input !py-1 !text-xs">
                                                <option :value="null">Select subject</option>
                                                <option v-for="sub in subjects" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                                            </select>
                                            <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                                                <input v-model="entry.start_time" type="time" class="form-input !py-1 !text-xs" />
                                                <input v-model="entry.end_time" type="time" class="form-input !py-1 !text-xs" />
                                            </div>
                                            <button type="button" class="mt-1.5 text-xs font-semibold text-rose-600 hover:underline" @click="row.cells[colKey(col)].splice(ei, 1)">Remove</button>
                                        </div>
                                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline" @click="addEntry(row, col)">
                                            {{ (row.cells[colKey(col)] || []).length === 1 ? '+ Add Oral / Sitting 2' : '+ Add subject' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <input v-model="newDate.date" type="date" class="form-input max-w-xs" />
                    <label class="inline-flex items-center gap-1.5 text-sm text-slate-500">
                        <input type="checkbox" v-model="newDate.is_holiday" class="h-4 w-4 rounded border-slate-300" /> Holiday
                    </label>
                    <button type="button" class="btn-primary" :disabled="!newDate.date" @click="addDate">Add date</button>
                </div>
            </div>
        </template>

        <!-- Import Excel modal -->
        <div v-if="importModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Import Exam Schedule from Excel</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload the school's printed exam routine. It's parsed and used to pre-fill the timetable grid below for review — nothing is saved until you click Save there.</p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="importForm.exam_id" class="form-input">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="importForm.branch_id" class="form-input">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="form-label">File</label>
                    <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onImportFileSelected" />
                </div>

                <p v-if="importError" class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">{{ importError }}</p>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-outline flex-1" :disabled="importing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="btn-primary flex-1" :disabled="!canRunImport || importing" @click="runImport">
                        {{ importing ? 'Parsing...' : 'Parse & Pre-fill' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import ExamPdfColorPicker from '../../components/ExamPdfColorPicker.vue';

const router = useRouter();

const tabs = [
    { id: 'list', label: 'Exam List' },
    { id: 'schedule', label: 'Exam Schedule' },
    { id: 'grades', label: 'Exam Grades' },
];

function onTabClick(tabId) {
    if (tabId === 'schedule') return;
    router.push(tabId === 'grades' ? '/exam-management/exams?tab=grades' : '/exam-management/exams');
}

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const exams = ref([]);
const branches = ref([]);
const classes = ref([]);
const subjects = ref([]);
const sheets = ref([]);
const sheetsLoading = ref(true);
const scheduleFiltersOpen = ref(true);
const scheduleViewMode = ref('table');
const scheduleFilters = reactive({ exam_id: '', branch_id: '' });

const scheduleView = ref('list');
const editingSheet = ref(null);
const sheetSaving = ref(false);
const sheetForm = reactive({ exam_id: null, branch_id: null, sittings: [], dates: [] });
const newDate = reactive({ date: '', is_holiday: false });
const currentSessionName = ref('');

// Excel import: parses server-side and pre-fills sheetForm.dates for review — it never saves on
// its own, saving still goes through the normal saveSheet() flow below.
const importModalOpen = ref(false);
const importForm = reactive({ exam_id: null, branch_id: null });
const importFile = ref(null);
const importFileInput = ref(null);
const importing = ref(false);
const importError = ref('');
const importWarnings = ref(null);
const canRunImport = computed(() => !!(importForm.exam_id && importForm.branch_id && importFile.value));

const selectedFilterExam = computed(() => exams.value.find((e) => e.id === scheduleFilters.exam_id) || null);
function onExamColorUpdate(updated) {
    const idx = exams.value.findIndex((e) => e.id === updated.id);
    if (idx !== -1) exams.value[idx] = { ...exams.value[idx], ...updated };
}

const filteredSheets = computed(() => {
    let rows = sheets.value;
    if (scheduleFilters.exam_id) rows = rows.filter((s) => s.exam_id === scheduleFilters.exam_id);
    if (scheduleFilters.branch_id) rows = rows.filter((s) => s.branch_id === scheduleFilters.branch_id);
    return rows;
});

const scheduleColumns = computed(() => {
    const cols = [];
    for (const c of classes.value) {
        for (const s of c.sections || []) {
            cols.push({ school_class_id: c.id, section_id: s.id, label: `${c.name} (${s.name})` });
        }
    }
    return cols;
});

const sheetSubtitle = computed(() => {
    const exam = exams.value.find((e) => e.id === sheetForm.exam_id);
    const branch = branches.value.find((b) => b.id === sheetForm.branch_id);
    const parts = [currentSessionName.value, exam?.name, branch?.name].filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Pick an exam and branch to get started.';
});

function colKey(col) {
    return `${col.school_class_id}_${col.section_id}`;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadScheduleLookups() {
    const [academics, sessionsRes, examsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/exams'),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    subjects.value = academics.subjects || [];
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
    exams.value = examsRes.data;
}

async function loadSheets() {
    sheetsLoading.value = true;
    try {
        await loadScheduleLookups();
        const { data } = await client.get('/exams/schedule-sheets');
        sheets.value = data;
    } finally {
        sheetsLoading.value = false;
    }
}

function blankSheetForm() {
    Object.assign(sheetForm, { exam_id: null, branch_id: null, sittings: [], dates: [] });
}

function addSitting() {
    sheetForm.sittings.push({ label: `Sitting ${sheetForm.sittings.length + 1}`, start_time: '09:00', end_time: '12:00' });
}

// A Timetable entry's position (1st entry, 2nd entry...) is what "Sitting 1" / "Sitting 2" means
// in the grid, so its time is driven by the Sitting defined at that same position — editing a
// Sitting's From/To here live-updates every matching cell across the whole Timetable, and a
// freshly imported (blank) cell picks up the current Sitting time the moment one exists at its
// position. Sittings are the single source of truth for timing; per-cell time inputs stay
// editable for one-off exceptions, but they get overwritten back to the Sitting's time the next
// time that Sitting is edited (adding/removing a Sitting row doesn't touch other positions).
function syncSittingTimes() {
    sheetForm.dates.forEach((row) => {
        if (row.is_holiday) return;
        Object.values(row.cells).forEach((entries) => {
            (entries || []).forEach((entry, i) => {
                const sitting = sheetForm.sittings[i];
                if (sitting) {
                    entry.start_time = sitting.start_time;
                    entry.end_time = sitting.end_time;
                }
            });
        });
    });
}
watch(() => sheetForm.sittings, syncSittingTimes, { deep: true });

function addDate() {
    if (!newDate.date) return;
    if (sheetForm.dates.some((d) => d.date === newDate.date)) {
        pushToast('That date is already in the timetable.', 'error');
        return;
    }
    const cells = {};
    scheduleColumns.value.forEach((col) => { cells[colKey(col)] = []; });
    sheetForm.dates.push({ date: newDate.date, is_holiday: newDate.is_holiday, cells });
    sheetForm.dates.sort((a, b) => (a.date < b.date ? -1 : 1));
    Object.assign(newDate, { date: '', is_holiday: false });
}

function addEntry(row, col) {
    const list = row.cells[colKey(col)];
    const sitting = sheetForm.sittings[list.length];
    list.push({ subject_id: null, start_time: sitting?.start_time || '', end_time: sitting?.end_time || '' });
}

function copyColumnDown(col) {
    if (!sheetForm.dates.length) return;
    const key = colKey(col);
    const entries = sheetForm.dates[0].cells[key] || [];
    sheetForm.dates.forEach((row) => {
        if (!row.is_holiday) row.cells[key] = entries.map((e) => ({ ...e }));
    });
}

function openCreateSchedule() {
    editingSheet.value = null;
    blankSheetForm();
    importWarnings.value = null;
    scheduleView.value = 'edit';
}

// Shared by openSheet() (loading a saved sheet) and applyImportResult() (pre-filling from an
// Excel import) — both hand it the same {date, is_holiday, cells: [...]} shape (see
// ExamScheduleSheetController::present() and ExamScheduleImportParser::parse()). Import results
// additionally carry `suspected_holiday` on the date and `raw_label` on an unmatched cell entry;
// both are simply absent (undefined) on a normally-loaded sheet, so this needs no branching.
function datesFromServer(datesData) {
    return (datesData || []).map((d) => {
        const cells = {};
        scheduleColumns.value.forEach((col) => { cells[colKey(col)] = []; });
        (d.cells || []).forEach((cell) => {
            const key = `${cell.school_class_id}_${cell.section_id}`;
            cells[key] ??= [];
            cells[key].push({
                subject_id: cell.subject_id,
                start_time: cell.start_time ? cell.start_time.slice(0, 5) : '',
                end_time: cell.end_time ? cell.end_time.slice(0, 5) : '',
                unmatched_label: cell.raw_label || null,
            });
        });
        return { date: d.date, is_holiday: d.is_holiday, suspected_holiday: d.suspected_holiday || false, cells };
    });
}

async function openSheet(sheet) {
    editingSheet.value = sheet;
    sheetsLoading.value = true;
    try {
        await loadScheduleLookups();
        const { data } = await client.get(`/exams/schedule-sheets/${sheet.id}`);
        currentSessionName.value = data.session_name || '';
        sheetForm.exam_id = data.exam_id;
        sheetForm.branch_id = data.branch_id;
        sheetForm.sittings = (data.sittings || []).map((s) => ({ label: s.label, start_time: s.start_time.slice(0, 5), end_time: s.end_time.slice(0, 5) }));
        sheetForm.dates = datesFromServer(data.dates);
        syncSittingTimes();
        importWarnings.value = null;
        scheduleView.value = 'edit';
    } finally {
        sheetsLoading.value = false;
    }
}

function closeSheetEdit() {
    scheduleView.value = 'list';
    editingSheet.value = null;
    importWarnings.value = null;
    loadSheets();
}

// Pre-fills the exam/branch pickers from whatever's already chosen when re-importing into a
// sheet that's currently open; leaves them blank when launched fresh from the list view.
function openImportModal() {
    importForm.exam_id = scheduleView.value === 'edit' ? sheetForm.exam_id : null;
    importForm.branch_id = scheduleView.value === 'edit' ? sheetForm.branch_id : null;
    importFile.value = null;
    importError.value = '';
    importModalOpen.value = true;
}

function closeImportModal() {
    importModalOpen.value = false;
    if (importFileInput.value) importFileInput.value.value = '';
}

function onImportFileSelected(event) {
    importFile.value = event.target.files?.[0] || null;
    importError.value = '';
}

async function runImport() {
    if (!canRunImport.value) return;
    importing.value = true;
    importError.value = '';
    try {
        const body = new FormData();
        body.append('file', importFile.value);
        const { data } = await client.post('/exams/schedule-sheets/import-preview', body);

        // Launched fresh from the list view -- reset the form like Create Schedule does, so
        // stale sittings/dates from an earlier session don't linger. Re-importing into a sheet
        // that's already open only replaces the timetable, keeping any sittings already entered.
        if (scheduleView.value !== 'edit') {
            editingSheet.value = null;
            blankSheetForm();
        }
        sheetForm.exam_id = importForm.exam_id;
        sheetForm.branch_id = importForm.branch_id;
        sheetForm.dates = datesFromServer(data.dates);
        syncSittingTimes();
        importWarnings.value = data.warnings;
        scheduleView.value = 'edit';
        closeImportModal();
        pushToast(`Parsed ${data.dates.length} date(s) from the file — review the warnings below before saving.`, 'success');
    } catch (e) {
        importError.value = e?.response?.data?.message || 'Could not parse this file.';
    } finally {
        importing.value = false;
    }
}

async function saveSheet() {
    if (!sheetForm.exam_id || !sheetForm.branch_id) {
        pushToast('Select an exam and branch.', 'error');
        return;
    }
    sheetSaving.value = true;
    try {
        const payload = {
            exam_id: sheetForm.exam_id,
            branch_id: sheetForm.branch_id,
            sittings: sheetForm.sittings,
            dates: sheetForm.dates.map((row) => ({
                date: row.date,
                is_holiday: row.is_holiday,
                cells: scheduleColumns.value.flatMap((col) => (row.cells[colKey(col)] || [])
                    .filter((entry) => entry.subject_id)
                    .map((entry) => ({
                        school_class_id: col.school_class_id,
                        section_id: col.section_id,
                        subject_id: entry.subject_id,
                        start_time: entry.start_time || null,
                        end_time: entry.end_time || null,
                    }))),
            })),
        };
        if (editingSheet.value) {
            await client.put(`/exams/schedule-sheets/${editingSheet.value.id}`, payload);
            pushToast('Exam schedule updated.', 'success');
        } else {
            const { data } = await client.post('/exams/schedule-sheets', payload);
            editingSheet.value = data;
            await client.put(`/exams/schedule-sheets/${data.id}`, payload);
            pushToast('Exam schedule created.', 'success');
        }
        closeSheetEdit();
    } finally {
        sheetSaving.value = false;
    }
}

async function removeSheet(sheet) {
    if (!window.confirm('Delete this exam schedule?')) return;
    sheets.value = sheets.value.filter((s) => s.id !== sheet.id);
    await client.delete(`/exams/schedule-sheets/${sheet.id}`);
    pushToast('Exam schedule deleted.', 'success');
}

async function downloadSheetPdf(sheet) {
    try {
        await downloadPdf(`/exams/schedule-sheets/${sheet.id}/pdf`, `exam-schedule-${sheet.id}.pdf`);
    } catch {
        pushToast('Could not open exam schedule PDF.', 'error');
    }
}

onMounted(loadSheets);
</script>
