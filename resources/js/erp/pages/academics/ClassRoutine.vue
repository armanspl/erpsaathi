<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Class Routine</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Class Routine']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Build the weekly class timetable, import it from an Excel sheet, or export it with the same colours.</p>
            </div>
            <div v-if="view === 'list'" class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openImportModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Import Excel
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Routine
                </button>
            </div>
        </div>

        <!-- LIST VIEW -->
        <template v-if="view === 'list'">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="sheetsLoading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!sheets.length" class="px-6 py-20 text-center text-sm text-slate-400">No class routine yet — create one or import an Excel file.</div>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entries</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in sheets" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.title || 'Class Routine' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.academic_session?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.branch?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.entries_count }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(s.updated_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="View (class wise / teacher wise / free teachers)" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSheet(s, 'view')">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSheet(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Export" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openExportModal(s)">
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

        <!-- EDIT VIEW -->
        <template v-else>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-xs" @click="closeEdit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ mode === 'view' ? (form.title || 'Class Routine') : editingSheet ? 'Edit Class Routine' : 'Create Class Routine' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ sheetSubtitle }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
                            <button
                                v-for="m in [{ id: 'edit', label: 'Edit' }, { id: 'view', label: 'View' }]"
                                :key="m.id"
                                type="button"
                                class="rounded-md px-3 py-1 text-xs font-semibold transition"
                                :class="mode === m.id ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                @click="mode = m.id"
                            >
                                {{ m.label }}
                            </button>
                        </div>
                        <button v-if="editingSheet" type="button" class="btn-outline !text-xs" @click="openImportModal">Re-import Excel</button>
                        <button v-if="editingSheet" type="button" class="btn-outline !text-xs" @click="openExportModal(editingSheet)">Export ▾</button>
                        <button type="button" class="btn-primary" :disabled="sheetSaving" @click="saveSheet">{{ sheetSaving ? 'Saving...' : 'Save' }}</button>
                    </div>
                </div>

                <div v-if="mode === 'edit'" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="form-label">Academic Session</label>
                        <select v-model.number="form.academic_session_id" class="form-input" :disabled="!!editingSheet">
                            <option :value="null">Select session</option>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model.number="form.branch_id" class="form-input" :disabled="!!editingSheet">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Title</label>
                        <input v-model="form.title" type="text" class="form-input" placeholder="e.g. Class Routine 2026-27" />
                    </div>
                    <div>
                        <label class="form-label">Periods / day</label>
                        <input v-model.number="form.periods_per_day" type="number" min="1" max="20" class="form-input" @change="onPeriodsChange" />
                    </div>
                </div>
            </div>

            <div v-if="importWarnings" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Review before saving — this routine was pre-filled from an Excel import</p>
                    <button type="button" class="shrink-0 text-xs font-medium text-amber-700 hover:underline dark:text-amber-400" @click="importWarnings = null">Dismiss</button>
                </div>
                <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-300">
                    <li v-if="importWarnings.sheets_skipped?.length">Sheet(s) not imported (not a Mon.../Thu... day-group grid): {{ importWarnings.sheets_skipped.join(', ') }}</li>
                    <li v-if="importWarnings.unmatched_classes?.length">These class labels don't match any class in your system, so their rows were skipped: {{ importWarnings.unmatched_classes.join(', ') }}. Add the class first, then re-import.</li>
                    <li v-if="importWarnings.unmatched_subjects?.length">{{ importWarnings.unmatched_subjects.length }} subject text(s) couldn't be matched to your subject list — shown as plain text in the grid (marked ⚠), pick the right one from the dropdown or add it under Academics → Subjects: {{ importWarnings.unmatched_subjects.join(', ') }}</li>
                    <li v-if="importWarnings.unmatched_teachers?.length">{{ importWarnings.unmatched_teachers.length }} teacher name(s) couldn't be matched to your teacher list — shown as plain text (marked ⚠), pick the right one or add them under People → Teachers: {{ importWarnings.unmatched_teachers.join(', ') }}</li>
                </ul>
            </div>

            <ClassRoutineView
                v-if="mode === 'view'"
                :sheet="viewSheet"
                :classes="classes"
                :subjects="subjects"
                :teachers="teachers"
                :school-name="erpStore.school?.school_name || ''"
                :subtitle="sheetSubtitle"
            />

            <div v-else class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <nav class="flex flex-wrap gap-1">
                        <button
                            v-for="d in DAYS"
                            :key="d.dow"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeDay === d.dow ? 'bg-primary-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                            @click="activeDay = d.dow"
                        >
                            {{ d.label }}
                        </button>
                    </nav>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
                            <button
                                v-for="tab in [{ id: 'class', label: 'Class wise' }, { id: 'teacher', label: 'Teacher wise' }]"
                                :key="tab.id"
                                type="button"
                                class="rounded-md px-3 py-1 text-xs font-semibold transition"
                                :class="editLayout === tab.id ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                @click="editLayout = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <label class="inline-flex items-center gap-1.5 text-slate-500">
                            <span>Periods today</span>
                            <input
                                type="number"
                                min="1"
                                max="20"
                                class="form-input !w-16 !py-1 !text-xs"
                                :value="periodsForDay(activeDay)"
                                @change="setDayPeriods(activeDay, $event.target.value)"
                                title="e.g. 5 for half day, 7 for full day — applies only to this weekday"
                            />
                        </label>
                        <span class="text-slate-400">Copy {{ dayLabel(activeDay) }} to:</span>
                        <select v-model="copyTargetDay" class="form-input !py-1 !text-xs">
                            <option :value="null">Select day</option>
                            <option v-for="d in DAYS.filter((x) => x.dow !== activeDay)" :key="d.dow" :value="d.dow">{{ d.label }}</option>
                        </select>
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="!copyTargetDay" @click="copyDay">Copy</button>
                    </div>
                </div>

                <!-- CLASS WISE EDIT -->
                <div v-if="editLayout === 'class'" class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full border-collapse text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Class</th>
                                <th v-for="p in periodsRange" :key="p" class="whitespace-nowrap px-2 py-2 text-center font-semibold text-slate-500">
                                    <div class="flex flex-col items-center gap-1">
                                        <span>P{{ p }}</span>
                                        <button
                                            type="button"
                                            class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide transition"
                                            :class="isPeriodAllFree(p)
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-white text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50 dark:bg-slate-900 dark:text-emerald-300 dark:ring-emerald-500/30'"
                                            :title="isPeriodAllFree(p) ? 'Clear FREE for all classes in this period' : 'Mark this whole period FREE for every class'"
                                            @click="togglePeriodFree(p)"
                                        >
                                            {{ isPeriodAllFree(p) ? '✓ Free all' : 'Free all' }}
                                        </button>
                                    </div>
                                </th>
                                <th class="whitespace-nowrap px-2 py-2 font-semibold text-slate-500">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!classes.length">
                                <td :colspan="periodsRange.length + 2" class="px-3 py-6 text-center text-slate-400">No classes found — add classes under Academics → Classes &amp; Sections first.</td>
                            </tr>
                            <tr v-for="c in classes" :key="c.id">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 align-top font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ c.name }}</td>
                                <td v-for="p in periodsRange" :key="p" class="min-w-[130px] px-1.5 py-1.5 align-top">
                                    <div
                                        class="space-y-1 rounded-lg border p-1.5"
                                        :class="cell(c.id, p).is_free
                                            ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10'
                                            : cellHasUnmatched(c.id, p)
                                                ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10'
                                                : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'"
                                    >
                                        <button
                                            type="button"
                                            class="w-full rounded px-1 py-0.5 text-[10px] font-bold uppercase tracking-wide transition"
                                            :class="cell(c.id, p).is_free
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-white text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50 dark:bg-slate-900 dark:text-emerald-300 dark:ring-emerald-500/30'"
                                            :title="cell(c.id, p).is_free ? 'Clear FREE (assign subject/teacher again)' : 'Mark this class period as FREE'"
                                            @click="toggleFree(c.id, p)"
                                        >
                                            {{ cell(c.id, p).is_free ? '✓ Free' : 'Free' }}
                                        </button>
                                        <template v-if="!cell(c.id, p).is_free">
                                            <select v-model="cell(c.id, p).subject_id" class="form-input !py-1 !text-[11px]" @change="cell(c.id, p).subject_label = null">
                                                <option :value="null">{{ cell(c.id, p).subject_label ? `⚠ ${cell(c.id, p).subject_label}` : '— subject —' }}</option>
                                                <option v-for="sub in subjects" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                                            </select>
                                            <select v-model="cell(c.id, p).teacher_id" class="form-input !py-1 !text-[11px]" @change="onTeacherPick(c.id, p)">
                                                <option :value="null">{{ cell(c.id, p).teacher_label ? `⚠ ${cell(c.id, p).teacher_label}` : '— teacher —' }}</option>
                                                <optgroup v-if="freeTeachersForPeriod(p, c.id).length" label="Free (leisure)">
                                                    <option v-for="t in freeTeachersForPeriod(p, c.id)" :key="'f'+t.id" :value="t.id">{{ t.name }}</option>
                                                </optgroup>
                                                <optgroup v-if="busyTeachersForPeriod(p, c.id).length" label="Busy this period">
                                                    <option v-for="t in busyTeachersForPeriod(p, c.id)" :key="'b'+t.id" :value="t.id">{{ t.name }} — {{ t.busyIn }}</option>
                                                </optgroup>
                                            </select>
                                            <div v-if="cell(c.id, p).teacher_id || cell(c.id, p).teacher_label" class="flex items-center gap-1">
                                                <input v-model="cell(c.id, p).color" type="color" class="h-5 w-6 shrink-0 cursor-pointer rounded border-0 p-0" title="Badge colour for this cell" />
                                                <span
                                                    class="flex-1 truncate rounded px-1 py-0.5 text-center text-[10px] font-semibold"
                                                    :style="{ background: cell(c.id, p).color || '#e2e8f0', color: contrastColor(cell(c.id, p).color) }"
                                                >{{ teacherLabelFor(c.id, p) }}</span>
                                            </div>
                                        </template>
                                        <div v-else class="rounded bg-emerald-100 px-1 py-1 text-center text-[10px] font-bold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                                            Free period
                                        </div>
                                    </div>
                                </td>
                                <td class="min-w-[140px] px-1.5 py-1.5 align-top">
                                    <input v-model="classRemarks[c.id]" type="text" class="form-input !py-1 !text-[11px]" placeholder="Remarks" />
                                </td>
                            </tr>
                            <tr class="bg-emerald-50/80 dark:bg-emerald-500/10">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-emerald-50 px-3 py-2 align-top text-[11px] font-bold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">Leisure / Free</td>
                                <td v-for="p in periodsRange" :key="'leisure-'+p" class="min-w-[130px] px-1.5 py-1.5 align-top">
                                    <div class="max-h-28 overflow-y-auto rounded-lg border border-emerald-200 bg-white/80 px-1.5 py-1 text-[10px] font-semibold leading-snug text-emerald-800 dark:border-emerald-500/30 dark:bg-slate-900/40 dark:text-emerald-300">
                                        <template v-if="leisureTeachers(p).length">
                                            <div v-for="t in leisureTeachers(p)" :key="t.id">{{ t.name }}</div>
                                        </template>
                                        <span v-else class="font-normal text-slate-400">—</span>
                                    </div>
                                </td>
                                <td class="px-1.5 py-1.5 text-[10px] text-slate-400">Free teachers by period</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TEACHER WISE EDIT: assign class + subject per teacher per period -->
                <div v-else class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                        Pick a class (and subject) for each teacher in each period. Free periods stay empty. Use Delete to clear a teacher from this whole routine (all days).
                    </p>
                    <table class="min-w-full border-collapse text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Teacher</th>
                                <th v-for="p in periodsRange" :key="p" class="whitespace-nowrap px-2 py-2 text-center font-semibold text-slate-500">P{{ p }}</th>
                                <th class="whitespace-nowrap px-2 py-2 text-center font-semibold text-slate-500">Periods</th>
                                <th class="whitespace-nowrap px-2 py-2 text-center font-semibold text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!teacherWiseTeachers.length">
                                <td :colspan="periodsRange.length + 3" class="px-3 py-6 text-center text-slate-400">
                                    {{ teachers.length ? 'All teachers were removed from this routine. Assign one under Class wise to bring them back.' : 'No teachers found — add them under People → Teachers first.' }}
                                </td>
                            </tr>
                            <tr v-for="t in teacherWiseTeachers" :key="t.id">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 align-top font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <span
                                        class="inline-block max-w-[140px] truncate rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                        :style="{ background: t.color || '#e2e8f0', color: contrastColor(t.color) }"
                                    >{{ t.name }}</span>
                                </td>
                                <td v-for="p in periodsRange" :key="p" class="min-w-[140px] px-1.5 py-1.5 align-top">
                                    <div
                                        class="space-y-1 rounded-lg border p-1.5"
                                        :class="teacherPeriodClash(t.id, p)
                                            ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10'
                                            : teacherPeriodClassId(t.id, p)
                                                ? 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60'
                                                : 'border-dashed border-emerald-300 bg-emerald-50/50 dark:border-emerald-500/30 dark:bg-emerald-500/5'"
                                    >
                                        <select
                                            class="form-input !py-1 !text-[11px]"
                                            :value="teacherPeriodClassId(t.id, p) ?? ''"
                                            @change="onTeacherWiseClassChange(t.id, p, $event.target.value)"
                                        >
                                            <option value="">— FREE —</option>
                                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                        <select
                                            v-if="teacherPeriodClassId(t.id, p)"
                                            class="form-input !py-1 !text-[11px]"
                                            :value="cell(teacherPeriodClassId(t.id, p), p).subject_id ?? ''"
                                            @change="onTeacherWiseSubjectChange(t.id, p, $event.target.value)"
                                        >
                                            <option value="">{{ cell(teacherPeriodClassId(t.id, p), p).subject_label ? `⚠ ${cell(teacherPeriodClassId(t.id, p), p).subject_label}` : '— subject —' }}</option>
                                            <option v-for="sub in subjects" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                                        </select>
                                        <div v-if="teacherPeriodClash(t.id, p)" class="text-[10px] font-semibold text-rose-600 dark:text-rose-400">Clash: {{ teacherPeriodClash(t.id, p) }}</div>
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-center align-top text-sm font-semibold text-slate-600 dark:text-slate-300">{{ teacherPeriodCount(t.id) }}</td>
                                <td class="px-2 py-2 text-center align-top">
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 dark:hover:bg-rose-500/10"
                                        title="Remove this teacher from the whole routine (all days)"
                                        @click="removeTeacherFromRoutine(t)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Import Excel modal -->
        <div v-if="importModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Import Class Routine from Excel</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Upload a workbook with a sheet named starting "MON" (applied to Mon/Tue/Wed) and/or "THU" (applied to Thu/Fri/Sat) — same layout as the school's printed routine. It's parsed and used to pre-fill the grid below for review — nothing is saved until you click Save there.
                </p>

                <div v-if="view === 'list'" class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Academic Session</label>
                        <select v-model.number="importForm.academic_session_id" class="form-input">
                            <option :value="null">Select session</option>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model.number="importForm.branch_id" class="form-input">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="form-label">Select / Upload Excel File</label>
                    <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="form-input" @change="onImportFileSelected" />
                </div>

                <p v-if="importError" class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">{{ importError }}</p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" class="btn-outline" :disabled="downloadingTemplate" @click="downloadTemplate">
                        {{ downloadingTemplate ? 'Downloading...' : 'Download Template' }}
                    </button>
                    <button type="button" class="btn-outline flex-1" :disabled="importing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="btn-primary flex-1" :disabled="!canRunImport || importing" @click="runImport">
                        {{ importing ? 'Parsing...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Export format modal -->
        <div v-if="exportModalSheet" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="closeExportModal">
            <div class="w-full max-w-xs rounded-xl bg-white p-5 shadow-2xl dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Export Class Routine</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose a format to download.</p>
                <div class="mt-4 space-y-2">
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('xlsx')">
                        📊 Excel (.xlsx) <span class="ml-auto text-[10px] text-slate-400">same colours</span>
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('pdf')">
                        📄 PDF
                    </button>
                    <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="chooseExportFormat('csv')">
                        🧾 CSV
                    </button>
                </div>
                <button type="button" class="btn-outline mt-4 w-full !text-xs" @click="closeExportModal">Cancel</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import ClassRoutineView from './ClassRoutineView.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { triggerBlobDownload } from '../../utils/documentPdf';
import { downloadImportTemplate } from '../../utils/downloadExport';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();

const DAYS = [
    { dow: 1, label: 'Monday' },
    { dow: 2, label: 'Tuesday' },
    { dow: 3, label: 'Wednesday' },
    { dow: 4, label: 'Thursday' },
    { dow: 5, label: 'Friday' },
    { dow: 6, label: 'Saturday' },
];

const view = ref('list');
const sheets = ref([]);
const sheetsLoading = ref(true);
const sessions = ref([]);
const branches = ref([]);
const classes = ref([]);
const subjects = ref([]);
const teachers = ref([]);
const currentSessionName = ref('');

const editingSheet = ref(null);
const sheetSaving = ref(false);
const form = reactive({ academic_session_id: null, branch_id: null, title: '', periods_per_day: 7 });
// Per-day period overrides (half day etc.). Keys are day_of_week; missing → form.periods_per_day.
const dayPeriods = reactive({});
// grid[dow][classId][period] = { is_free, subject_id, subject_label, teacher_id, teacher_label, color }
const grid = reactive({});
const classRemarks = reactive({});
const activeDay = ref(1);
const copyTargetDay = ref(null);
// Inside an open routine: 'edit' = the editable grid, 'view' = Excel-style class/teacher-wise view.
const mode = ref('edit');
// Within edit mode: class-centric grid vs teacher-centric assign grid (same underlying data).
const editLayout = ref('class');
/** Teachers hidden from Teacher-wise after "Delete" (still assignable via Class wise). */
const removedFromRoutineTeacherIds = ref(new Set());

const teacherWiseTeachers = computed(() =>
    teachers.value.filter((t) => !removedFromRoutineTeacherIds.value.has(t.id))
);

function periodsForDay(dow) {
    const n = Number(dayPeriods[dow]);
    if (n >= 1) return n;
    return Math.max(1, Number(form.periods_per_day) || 7);
}

function setDayPeriods(dow, raw) {
    let n = parseInt(raw, 10);
    if (!Number.isFinite(n) || n < 1) n = 1;
    if (n > 20) n = 20;
    dayPeriods[dow] = n;
    // Keep sheet-wide default at least as high as any day (used as fallback / create default).
    if (n > (form.periods_per_day || 0)) form.periods_per_day = n;
    ensureDay(dow);
}

const periodsRange = computed(() => Array.from({ length: periodsForDay(activeDay.value) }, (_, i) => i + 1));

function blankCell() {
    return { is_free: false, subject_id: null, subject_label: null, teacher_id: null, teacher_label: null, color: null };
}

function ensureDay(dow) {
    grid[dow] ??= {};
    const maxP = periodsForDay(dow);
    classes.value.forEach((c) => {
        grid[dow][c.id] ??= {};
        for (let p = 1; p <= maxP; p++) {
            grid[dow][c.id][p] ??= blankCell();
        }
    });
}
function ensureAllDays() {
    DAYS.forEach((d) => ensureDay(d.dow));
}

function cell(classId, period) {
    ensureDay(activeDay.value);
    grid[activeDay.value][classId] ??= {};
    grid[activeDay.value][classId][period] ??= blankCell();
    return grid[activeDay.value][classId][period];
}

function toggleFree(classId, period) {
    const c = cell(classId, period);
    c.is_free = !c.is_free;
    if (c.is_free) {
        c.subject_id = null;
        c.subject_label = null;
        c.teacher_id = null;
        c.teacher_label = null;
        c.color = null;
    }
}

/** True when every class on the active day has this period marked FREE. */
function isPeriodAllFree(period) {
    if (!classes.value.length) return false;
    return classes.value.every((c) => cell(c.id, period).is_free);
}

/** Mark / clear FREE for this period across all classes (e.g. school-wide free period). */
function togglePeriodFree(period) {
    const makeFree = !isPeriodAllFree(period);
    classes.value.forEach((c) => {
        const slot = cell(c.id, period);
        slot.is_free = makeFree;
        if (makeFree) {
            slot.subject_id = null;
            slot.subject_label = null;
            slot.teacher_id = null;
            slot.teacher_label = null;
            slot.color = null;
        }
    });
    pushToast(
        makeFree
            ? `Period ${period} marked FREE for all classes.`
            : `Period ${period} FREE cleared — you can assign subjects/teachers again.`,
        'success'
    );
}

function cellHasUnmatched(classId, period) {
    const c = grid[activeDay.value]?.[classId]?.[period];
    return !!(c && (c.subject_label || c.teacher_label));
}

function teacherLabelFor(classId, period) {
    const c = cell(classId, period);
    if (c.teacher_id) return teachers.value.find((t) => t.id === c.teacher_id)?.name || '';
    return c.teacher_label || '';
}

function onTeacherPick(classId, period) {
    const c = cell(classId, period);
    c.teacher_label = null;
    if (c.teacher_id) {
        const t = teachers.value.find((x) => x.id === c.teacher_id);
        if (t?.color && !c.color) c.color = t.color;
        // Re-show on Teacher wise if they were deleted earlier.
        if (removedFromRoutineTeacherIds.value.has(c.teacher_id)) {
            const next = new Set(removedFromRoutineTeacherIds.value);
            next.delete(c.teacher_id);
            removedFromRoutineTeacherIds.value = next;
        }
    }
}

/** teacher_id -> class name(s) already assigned for active day + period (excluding one class when editing that cell). */
function busyMapForPeriod(period, excludeClassId = null) {
    const busy = new Map();
    const dayGrid = grid[activeDay.value] || {};
    classes.value.forEach((c) => {
        if (excludeClassId != null && c.id === excludeClassId) return;
        const cellData = dayGrid[c.id]?.[period];
        if (!cellData || cellData.is_free) return;
        const tid = cellData.teacher_id;
        if (!tid) return;
        const prev = busy.get(tid) || [];
        prev.push(c.name);
        busy.set(tid, prev);
    });
    return busy;
}

function freeTeachersForPeriod(period, classId) {
    const busy = busyMapForPeriod(period, classId);
    return teachers.value.filter((t) => !busy.has(t.id));
}

function busyTeachersForPeriod(period, classId) {
    const busy = busyMapForPeriod(period, classId);
    return teachers.value
        .filter((t) => busy.has(t.id))
        .map((t) => ({ ...t, busyIn: (busy.get(t.id) || []).join(', ') }));
}

/** Free teachers for the LEISURE row — blank when every class is FREE this period. */
function leisureTeachers(period) {
    const dayGrid = grid[activeDay.value] || {};
    const anyTeaching = classes.value.some((c) => {
        const cellData = dayGrid[c.id]?.[period];
        return cellData && !cellData.is_free && String(cellData.subject_label || '').toUpperCase() !== 'FREE'
            && (cellData.teacher_id || cellData.subject_id || cellData.subject_label);
    });
    if (!anyTeaching) return [];
    const busy = busyMapForPeriod(period, null);
    return teachers.value.filter((t) => !busy.has(t.id));
}

/** Class ids this teacher is assigned to on the active day + period. */
function teacherPeriodClassIds(teacherId, period) {
    const dayGrid = grid[activeDay.value] || {};
    const ids = [];
    classes.value.forEach((c) => {
        const cellData = dayGrid[c.id]?.[period];
        if (cellData?.is_free) return;
        if (cellData?.teacher_id === teacherId) ids.push(c.id);
    });
    return ids;
}

function teacherPeriodClassId(teacherId, period) {
    const ids = teacherPeriodClassIds(teacherId, period);
    return ids[0] ?? null;
}

function teacherPeriodClash(teacherId, period) {
    const ids = teacherPeriodClassIds(teacherId, period);
    if (ids.length < 2) return '';
    return ids.map((id) => classes.value.find((c) => c.id === id)?.name || `#${id}`).join(' + ');
}

function teacherPeriodCount(teacherId) {
    let n = 0;
    periodsRange.value.forEach((p) => {
        if (teacherPeriodClassIds(teacherId, p).length) n += 1;
    });
    return n;
}

/** Clear this teacher from every class in this period (active day). */
function clearTeacherFromPeriod(teacherId, period) {
    ensureDay(activeDay.value);
    classes.value.forEach((c) => {
        const cellData = grid[activeDay.value][c.id]?.[period];
        if (!cellData || cellData.teacher_id !== teacherId) return;
        cellData.teacher_id = null;
        cellData.teacher_label = null;
        // Drop orphan empty cells (no subject either).
        if (!cellData.subject_id && !cellData.subject_label) {
            cellData.color = null;
        }
    });
}

/** Remove teacher from every day/period in this routine and hide them on Teacher wise. */
function removeTeacherFromRoutine(teacher) {
    if (!teacher?.id) return;
    if (!window.confirm(`Remove ${teacher.name} from this routine? Their assignments on all days will be cleared.`)) {
        return;
    }
    const teacherId = teacher.id;
    DAYS.forEach((d) => {
        ensureDay(d.dow);
        const maxP = periodsForDay(d.dow);
        classes.value.forEach((c) => {
            for (let p = 1; p <= maxP; p++) {
                const cellData = grid[d.dow]?.[c.id]?.[p];
                if (!cellData) continue;
                if (cellData.teacher_id === teacherId) {
                    cellData.teacher_id = null;
                    cellData.teacher_label = null;
                    if (!cellData.subject_id && !cellData.subject_label) {
                        cellData.color = null;
                    }
                } else if (cellData.teacher_label && String(cellData.teacher_label).toLowerCase() === String(teacher.name || '').toLowerCase()) {
                    cellData.teacher_label = null;
                }
            }
        });
    });
    const next = new Set(removedFromRoutineTeacherIds.value);
    next.add(teacherId);
    removedFromRoutineTeacherIds.value = next;
    pushToast(`${teacher.name} removed from this routine.`, 'success');
}

function onTeacherWiseClassChange(teacherId, period, classIdRaw) {
    const classId = classIdRaw === '' || classIdRaw == null ? null : Number(classIdRaw);
    ensureDay(activeDay.value);

    // Remember subject from the teacher's previous slot this period (if any).
    const prevClassId = teacherPeriodClassId(teacherId, period);
    const prevSubjectId = prevClassId ? cell(prevClassId, period).subject_id : null;
    const prevSubjectLabel = prevClassId ? cell(prevClassId, period).subject_label : null;

    clearTeacherFromPeriod(teacherId, period);

    if (!classId) return;

    const target = cell(classId, period);
    target.is_free = false;
    // If another teacher already had this class+period, replace them.
    target.teacher_id = teacherId;
    target.teacher_label = null;
    const t = teachers.value.find((x) => x.id === teacherId);
    if (t?.color) target.color = t.color;

    // Keep subject if the class already had one; else carry over from previous slot.
    if (!target.subject_id && !target.subject_label) {
        target.subject_id = prevSubjectId;
        target.subject_label = prevSubjectId ? null : prevSubjectLabel;
    }
}

function onTeacherWiseSubjectChange(teacherId, period, subjectIdRaw) {
    const classId = teacherPeriodClassId(teacherId, period);
    if (!classId) return;
    const subjectId = subjectIdRaw === '' || subjectIdRaw == null ? null : Number(subjectIdRaw);
    const target = cell(classId, period);
    target.subject_id = subjectId;
    target.subject_label = null;
}

function contrastColor(hex) {
    if (!hex) return '#1e293b';
    const h = hex.replace('#', '');
    if (h.length !== 6) return '#1e293b';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.6 ? '#000000' : '#ffffff';
}

function dayLabel(dow) {
    return DAYS.find((d) => d.dow === dow)?.label || '';
}

function onPeriodsChange() {
    if (!form.periods_per_day || form.periods_per_day < 1) form.periods_per_day = 1;
    ensureAllDays();
}

function copyDay() {
    if (!copyTargetDay.value) return;
    ensureDay(activeDay.value);
    dayPeriods[copyTargetDay.value] = periodsForDay(activeDay.value);
    ensureDay(copyTargetDay.value);
    grid[copyTargetDay.value] = JSON.parse(JSON.stringify(grid[activeDay.value]));
    pushToast(`Copied ${dayLabel(activeDay.value)} to ${dayLabel(copyTargetDay.value)}.`, 'success');
    copyTargetDay.value = null;
}

function periodsByDayPayload() {
    const out = {};
    DAYS.forEach((d) => {
        out[d.dow] = periodsForDay(d.dow);
    });
    return out;
}

// The routine as it is in the grid right now (saved, freshly imported or typed in by hand) — what View shows.
const viewSheet = computed(() => ({
    title: form.title,
    periods_per_day: form.periods_per_day,
    periods_by_day: periodsByDayPayload(),
    days: DAYS.map((d) => {
        const maxP = periodsForDay(d.dow);
        return {
            day_of_week: d.dow,
            periods: maxP,
            entries: classes.value.flatMap((c) => {
                const list = [];
                for (let p = 1; p <= maxP; p++) {
                    const x = grid[d.dow]?.[c.id]?.[p];
                    if (!x) continue;
                    if (x.is_free) {
                        list.push({ ...x, is_free: true, school_class_id: c.id, period_number: p, remarks: classRemarks[c.id] || null });
                        continue;
                    }
                    if (!x.subject_id && !x.subject_label && !x.teacher_id && !x.teacher_label) continue;
                    list.push({ ...x, is_free: false, school_class_id: c.id, period_number: p, remarks: classRemarks[c.id] || null });
                }
                return list;
            }),
        };
    }),
}));

const sheetSubtitle = computed(() => {
    const session = sessions.value.find((s) => s.id === form.academic_session_id);
    const branch = branches.value.find((b) => b.id === form.branch_id);
    const parts = [session?.name, branch?.name].filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Pick a session and branch to get started.';
});

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadLookups() {
    const [academics, sessionsRes, routineLookups] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/academics/class-routine/lookups'),
    ]);
    branches.value = academics.branches || [];
    classes.value = (academics.classes || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
    subjects.value = academics.subjects || [];
    teachers.value = routineLookups.data.teachers || [];
    sessions.value = sessionsRes.data;
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
}

async function loadSheets() {
    sheetsLoading.value = true;
    try {
        await loadLookups();
        const { data } = await client.get('/academics/class-routine');
        sheets.value = data;
    } finally {
        sheetsLoading.value = false;
    }
}

function resetForm() {
    Object.assign(form, {
        academic_session_id: sessions.value.find((s) => s.is_current)?.id || null,
        branch_id: branches.value.length === 1 ? branches.value[0].id : null,
        title: '',
        periods_per_day: 7,
    });
    Object.keys(dayPeriods).forEach((k) => delete dayPeriods[k]);
    Object.keys(grid).forEach((k) => delete grid[k]);
    Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
    ensureAllDays();
}

function openCreate() {
    editingSheet.value = null;
    mode.value = 'edit';
    resetForm();
    importWarnings.value = null;
    activeDay.value = 1;
    view.value = 'edit';
}

function daysFromServer(daysData, periodsByDayMap = null) {
    Object.keys(grid).forEach((k) => delete grid[k]);
    Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
    Object.keys(dayPeriods).forEach((k) => delete dayPeriods[k]);

    if (periodsByDayMap && typeof periodsByDayMap === 'object') {
        Object.entries(periodsByDayMap).forEach(([dow, n]) => {
            const d = Number(dow);
            const count = Number(n);
            if (d >= 1 && count >= 1) dayPeriods[d] = count;
        });
    }
    (daysData || []).forEach((day) => {
        if (day.periods >= 1) dayPeriods[day.day_of_week] = Number(day.periods);
    });

    ensureAllDays();
    (daysData || []).forEach((day) => {
        (day.entries || []).forEach((entry) => {
            grid[day.day_of_week] ??= {};
            grid[day.day_of_week][entry.school_class_id] ??= {};
            const free = !!(entry.is_free || String(entry.subject_label || '').toUpperCase() === 'FREE');
            grid[day.day_of_week][entry.school_class_id][entry.period_number] = {
                is_free: free,
                subject_id: free ? null : entry.subject_id,
                subject_label: free ? null : entry.subject_label,
                teacher_id: free ? null : entry.teacher_id,
                teacher_label: free ? null : entry.teacher_label,
                color: free ? null : entry.color,
            };
            if (entry.remarks) classRemarks[entry.school_class_id] = entry.remarks;
        });
    });
}

async function openSheet(sheet, openMode = 'edit') {
    editingSheet.value = sheet;
    mode.value = openMode;
    removedFromRoutineTeacherIds.value = new Set();
    sheetsLoading.value = true;
    try {
        await loadLookups();
        const { data } = await client.get(`/academics/class-routine/${sheet.id}`);
        Object.assign(form, {
            academic_session_id: data.academic_session_id,
            branch_id: data.branch_id,
            title: data.title || '',
            periods_per_day: data.periods_per_day || 7,
        });
        daysFromServer(data.days, data.periods_by_day);
        importWarnings.value = null;
        activeDay.value = 1;
        view.value = 'edit';
    } finally {
        sheetsLoading.value = false;
    }
}

function closeEdit() {
    view.value = 'list';
    editingSheet.value = null;
    importWarnings.value = null;
    removedFromRoutineTeacherIds.value = new Set();
    loadSheets();
}

async function saveSheet() {
    if (!form.academic_session_id || !form.branch_id) {
        pushToast('Select an academic session and branch.', 'error');
        return;
    }
    sheetSaving.value = true;
    try {
        let sheetId = editingSheet.value?.id;
        if (!sheetId) {
            const { data } = await client.post('/academics/class-routine', {
                academic_session_id: form.academic_session_id,
                branch_id: form.branch_id,
                title: form.title || null,
                periods_per_day: form.periods_per_day,
                periods_by_day: periodsByDayPayload(),
            });
            sheetId = data.id;
            editingSheet.value = data;
        }

        const days = DAYS.map((d) => {
            const maxP = periodsForDay(d.dow);
            return {
                day_of_week: d.dow,
                entries: classes.value.flatMap((c) => {
                    const list = [];
                    for (let p = 1; p <= maxP; p++) {
                        const cellData = grid[d.dow]?.[c.id]?.[p];
                        if (!cellData) continue;
                        if (cellData.is_free) {
                            list.push({
                                school_class_id: c.id,
                                period_number: p,
                                is_free: true,
                                remarks: classRemarks[c.id] || null,
                            });
                            continue;
                        }
                        if (!cellData.subject_id && !cellData.subject_label && !cellData.teacher_id && !cellData.teacher_label) continue;
                        list.push({
                            school_class_id: c.id,
                            period_number: p,
                            is_free: false,
                            subject_id: cellData.subject_id || null,
                            subject_label: cellData.subject_id ? null : cellData.subject_label,
                            teacher_id: cellData.teacher_id || null,
                            teacher_label: cellData.teacher_id ? null : cellData.teacher_label,
                            color: cellData.color || null,
                            remarks: classRemarks[c.id] || null,
                        });
                    }
                    return list;
                }),
            };
        });

        await client.put(`/academics/class-routine/${sheetId}`, {
            title: form.title || null,
            periods_per_day: form.periods_per_day,
            periods_by_day: periodsByDayPayload(),
            days,
        });
        pushToast('Class routine saved.', 'success');
        closeEdit();
    } finally {
        sheetSaving.value = false;
    }
}

async function removeSheet(sheet) {
    if (!window.confirm('Delete this class routine?')) return;
    sheets.value = sheets.value.filter((s) => s.id !== sheet.id);
    await client.delete(`/academics/class-routine/${sheet.id}`);
    pushToast('Class routine deleted.', 'success');
}

const EXPORT_EXTENSIONS = { xlsx: 'xlsx', pdf: 'pdf', csv: 'csv' };
async function downloadRoutine(sheet, format = 'xlsx') {
    try {
        const response = await client.get(`/academics/class-routine/${sheet.id}/export`, {
            params: { format },
            responseType: 'blob',
        });
        triggerBlobDownload(response.data, `class-routine-${sheet.id}.${EXPORT_EXTENSIONS[format] || 'xlsx'}`);
        pushToast(`Exported as ${format.toUpperCase()}.`, 'success');
    } catch {
        pushToast('Could not export this routine.', 'error');
    }
}

// A dropdown panel positioned relative to its trigger button got silently clipped by the sheets
// table's `overflow-hidden` card — nothing appeared to click. A modal has no clipping ancestor to
// worry about, so both the list row's export icon and the edit view's Export button open this
// same one instead.
const exportModalSheet = ref(null);
function openExportModal(sheet) {
    exportModalSheet.value = sheet;
}
function closeExportModal() {
    exportModalSheet.value = null;
}
function chooseExportFormat(format) {
    if (exportModalSheet.value) downloadRoutine(exportModalSheet.value, format);
    closeExportModal();
}

const downloadingTemplate = ref(false);
async function downloadTemplate() {
    downloadingTemplate.value = true;
    try {
        await downloadImportTemplate('class-routine');
        pushToast('Template downloaded.', 'success');
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not download template.', 'error');
    } finally {
        downloadingTemplate.value = false;
    }
}

// --- Import ---
const importModalOpen = ref(false);
const importFileInput = ref(null);
const importFile = ref(null);
const importing = ref(false);
const importForm = reactive({ academic_session_id: null, branch_id: null });
const importError = ref('');
const importWarnings = ref(null);
const canRunImport = computed(() => {
    if (!importFile.value) return false;
    if (view.value === 'edit') return true;
    return !!(importForm.academic_session_id && importForm.branch_id);
});

function openImportModal() {
    importForm.academic_session_id = view.value === 'edit' ? form.academic_session_id : (sessions.value.find((s) => s.is_current)?.id || null);
    importForm.branch_id = view.value === 'edit' ? form.branch_id : (branches.value.length === 1 ? branches.value[0].id : null);
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
        const { data } = await client.post('/academics/class-routine/import-preview', body);

        if (view.value !== 'edit') {
            editingSheet.value = null;
            resetForm();
            form.academic_session_id = importForm.academic_session_id;
            form.branch_id = importForm.branch_id;
        }

        Object.keys(grid).forEach((k) => delete grid[k]);
        Object.keys(classRemarks).forEach((k) => delete classRemarks[k]);
        ensureAllDays();
        daysFromServer(data.days);

        // Backfill any still-colourless matched teacher's badge colour from the legend, purely
        // client-side preview convenience — the real backfill happens server-side on Save.
        Object.entries(data.teacher_colors || {}).forEach(([rawName, hex]) => {
            const t = teachers.value.find((x) => x.name.toUpperCase().includes(rawName.toUpperCase()));
            if (t && !t.color) t.color = hex;
        });

        importWarnings.value = data.warnings;
        activeDay.value = 1;
        view.value = 'edit';
        closeImportModal();
        const totalEntries = (data.days || []).reduce((sum, d) => sum + d.entries.length, 0);
        pushToast(`Parsed ${totalEntries} routine entries across ${data.days.length} day(s) — review the warnings below before saving.`, 'success');
    } catch (e) {
        importError.value = e?.response?.data?.message || 'Could not parse this file.';
    } finally {
        importing.value = false;
    }
}

// Reached from Import & Export → Class Routine Import/Export (that hub only lists a link here,
// since a routine's import/export needs a session+branch picked and a review grid, not a blind
// one-shot upload) — auto-open the matching action once the list has loaded.
onMounted(async () => {
    await loadSheets();
    if (route.query.action === 'import') {
        openImportModal();
    } else if (route.query.action === 'export' && sheets.value.length === 1) {
        downloadRoutine(sheets.value[0], 'xlsx');
    }
    if (route.query.action) {
        router.replace({ path: route.path, query: {} });
    }
});
</script>
