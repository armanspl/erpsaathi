<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Exam Results</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Individual exam, term combined result, or full annual report card.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="opt in resultTypes"
                :key="opt.id"
                type="button"
                class="rounded-lg px-3.5 py-2 text-sm font-medium transition ring-1 ring-inset"
                :class="resultType === opt.id
                    ? 'bg-primary-600 text-white ring-primary-600'
                    : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700'"
                @click="setResultType(opt.id)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input" @change="onBranchChange">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id" @change="onClassChange">
                            <option :value="null">{{ filters.branch_id ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input" :disabled="!filters.branch_id" @change="load">
                            <option :value="null">All sections</option>
                            <option v-for="s in sectionsForClass(filters.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div v-if="resultType === 'individual' || resultType === 'term'">
                        <label class="form-label">Term</label>
                        <select v-model="filters.term_id" class="form-input" @change="onTermFilter">
                            <option v-if="resultType === 'individual'" :value="null">All terms</option>
                            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div v-if="resultType === 'half_yearly'">
                        <label class="form-label">Term</label>
                        <p class="form-input flex items-center bg-slate-50 text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            {{ halfYearlyTerm ? halfYearlyTerm.name : 'No Half Yearly term found' }}
                        </p>
                    </div>
                    <div v-if="resultType === 'individual'">
                        <label class="form-label">Exam</label>
                        <select v-model="filters.exam_id" class="form-input" @change="load">
                            <option :value="null">Select exam</option>
                            <option v-for="e in filteredExams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="colorPickerExam" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <ExamPdfColorPicker :exam="colorPickerExam" @update="onColorPickerUpdate" />
                    <p class="mt-1 text-xs text-slate-400">Applies to every {{ resultTypeLabel }} PDF downloaded{{ resultType === 'individual' ? ` for ${colorPickerExam.name}` : '' }}.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                Session {{ currentSessionName || '—' }}
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                    {{ resultTypeLabel }}
                </span>
                <span v-if="resultType === 'individual' && exam" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="exam.published_at ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30' : 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/30'">
                    {{ exam.published_at ? 'Published' : 'Live' }}
                </span>
            </p>
            <div class="flex gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!rows.length || zipping" @click="downloadZip">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    {{ zipping ? 'Zipping...' : 'Download all ZIP' }}
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" :disabled="!rows.length || downloading" @click="downloadSheet">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    {{ downloading ? 'Downloading...' : 'Download' }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <button
                    v-for="mode in viewModes"
                    :key="mode.id"
                    type="button"
                    class="rounded-md p-1.5 transition"
                    :class="viewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    :title="mode.label"
                    @click="viewMode = mode.id"
                >
                    <span v-html="mode.icon" />
                </button>
            </div>

            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!rows.length" class="px-6 py-16 text-center text-sm text-slate-400">
                {{ emptyMessage }}
            </div>

            <div v-else class="overflow-x-auto">
                <!-- Individual: subject columns for selected exam -->
                <table v-if="viewMode === 'table' && resultType === 'individual'" class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll No</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Admission ID</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</th>
                            <th v-for="subj in subjectColumns" :key="subj" class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ subj }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">%</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in rows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.roll_no ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td v-for="subj in subjectColumns" :key="subj" class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ subjectMark(r, subj) }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ r.obtained }} / {{ r.max_total }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.percentage }}%</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.grade || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="View" @click="viewReportCard(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download" @click="downloadStudentPdf(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Term / Annual: student summary; View shows breakdown -->
                <table v-else-if="viewMode === 'table'" class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll No</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Admission ID</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ resultType === 'annual' ? 'Overall' : 'Term Total' }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">%</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Rank</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in rows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.roll_no ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ r.obtained }} / {{ r.max_total }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.percentage }}%</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.grade || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.rank ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="View" @click="viewReportCard(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download" @click="downloadStudentPdf(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in rows" :key="r.student_id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ r.name }}</div>
                                <p class="mt-1 text-xs text-slate-400">{{ r.admission_no }} · Roll {{ r.roll_no ?? '—' }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.result === 'Pass' ? 'Active' : 'Inactive')">{{ r.result }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ r.obtained }} / {{ r.max_total }} · {{ r.percentage }}% · Grade {{ r.grade || '—' }}</p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="viewReportCard(r)">View</button>
                            <button type="button" class="btn-outline flex-1 !py-1 !text-xs" @click="downloadStudentPdf(r)">Download</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail modal -->
        <div v-if="reportCard" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <div class="mb-4 text-center">
                    <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ modalTitle }}</p>
                    <p class="text-xs text-slate-400">{{ modalSubtitle }}</p>
                </div>
                <div class="mb-4 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    <p><strong>Name:</strong> {{ reportCard.name }}</p>
                    <p><strong>Adm No:</strong> {{ reportCard.admission_no }}</p>
                    <p><strong>Roll No:</strong> {{ reportCard.roll_no ?? '—' }}</p>
                    <p><strong>Rank:</strong> #{{ reportCard.rank }}</p>
                    <p><strong>Grade:</strong> {{ reportCard.grade || '—' }}</p>
                    <p><strong>Result:</strong> {{ reportCard.result }}</p>
                </div>

                <!-- Individual subjects -->
                <table v-if="resultType === 'individual'" class="mb-4 w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs text-slate-400 dark:border-slate-700">
                            <th class="pb-2 text-left font-medium">Subject</th>
                            <th class="pb-2 text-right font-medium">Marks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in reportCard.subjects" :key="s.subject_id">
                            <td class="py-1.5 text-slate-700 dark:text-slate-200">{{ s.subject_name }}</td>
                            <td class="py-1.5 text-right text-slate-800 dark:text-slate-100">{{ s.marks_obtained ?? '—' }} / {{ s.max_marks }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 font-semibold dark:border-slate-700">
                            <td class="py-2 text-slate-800 dark:text-slate-100">Total</td>
                            <td class="py-2 text-right text-slate-800 dark:text-slate-100">{{ reportCard.obtained }} / {{ reportCard.max_total }} ({{ reportCard.percentage }}%)</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Term matrix: Subject × PT/NB/SEA/TEST/Board/Total -->
                <div v-else-if="resultType === 'term' || resultType === 'half_yearly'" class="mb-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs text-slate-400 dark:border-slate-700">
                                <th class="pb-2 text-left font-medium">Subject</th>
                                <th v-for="col in termColumns" :key="col.key" class="pb-2 text-center font-medium">{{ col.label }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="s in reportCard.subjects" :key="s.subject_id">
                                <td class="py-1.5 font-medium text-slate-700 dark:text-slate-200">{{ s.subject_name }}</td>
                                <td v-for="col in termColumns" :key="col.key" class="py-1.5 text-center text-slate-800 dark:text-slate-100">
                                    {{ termCell(s, col) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-slate-400">TEST = PT + NB + SEA (display only). Term Total = TEST + board exam — not double-counted.</p>
                </div>

                <!-- Annual: per-subject overall -->
                <div v-else class="mb-4 overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs text-slate-400 dark:border-slate-700">
                                <th class="pb-2 text-left font-medium">Subject</th>
                                <th v-for="(t, i) in (reportCard.subjects?.[0]?.terms || [])" :key="t.term_id" class="pb-2 text-center font-medium">{{ t.term_name || ('Term-' + (i + 1)) }}</th>
                                <th class="pb-2 text-center font-medium">Overall</th>
                                <th class="pb-2 text-center font-medium">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="s in reportCard.subjects" :key="s.subject_id">
                                <td class="py-1.5 font-medium text-slate-700 dark:text-slate-200">{{ s.subject_name }}</td>
                                <td v-for="t in (s.terms || [])" :key="t.term_id" class="py-1.5 text-center text-slate-800 dark:text-slate-100">{{ t.total ?? '—' }}</td>
                                <td class="py-1.5 text-center font-semibold text-slate-800 dark:text-slate-100">{{ s.overall ?? '—' }}</td>
                                <td class="py-1.5 text-center text-slate-800 dark:text-slate-100">{{ s.grade || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-slate-400">Overall = average of Term-1 Total and Term-2 Total. Download PDF for full PT/NB/SEA/TEST/board columns.</p>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="btn-outline flex-1" @click="reportCard = null">Close</button>
                    <button type="button" class="btn-primary flex-1" @click="downloadStudentPdf(reportCard)">Download</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';
import { erpStore } from '../../store';
import { statusBadgeClass } from '../../utils/colors';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import ExamPdfColorPicker from '../../components/ExamPdfColorPicker.vue';

const resultTypes = [
    { id: 'individual', label: 'Individual Exam' },
    { id: 'term', label: 'Term Result' },
    { id: 'half_yearly', label: 'Half Yearly Result' },
    { id: 'annual', label: 'Annual Result' },
];
const resultType = ref('individual');

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'compact', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];
const viewMode = ref('table');
const filtersOpen = ref(true);

const exams = ref([]);
const terms = ref([]);
const termColumns = ref([]);
const annualColumns = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const currentSessionName = ref('');

const filters = reactive({ exam_id: null, term_id: null, branch_id: null, school_class_id: null, section_id: null });
const rows = ref([]);
const loading = ref(false);
const reportCard = ref(null);
const zipping = ref(false);
const downloading = ref(false);

// Term Result / Half Yearly Result and Annual Result don't have a single "exam" the user picked
// — their PDFs are coloured by whichever exam AcademicTermController::anchorExam() /
// AnnualReportController::anchorExam() resolves to server-side, so the colour picker for those
// tabs targets that resolved exam instead (fetched via the anchor-exam endpoints below).
const termAnchorExam = ref(null);
const annualAnchorExam = ref(null);

const exam = computed(() => exams.value.find((e) => e.id === filters.exam_id));
const filteredExams = computed(() => {
    if (filters.term_id == null) return exams.value;
    return exams.value.filter((e) => e.academic_term_id === filters.term_id);
});
const resultTypeLabel = computed(() => resultTypes.find((t) => t.id === resultType.value)?.label || '');
const modalTitle = computed(() => {
    if (resultType.value === 'term') return 'Term Result';
    if (resultType.value === 'half_yearly') return 'Half Yearly Result';
    if (resultType.value === 'annual') return 'Annual Result';
    return 'Report Card';
});
const modalSubtitle = computed(() => {
    if (resultType.value === 'individual') return exam.value?.name || '';
    if (resultType.value === 'term' || resultType.value === 'half_yearly') return terms.value.find((t) => t.id === filters.term_id)?.name || '';
    return currentSessionName.value || 'Session';
});

// The term that carries the Half Yearly / Mid Term exam (set up via Exams > Terms, same
// pattern AcademicTermController::setupSession() uses to auto-assign exams into Term-1).
// Falls back to the first configured term so schools that didn't name it "Half Yearly"
// still get a sensible default rather than an empty tab.
const halfYearlyTerm = computed(() => {
    return terms.value.find((t) => (t.exams || []).some((e) => /half\s*year|mid\s*term/i.test(e.name)))
        ?? terms.value[0]
        ?? null;
});

function onExamColorUpdate(updated) {
    const idx = exams.value.findIndex((e) => e.id === updated.id);
    if (idx !== -1) exams.value[idx] = { ...exams.value[idx], ...updated };
}

const colorPickerExam = computed(() => {
    if (resultType.value === 'individual') return exam.value || null;
    if (resultType.value === 'term' || resultType.value === 'half_yearly') return termAnchorExam.value;
    if (resultType.value === 'annual') return annualAnchorExam.value;
    return null;
});

function onColorPickerUpdate(updated) {
    if (resultType.value === 'individual') {
        onExamColorUpdate(updated);
    } else if (resultType.value === 'term' || resultType.value === 'half_yearly') {
        termAnchorExam.value = updated;
    } else if (resultType.value === 'annual') {
        annualAnchorExam.value = updated;
    }
}

async function loadTermAnchorExam(termId) {
    if (!termId) {
        termAnchorExam.value = null;
        return;
    }
    try {
        const { data } = await client.get(`/exams/terms/${termId}/anchor-exam`);
        termAnchorExam.value = data;
    } catch {
        termAnchorExam.value = null;
    }
}

async function loadAnnualAnchorExam() {
    try {
        const { data } = await client.get('/exams/annual-report/anchor-exam');
        annualAnchorExam.value = data;
    } catch {
        annualAnchorExam.value = null;
    }
}

const allFiltersSet = computed(() => {
    const base = !!filters.branch_id;
    if (resultType.value === 'individual') return base && !!filters.exam_id;
    if (resultType.value === 'term' || resultType.value === 'half_yearly') return base && !!filters.term_id;
    return base;
});

const subjectColumns = computed(() => (rows.value[0]?.subjects || []).map((s) => s.subject_name));

const emptyMessage = computed(() => {
    if (!allFiltersSet.value) {
        if (resultType.value === 'individual') return 'Select exam and branch to load results.';
        if (resultType.value === 'term') return 'Select branch and term to load term results.';
        if (resultType.value === 'half_yearly') return halfYearlyTerm.value ? 'Select a branch to load Half Yearly results.' : 'No Half Yearly term found. Set up Term-1 with a Half Yearly exam under Exams > Terms.';
        return 'Select a branch to load annual results.';
    }
    if (resultType.value === 'term') return 'No marks for this term yet. Ensure PT/NB/SEA/board exams are linked and marked.';
    if (resultType.value === 'half_yearly') return 'No Half Yearly results yet. Ensure PT-1/NB-1/SEA-1/Half Yearly exams are linked and marked.';
    if (resultType.value === 'annual') return 'No annual results. Ensure Term-1 and Term-2 have assessments and marks.';
    return 'No marks entered for this exam yet.';
});

function subjectMark(row, subjectName) {
    const subject = row.subjects.find((s) => s.subject_name === subjectName);
    return subject?.marks_obtained ?? '—';
}

function termCell(subject, col) {
    if (col.type === 'test_total') return subject.test_total ?? '—';
    if (col.type === 'term_total') return subject.marks_obtained ?? '—';
    const key = col.key;
    const val = subject.cells?.[key];
    return val === null || val === undefined || val === '' ? '—' : val;
}

function sectionsForClass(classId) {
    if (!classId) return sections.value;
    return sections.value.filter((s) => s.school_class_id === classId);
}

function filterParams() {
    const params = { branch_id: filters.branch_id };
    if (filters.school_class_id) params.school_class_id = filters.school_class_id;
    if (filters.section_id) params.section_id = filters.section_id;
    return params;
}

async function loadLookups() {
    const [academics, sessionsRes, examsRes, termsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/exams'),
        client.get('/exams/terms').catch(() => ({ data: [] })),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    exams.value = examsRes.data;
    terms.value = termsRes.data || [];
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
}
loadLookups();

function setResultType(type) {
    resultType.value = type;
    reportCard.value = null;
    rows.value = [];
    termColumns.value = [];
    annualColumns.value = [];
    if (type === 'term' && !filters.term_id && terms.value.length) {
        filters.term_id = terms.value[0].id;
    }
    if (type === 'half_yearly') {
        filters.term_id = halfYearlyTerm.value?.id ?? null;
    }
    if (type === 'annual') {
        filters.exam_id = null;
    }
    // The PDF colour picker doesn't depend on branch/class/section filters, so it's loaded
    // independently of load() (which waits for those) — same reason Individual's picker already
    // shows as soon as an exam is picked, not only once results have loaded.
    if (type === 'term' || type === 'half_yearly') {
        loadTermAnchorExam(filters.term_id);
    } else if (type === 'annual') {
        loadAnnualAnchorExam();
    }
    load();
}

function onTermFilter() {
    if (resultType.value === 'individual') {
        if (filters.exam_id && !filteredExams.value.some((e) => e.id === filters.exam_id)) {
            filters.exam_id = null;
            rows.value = [];
        }
        return;
    }
    if (resultType.value === 'term') {
        loadTermAnchorExam(filters.term_id);
    }
    load();
}
function onBranchChange() {
    filters.school_class_id = null;
    filters.section_id = null;
    load();
}
function onClassChange() {
    filters.section_id = null;
    load();
}

async function load() {
    reportCard.value = null;
    if (!allFiltersSet.value) {
        rows.value = [];
        return;
    }
    loading.value = true;
    try {
        if (resultType.value === 'individual') {
            const { data } = await client.get(`/exams/${filters.exam_id}/results`, { params: filterParams() });
            rows.value = data;
            termColumns.value = [];
        } else if (resultType.value === 'term' || resultType.value === 'half_yearly') {
            const { data } = await client.get(`/exams/terms/${filters.term_id}/results`, { params: filterParams() });
            rows.value = data.rows || [];
            termColumns.value = data.columns || [];
        } else {
            const { data } = await client.get('/exams/annual-report', { params: filterParams() });
            rows.value = data.rows || [];
            annualColumns.value = data.columns || [];
        }
    } catch (e) {
        rows.value = [];
        pushToast(e?.response?.data?.message || 'Could not load results.', 'error');
    } finally {
        loading.value = false;
    }
}

function viewReportCard(row) {
    reportCard.value = row;
}

async function downloadStudentPdf(row) {
    try {
        if (resultType.value === 'individual') {
            await downloadPdf(`/exams/${filters.exam_id}/results/${row.student_id}/pdf`, `report-card-${row.admission_no}.pdf`);
        } else if (resultType.value === 'term' || resultType.value === 'half_yearly') {
            const prefix = resultType.value === 'half_yearly' ? 'half-yearly-report' : 'term-report';
            await downloadPdf(
                `/exams/terms/${filters.term_id}/results/${row.student_id}/pdf`,
                `${prefix}-${row.admission_no}.pdf`,
                filterParams(),
            );
        } else {
            await downloadPdf(`/exams/annual-report/${row.student_id}/pdf`, `annual-report-card-${row.admission_no}.pdf`);
        }
    } catch (e) {
        pushToast('Could not download PDF.', 'error');
    }
}

async function downloadSheet() {
    downloading.value = true;
    try {
        if (resultType.value === 'individual') {
            await downloadPdf(`/exams/${filters.exam_id}/results/pdf`, `exam-results-${filters.exam_id}.pdf`, filterParams());
        } else if (resultType.value === 'term' || resultType.value === 'half_yearly') {
            const prefix = resultType.value === 'half_yearly' ? 'half-yearly-results' : 'term-results';
            await downloadPdf(`/exams/terms/${filters.term_id}/results/pdf`, `${prefix}-${filters.term_id}.pdf`, filterParams());
        } else {
            // Annual has no class sheet — download ZIP of report cards instead
            await downloadZip();
        }
    } catch (e) {
        pushToast('Could not download sheet.', 'error');
    } finally {
        downloading.value = false;
    }
}

async function downloadZip() {
    zipping.value = true;
    try {
        let url;
        let filename;
        if (resultType.value === 'individual') {
            url = `/exams/${filters.exam_id}/results/zip`;
            filename = `exam-results-${filters.exam_id}.zip`;
        } else if (resultType.value === 'term' || resultType.value === 'half_yearly') {
            url = `/exams/terms/${filters.term_id}/results/zip`;
            filename = resultType.value === 'half_yearly'
                ? `half-yearly-report-cards-${filters.term_id}.zip`
                : `term-report-cards-${filters.term_id}.zip`;
        } else {
            url = '/exams/annual-report/zip';
            filename = 'annual-report-cards.zip';
        }
        const response = await client.get(url, { params: filterParams(), responseType: 'blob' });
        triggerBlobDownload(new Blob([response.data], { type: 'application/zip' }), filename);
    } catch (e) {
        pushToast('Could not generate ZIP for the selected filters.', 'error');
    } finally {
        zipping.value = false;
    }
}
</script>
