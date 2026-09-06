<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Exam Management</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage exam definitions, schedules, and grading ranges.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="window.print()">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v8H6Z"/></svg>
                    Print
                </button>
                <button v-if="activeTab === 'list'" type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreateExam">
                    <span class="text-lg leading-none">+</span> Create Exam
                </button>
                <button v-else-if="activeTab === 'grades'" type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreateGrade">
                    <span class="text-lg leading-none">+</span> Create Grade
                </button>
            </div>
        </div>

        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                @click="onTabClick(tab.id)"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- EXAM LIST -->
        <template v-if="activeTab === 'list'">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                        Filters
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                    <label class="form-label">Search</label>
                    <input v-model="examFilters.search" type="search" class="form-input max-w-sm" placeholder="Search exams..." />
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

                <div v-if="examsLoading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!pagedExams.length" class="px-6 py-20 text-center text-sm text-slate-400">No exams match your filters.</div>

                <div v-else class="overflow-x-auto">
                    <table v-if="viewMode === 'table'" class="w-full min-w-[880px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Exam {{ sortArrow('name') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Term</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('type')">Type {{ sortArrow('type') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('total_marks')">Total {{ sortArrow('total_marks') }}</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('passing_marks')">Passing {{ sortArrow('passing_marks') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Range</th>
                                <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('created_at')">Created {{ sortArrow('created_at') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="e in pagedExams" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedExamIds.has(e.id)" @change="toggleOne(e.id, $event.target.checked)" /></td>
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ e.name }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.academic_term?.name || '\u2014' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    <span v-if="e.type" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ e.type }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ e.total_marks ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ e.passing_marks ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.min_marks ?? '—' }} - {{ e.max_marks ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(e.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEditExam(e)">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                        </button>
                                        <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeExam(e)">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="e in pagedExams" :key="e.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <div>
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ e.name }} <span class="text-xs font-normal text-slate-400">· {{ e.type || '—' }}</span></div>
                                <div class="text-xs text-slate-400">Total {{ e.total_marks ?? '—' }} · Passing {{ e.passing_marks ?? '—' }} · Range {{ e.min_marks ?? '—' }}-{{ e.max_marks ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="e in pagedExams" :key="e.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ e.name }}</div>
                            <p class="mt-2 text-xs text-slate-400">{{ e.type || '—' }} · Created {{ formatDate(e.created_at) }}</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Total {{ e.total_marks ?? '—' }} · Passing {{ e.passing_marks ?? '—' }}</p>
                            <p class="text-xs text-slate-400">Range {{ e.min_marks ?? '—' }} - {{ e.max_marks ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <p class="text-xs text-slate-400">Showing {{ examsShowingFrom }}“{{ examsShowingTo }} of {{ filteredExams.length }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <select v-model.number="examsPerPage" class="form-input !w-auto !py-1.5 !text-xs">
                            <option :value="10">10 / page</option>
                            <option :value="20">20 / page</option>
                            <option :value="50">50 / page</option>
                        </select>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="examsPage <= 1" @click="examsPage -= 1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span>Page {{ examsPage }} of {{ examsTotalPages }}</span>
                            <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="examsPage >= examsTotalPages" @click="examsPage += 1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- EXAM GRADES -->
        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="gradeFiltersOpen = !gradeFiltersOpen">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                        Filters
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition" :class="gradeFiltersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-show="gradeFiltersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                    <label class="form-label">Search</label>
                    <input v-model="gradeFilters.search" type="search" class="form-input max-w-sm" placeholder="Search grades..." />
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                    <button
                        v-for="mode in viewModes"
                        :key="mode.id"
                        type="button"
                        class="rounded-md p-1.5 transition"
                        :class="gradeViewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                        :title="mode.label"
                        @click="gradeViewMode = mode.id"
                    >
                        <span v-html="mode.icon" />
                    </button>
                </div>

                <div v-if="gradesLoading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!pagedGrades.length" class="px-6 py-20 text-center text-sm text-slate-400">No grade bands defined yet.</div>

                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allGradesSelected" @change="toggleAllGrades($event.target.checked)" /></th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleGradeSort('grade')">Grade {{ gradeSortArrow('grade') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleGradeSort('min_percentage')">Min % {{ gradeSortArrow('min_percentage') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleGradeSort('max_percentage')">Max % {{ gradeSortArrow('max_percentage') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleGradeSort('created_at')">Created {{ gradeSortArrow('created_at') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="g in pagedGrades" :key="g.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedGradeIds.has(g.id)" @change="toggleOneGrade(g.id, $event.target.checked)" /></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ g.grade }}</span></td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ g.min_percentage }}%</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ g.max_percentage }}%</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(g.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEditGrade(g)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeGrade(g)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <p class="text-xs text-slate-400">Showing {{ gradesShowingFrom }}“{{ gradesShowingTo }} of {{ filteredGrades.length }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <select v-model.number="gradesPerPage" class="form-input !w-auto !py-1.5 !text-xs">
                            <option :value="10">10 / page</option>
                            <option :value="20">20 / page</option>
                            <option :value="50">50 / page</option>
                        </select>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="gradesPage <= 1" @click="gradesPage -= 1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span>Page {{ gradesPage }} of {{ gradesTotalPages }}</span>
                            <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="gradesPage >= gradesTotalPages" @click="gradesPage += 1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Create / Edit Exam modal -->
        <div v-if="examFormOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="examFormOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editingExam ? 'Edit Exam' : 'Create Exam' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Define an exam name, type, and marks policy.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="examFormOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="examForm.name" type="text" class="form-input" placeholder="e.g. PT-1" />
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <input v-model="examForm.type" type="text" class="form-input" placeholder="unit" />
                    </div>
                    <div>
                        <label class="form-label">Term</label>
                        <select v-model="examForm.academic_term_id" class="form-input">
                            <option :value="null">Standalone (no term)</option>
                            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Sort order</label>
                        <input v-model.number="examForm.sort_order" type="number" min="0" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Total Marks</label>
                        <input v-model.number="examForm.total_marks" type="number" min="0" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Passing Marks</label>
                        <input v-model.number="examForm.passing_marks" type="number" min="0" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Minimum Marks</label>
                        <input v-model.number="examForm.min_marks" type="number" min="0" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Maximum Marks</label>
                        <input v-model.number="examForm.max_marks" type="number" min="0" class="form-input" />
                    </div>
                    <div class="col-span-2 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input v-model="examForm.counts_toward_term" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Counts toward term total
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input v-model="examForm.is_internal_component" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Internal component (rolls into Test subtotal)
                        </label>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Description</label>
                        <textarea v-model="examForm.description" rows="3" class="form-input" placeholder="Optional notes for this exam." />
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="examFormOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="examSaving" @click="saveExam">{{ examSaving ? 'Saving...' : editingExam ? 'Save' : 'Create' }}</button>
                </div>
            </div>
        </div>

        <!-- Create / Edit Exam Grade modal -->
        <div v-if="gradeFormOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="gradeFormOpen = false" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editingGrade ? 'Edit Exam Grade' : 'Create Exam Grade' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Define a non-overlapping percentage range.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="gradeFormOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Grade</label>
                        <input v-model="gradeForm.grade" type="text" class="form-input" placeholder="e.g. A+" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Minimum %</label>
                            <input v-model.number="gradeForm.min_percentage" type="number" step="0.01" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Maximum %</label>
                            <input v-model.number="gradeForm.max_percentage" type="number" step="0.01" class="form-input" />
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="gradeFormOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="gradeSaving" @click="saveGrade">{{ gradeSaving ? 'Saving...' : editingGrade ? 'Save' : 'Create' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();

const tabs = [
    { id: 'list', label: 'Exam List' },
    { id: 'schedule', label: 'Exam Schedule' },
    { id: 'grades', label: 'Exam Grades' },
];
const activeTab = ref('list');

function onTabClick(tabId) {
    if (tabId === 'schedule') {
        router.push('/exam-management/exam-schedule');
        return;
    }
    activeTab.value = tabId;
}

onMounted(() => {
    if (route.query.tab === 'grades') activeTab.value = 'grades';
});

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];
const viewMode = ref('table');
const filtersOpen = ref(true);

// --- Exam List ---
const exams = ref([]);
const terms = ref([]);
const examsLoading = ref(true);
const examFilters = reactive({ search: '' });
const examsPage = ref(1);
const examsPerPage = ref(20);
const examSortKey = ref('created_at');
const examSortDir = ref('desc');
const selectedExamIds = ref(new Set());

const examFormOpen = ref(false);
const editingExam = ref(null);
const examSaving = ref(false);
const examForm = reactive({
    name: '',
    type: '',
    total_marks: '',
    passing_marks: '',
    min_marks: '',
    max_marks: '',
    description: '',
    academic_term_id: null,
    sort_order: 0,
    counts_toward_term: true,
    is_internal_component: false,
});

const filteredExams = computed(() => {
    let rows = exams.value;
    if (examFilters.search.trim()) {
        const term = examFilters.search.trim().toLowerCase();
        rows = rows.filter((e) => `${e.name} ${e.type || ''}`.toLowerCase().includes(term));
    }
    return [...rows].sort((a, b) => {
        let av = a[examSortKey.value] ?? '';
        let bv = b[examSortKey.value] ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return examSortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return examSortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});
const examsTotalPages = computed(() => Math.max(1, Math.ceil(filteredExams.value.length / examsPerPage.value)));
const pagedExams = computed(() => {
    const start = (examsPage.value - 1) * examsPerPage.value;
    return filteredExams.value.slice(start, start + examsPerPage.value);
});
const examsShowingFrom = computed(() => (filteredExams.value.length ? (examsPage.value - 1) * examsPerPage.value + 1 : 0));
const examsShowingTo = computed(() => Math.min(examsPage.value * examsPerPage.value, filteredExams.value.length));
const allSelected = computed(() => pagedExams.value.length > 0 && pagedExams.value.every((e) => selectedExamIds.value.has(e.id)));

function toggleSort(key) {
    if (examSortKey.value === key) {
        examSortDir.value = examSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        examSortKey.value = key;
        examSortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (examSortKey.value !== key) return '';
    return examSortDir.value === 'asc' ? '↑' : '↓';
}
function toggleAll(checked) {
    if (checked) pagedExams.value.forEach((e) => selectedExamIds.value.add(e.id));
    else pagedExams.value.forEach((e) => selectedExamIds.value.delete(e.id));
}
function toggleOne(id, checked) {
    if (checked) selectedExamIds.value.add(id);
    else selectedExamIds.value.delete(id);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadExams() {
    examsLoading.value = true;
    try {
        const [{ data }, termsRes] = await Promise.all([
            client.get('/exams'),
            client.get('/exams/terms').catch(() => ({ data: [] })),
        ]);
        exams.value = data;
        terms.value = termsRes.data || [];
    } finally {
        examsLoading.value = false;
    }
}

function openCreateExam() {
    editingExam.value = null;
    Object.assign(examForm, {
        name: '',
        type: '',
        total_marks: '',
        passing_marks: '',
        min_marks: '',
        max_marks: '',
        description: '',
        academic_term_id: null,
        sort_order: 0,
        counts_toward_term: true,
        is_internal_component: false,
    });
    examFormOpen.value = true;
}

function openEditExam(exam) {
    editingExam.value = exam;
    Object.assign(examForm, {
        name: exam.name,
        type: exam.type || '',
        total_marks: exam.total_marks ?? '',
        passing_marks: exam.passing_marks ?? '',
        min_marks: exam.min_marks ?? '',
        max_marks: exam.max_marks ?? '',
        description: exam.description || '',
        academic_term_id: exam.academic_term_id ?? null,
        sort_order: exam.sort_order ?? 0,
        counts_toward_term: exam.counts_toward_term !== false,
        is_internal_component: !!exam.is_internal_component,
    });
    examFormOpen.value = true;
}

async function saveExam() {
    if (!examForm.name.trim()) {
        pushToast('Name is required.', 'error');
        return;
    }
    examSaving.value = true;
    try {
        const payload = { ...examForm };
        if (editingExam.value) {
            await client.put(`/exams/${editingExam.value.id}`, payload);
            pushToast('Exam updated.', 'success');
        } else {
            await client.post('/exams', payload);
            pushToast('Exam created.', 'success');
        }
        examFormOpen.value = false;
        await loadExams();
    } finally {
        examSaving.value = false;
    }
}

async function removeExam(exam) {
    if (!window.confirm(`Delete exam "${exam.name}"?`)) return;
    exams.value = exams.value.filter((e) => e.id !== exam.id);
    await client.delete(`/exams/${exam.id}`);
    pushToast(`Exam "${exam.name}" deleted.`, 'success');
}

// --- Exam Grades ---
const grades = ref([]);
const gradesLoading = ref(true);
const gradeFiltersOpen = ref(true);
const gradeViewMode = ref('table');
const gradeFilters = reactive({ search: '' });
const gradesPage = ref(1);
const gradesPerPage = ref(20);
const gradeSortKey = ref('min_percentage');
const gradeSortDir = ref('desc');
const selectedGradeIds = ref(new Set());

const gradeFormOpen = ref(false);
const editingGrade = ref(null);
const gradeSaving = ref(false);
const gradeForm = reactive({ grade: '', min_percentage: null, max_percentage: null });

const filteredGrades = computed(() => {
    let rows = grades.value;
    if (gradeFilters.search.trim()) {
        const term = gradeFilters.search.trim().toLowerCase();
        rows = rows.filter((g) => g.grade.toLowerCase().includes(term));
    }
    return [...rows].sort((a, b) => {
        let av = a[gradeSortKey.value] ?? '';
        let bv = b[gradeSortKey.value] ?? '';
        if (typeof av === 'string' && gradeSortKey.value === 'grade') av = av.toLowerCase();
        if (typeof bv === 'string' && gradeSortKey.value === 'grade') bv = bv.toLowerCase();
        if (av < bv) return gradeSortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return gradeSortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});
const gradesTotalPages = computed(() => Math.max(1, Math.ceil(filteredGrades.value.length / gradesPerPage.value)));
const pagedGrades = computed(() => {
    const start = (gradesPage.value - 1) * gradesPerPage.value;
    return filteredGrades.value.slice(start, start + gradesPerPage.value);
});
const gradesShowingFrom = computed(() => (filteredGrades.value.length ? (gradesPage.value - 1) * gradesPerPage.value + 1 : 0));
const gradesShowingTo = computed(() => Math.min(gradesPage.value * gradesPerPage.value, filteredGrades.value.length));
const allGradesSelected = computed(() => pagedGrades.value.length > 0 && pagedGrades.value.every((g) => selectedGradeIds.value.has(g.id)));

function toggleGradeSort(key) {
    if (gradeSortKey.value === key) {
        gradeSortDir.value = gradeSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        gradeSortKey.value = key;
        gradeSortDir.value = 'asc';
    }
}
function gradeSortArrow(key) {
    if (gradeSortKey.value !== key) return '';
    return gradeSortDir.value === 'asc' ? '↑' : '↓';
}
function toggleAllGrades(checked) {
    if (checked) pagedGrades.value.forEach((g) => selectedGradeIds.value.add(g.id));
    else pagedGrades.value.forEach((g) => selectedGradeIds.value.delete(g.id));
}
function toggleOneGrade(id, checked) {
    if (checked) selectedGradeIds.value.add(id);
    else selectedGradeIds.value.delete(id);
}

async function loadGrades() {
    gradesLoading.value = true;
    try {
        const { data } = await client.get('/exams/grade-system');
        grades.value = data;
    } finally {
        gradesLoading.value = false;
    }
}

function openCreateGrade() {
    editingGrade.value = null;
    Object.assign(gradeForm, { grade: '', min_percentage: null, max_percentage: null });
    gradeFormOpen.value = true;
}

function openEditGrade(grade) {
    editingGrade.value = grade;
    Object.assign(gradeForm, { grade: grade.grade, min_percentage: Number(grade.min_percentage), max_percentage: Number(grade.max_percentage) });
    gradeFormOpen.value = true;
}

async function saveGrade() {
    gradeSaving.value = true;
    try {
        if (editingGrade.value) {
            await client.put(`/exams/grade-system/${editingGrade.value.id}`, gradeForm);
            pushToast('Grade updated.', 'success');
        } else {
            await client.post('/exams/grade-system', gradeForm);
            pushToast('Grade created.', 'success');
        }
        gradeFormOpen.value = false;
        await loadGrades();
    } finally {
        gradeSaving.value = false;
    }
}

async function removeGrade(grade) {
    if (!window.confirm(`Delete grade "${grade.grade}"?`)) return;
    grades.value = grades.value.filter((g) => g.id !== grade.id);
    await client.delete(`/exams/grade-system/${grade.id}`);
    pushToast(`Grade "${grade.grade}" deleted.`, 'success');
}

watch(examsPerPage, () => { examsPage.value = 1; });
watch(examsTotalPages, (n) => { if (examsPage.value > n) examsPage.value = n; });
watch(() => examFilters.search, () => { examsPage.value = 1; });

watch(gradesPerPage, () => { gradesPage.value = 1; });
watch(gradesTotalPages, (n) => { if (gradesPage.value > n) gradesPage.value = n; });
watch(() => gradeFilters.search, () => { gradesPage.value = 1; });

watch(activeTab, (tab) => {
    if (tab === 'grades' && !grades.value.length) loadGrades();
});

loadExams();
</script>
