<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Attendance</h1>
        </div>

        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- MARK -->
        <template v-if="activeTab === 'mark'">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="markMode === 'day' ? 'bg-primary-600 text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    @click="markMode = 'day'"
                >Single day</button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="markMode === 'month' ? 'bg-primary-600 text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    @click="markMode = 'month'"
                >Month</button>
            </div>
        </template>

        <!-- MARK: single day -->
        <template v-if="activeTab === 'mark' && markMode === 'day'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <label class="form-label">Attendance date</label>
                <input v-model="mark.date" type="date" class="form-input max-w-xs" @change="loadMarkRoster" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="mark.branch_id" class="form-input" @change="onMarkBranchChange">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="mark.school_class_id" class="form-input" @change="onMarkClassChange">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="mark.section_id" class="form-input" @change="loadMarkRoster">
                            <option :value="null">Select section</option>
                            <option v-for="s in markSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Teacher</label>
                        <select v-model="mark.teacher_id" class="form-input" @change="onMarkTeacherChange">
                            <option :value="null">Select teacher</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <template v-if="mark.school_class_id">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Present" :value="markCounts.Present" color="emerald" icon="OK" />
                    <StatCard label="Absent" :value="markCounts.Absent" color="rose" icon="X" />
                    <StatCard label="Leave" :value="markCounts.Leave" color="amber" icon="LV" />
                    <StatCard label="Late" :value="markCounts.Late" color="sky" icon="LT" />
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-400">Mark All:</span>
                        <button v-for="s in statuses" :key="s" type="button" class="btn-outline !py-1 !text-xs" @click="markAll(s)">{{ s }}</button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="markLoading">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                            </tr>
                            <tr v-else-if="!people.length">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-400">No students match these filters.</td>
                            </tr>
                            <tr v-for="p in people" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.code || '—' }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ p.meta || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <button
                                            v-for="s in statuses"
                                            :key="s"
                                            type="button"
                                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                                            :class="p.status === s ? statusActiveClass(s) : 'border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400'"
                                            @click="p.status = s"
                                        >{{ s }}</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model="p.remarks" type="text" class="form-input !py-1 !text-xs" placeholder="Optional" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="btn-primary" :disabled="markSaving || !people.length" @click="saveMark">
                        {{ markSaving ? 'Saving...' : 'Save Attendance' }}
                    </button>
                </div>
            </template>
            <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
                Select branch, class, and section to load the attendance roster.
            </div>
        </template>

        <!-- MARK: month -->
        <template v-else-if="activeTab === 'mark' && markMode === 'month'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Month</label>
                        <input v-model="monthMark.month" type="month" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="monthMark.branch_id" class="form-input" @change="onMonthBranchChange">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="monthMark.school_class_id" class="form-input" @change="monthMark.section_id = null">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="monthMark.section_id" class="form-input">
                            <option :value="null">Select section</option>
                            <option v-for="s in monthMarkSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn-primary mt-4" :disabled="monthGridLoading || !monthMark.school_class_id || !monthMark.month" @click="loadMonthGrid">
                    {{ monthGridLoading ? 'Loading...' : 'Load month' }}
                </button>
            </div>

            <div v-if="monthGridLoading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
            <div v-else-if="!monthGridStudents.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
                Select class and month, then Load month to mark the whole month at once.
            </div>
            <template v-else>
                <p class="text-xs text-slate-400">Click a day cell to cycle Present → Absent → blank. Grey columns are holidays and can't be marked. "Present" is a live running count for the month, updating as you mark.</p>
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Roll</th>
                                <th class="sticky left-12 z-10 bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Name</th>
                                <th
                                    v-for="d in monthGridDays"
                                    :key="d.date"
                                    class="px-1 py-2 text-center font-semibold"
                                    :class="d.is_holiday ? 'bg-slate-100 text-slate-400 dark:bg-slate-800/70' : 'text-slate-400'"
                                    :title="d.is_holiday ? d.dow + ' — Holiday' : d.dow"
                                >{{ d.day }}</th>
                                <th class="px-3 py-2 text-center font-semibold text-emerald-600 dark:text-emerald-400">Present</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="student in monthGridStudents" :key="student.id">
                                <td class="sticky left-0 bg-white px-3 py-2 font-mono text-slate-500 dark:bg-slate-900">{{ student.roll_no || '—' }}</td>
                                <td class="sticky left-12 whitespace-nowrap bg-white px-3 py-2 font-medium text-slate-800 dark:bg-slate-900 dark:text-slate-100">{{ student.name }}</td>
                                <td
                                    v-for="(cell, i) in student.cells"
                                    :key="cell.date"
                                    class="px-1 py-2 text-center"
                                    :class="cell.status === 'Holiday' ? 'bg-slate-50 dark:bg-slate-800/40' : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40'"
                                    @click="cycleCell(student, i)"
                                >
                                    <span v-if="cell.status === 'Present'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">P</span>
                                    <span v-else-if="cell.status === 'Absent'" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">A</span>
                                    <span v-else-if="cell.status === 'Holiday'" class="text-slate-300 dark:text-slate-600">·</span>
                                    <span v-else class="text-slate-200 dark:text-slate-700">—</span>
                                </td>
                                <td class="px-3 py-2 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ presentTotal(student) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="btn-primary" :disabled="monthGridSaving" @click="saveMonthGrid">
                        {{ monthGridSaving ? 'Saving...' : 'Save Month' }}
                    </button>
                </div>
            </template>
        </template>

        <!-- HISTORY -->
        <template v-else-if="activeTab === 'history'">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="history.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="history.to" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="history.branch_id" class="form-input">
                            <option :value="null">All branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="history.school_class_id" class="form-input" @change="history.section_id = null">
                            <option :value="null">All classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="history.section_id" class="form-input">
                            <option :value="null">All sections</option>
                            <option v-for="s in historySections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Teacher</label>
                        <select v-model="history.teacher_id" class="form-input">
                            <option :value="null">All teachers</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-primary" :disabled="historyLoading" @click="loadHistory">{{ historyLoading ? 'Loading...' : 'Apply filters' }}</button>
                        <button type="button" class="btn-outline" @click="pushToast('Column picker coming soon.', 'info')">Columns</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline" @click="pushToast('Export columns coming soon.', 'info')">Export columns</button>
                        <button type="button" class="btn-outline" :disabled="!historySessions.length" @click="exportHistoryCsv">Export</button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="historyLoading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!historySessions.length" class="px-6 py-16 text-center text-sm text-slate-400">
                    No attendance sessions found for the selected filters.
                </div>
                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Marked</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Present</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Absent</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="session in historySessions" :key="session.date" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ formatDisplayDate(session.date) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ session.marked_count }}</td>
                            <td class="px-4 py-3 text-emerald-600">{{ session.present_count }}</td>
                            <td class="px-4 py-3 text-rose-600">{{ session.absent_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="text-xs font-semibold text-primary-600 hover:underline" @click="openSessionInMark(session.date)">Open</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- EXCEL VIEW -->
        <template v-else-if="activeTab === 'excel'">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="excel.branch_id" class="form-input">
                        <option :value="null">Select branch</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="excel.school_class_id" class="form-input" @change="excel.section_id = null">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="excel.section_id" class="form-input">
                        <option :value="null">Select section</option>
                        <option v-for="s in excelSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Month</label>
                    <input v-model="excel.month" type="month" class="form-input" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary" :disabled="excelLoading || !excel.school_class_id || !excel.month" @click="loadExcelSheet">
                    {{ excelLoading ? 'Loading...' : 'View sheet' }}
                </button>
                <button type="button" class="btn-outline" :disabled="!excelRows.length" @click="exportExcelSheet">Export CSV</button>
            </div>

            <div v-if="!excel.school_class_id || !excel.month" class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-20 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">
                Select branch, class, section, and month to view the attendance sheet.
            </div>
            <div v-else-if="excelLoading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
            <div v-else-if="!excelRows.length" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No students found for this class/section.</div>
            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="sticky left-0 bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Roll</th>
                            <th class="sticky left-12 bg-slate-50 px-3 py-2 font-semibold text-slate-500 dark:bg-slate-800">Name</th>
                            <th v-for="d in excelDays" :key="d" class="px-1.5 py-2 text-center font-semibold text-slate-400">{{ d }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in excelRows" :key="row.id">
                            <td class="sticky left-0 bg-white px-3 py-2 font-mono text-slate-500 dark:bg-slate-900">{{ row.roll_no || '—' }}</td>
                            <td class="sticky left-12 whitespace-nowrap bg-white px-3 py-2 font-medium text-slate-800 dark:bg-slate-900 dark:text-slate-100">{{ row.name }}</td>
                            <td v-for="d in excelDays" :key="d" class="px-1.5 py-2 text-center font-semibold" :class="sheetCellClass(row.days[String(d)])">
                                {{ row.days[String(d)] || '·' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400 dark:border-slate-800">P = Present · A = Absent · L = Leave · T = Late · H = Half Day</p>
            </div>
        </template>

        <!-- MY ATTENDANCE -->
        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-2 sm:max-w-lg">
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="mine.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="mine.to" type="date" class="form-input" />
                    </div>
                </div>
                <button type="button" class="btn-primary mt-4" :disabled="mineLoading" @click="loadMine">{{ mineLoading ? 'Loading...' : 'Apply filters' }}</button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="mineLoading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!mineRecords.length" class="px-6 py-16 text-center text-sm text-slate-400">
                    {{ mineMessage || 'No attendance records found for the selected dates.' }}
                </div>
                <template v-else>
                    <div v-if="minePerson" class="border-b border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800">
                        Showing attendance for <span class="font-semibold text-slate-800 dark:text-slate-100">{{ minePerson.name }}</span>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(r, i) in mineRecords" :key="i">
                                <td class="px-4 py-3 text-slate-800 dark:text-slate-100">{{ formatDisplayDate(r.date) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.status)">{{ r.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ r.remarks || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import StatCard from '../../components/common/StatCard.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import { fetchPeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'mark', label: 'Mark' },
    { id: 'history', label: 'History' },
    { id: 'excel', label: 'Excel view' },
    { id: 'mine', label: 'My attendance' },
];
const activeTab = ref('mark');
const markMode = ref('day');
const statuses = ['Present', 'Absent', 'Leave', 'Late', 'Half Day'];

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const teachers = ref([]);

const mark = reactive({
    date: new Date().toISOString().slice(0, 10),
    branch_id: null,
    school_class_id: null,
    section_id: null,
    teacher_id: null,
});
const people = ref([]);
const markLoading = ref(false);
const markSaving = ref(false);

const monthMark = reactive({
    month: new Date().toISOString().slice(0, 7),
    branch_id: null,
    school_class_id: null,
    section_id: null,
});
const monthGridDays = ref([]);
const monthGridStudents = ref([]);
const monthGridLoading = ref(false);
const monthGridSaving = ref(false);

const history = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    branch_id: null,
    school_class_id: null,
    section_id: null,
    teacher_id: null,
});
const historySessions = ref([]);
const historyLoading = ref(false);

const excel = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    month: new Date().toISOString().slice(0, 7),
});
const excelDays = ref([]);
const excelRows = ref([]);
const excelLoading = ref(false);

const mine = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
});
const mineRecords = ref([]);
const minePerson = ref(null);
const mineMessage = ref('');
const mineLoading = ref(false);

const markSections = computed(() => sections.value.filter((s) => s.school_class_id === mark.school_class_id));
const monthMarkSections = computed(() => sections.value.filter((s) => s.school_class_id === monthMark.school_class_id));
const historySections = computed(() => (
    history.school_class_id
        ? sections.value.filter((s) => s.school_class_id === history.school_class_id)
        : sections.value
));
const excelSections = computed(() => sections.value.filter((s) => s.school_class_id === excel.school_class_id));

const markCounts = computed(() => {
    const counts = { Present: 0, Absent: 0, Leave: 0, Late: 0, 'Half Day': 0 };
    people.value.forEach((p) => {
        if (p.status && counts[p.status] !== undefined) counts[p.status] += 1;
    });
    return counts;
});

function statusActiveClass(status) {
    return {
        Present: 'bg-emerald-600 text-white',
        Absent: 'bg-rose-600 text-white',
        Leave: 'bg-amber-500 text-white',
        Late: 'bg-sky-600 text-white',
        'Half Day': 'bg-violet-600 text-white',
    }[status] || 'bg-primary-600 text-white';
}

function sheetCellClass(letter) {
    return {
        P: 'text-emerald-600',
        A: 'text-rose-600',
        L: 'text-amber-600',
        T: 'text-sky-600',
        H: 'text-violet-600',
    }[letter] || 'text-slate-300';
}

function formatDisplayDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadLookups() {
    const [lookups, people] = await Promise.all([
        fetchAcademicsLookups(),
        fetchPeopleLookups(),
    ]);
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    teachers.value = people.teachers || [];
}

function onMarkBranchChange() {
    loadMarkRoster();
}

function onMarkClassChange() {
    mark.section_id = null;
    loadMarkRoster();
}

function onMarkTeacherChange() {
    const teacher = teachers.value.find((t) => t.id === mark.teacher_id);
    if (teacher?.school_class_id) {
        mark.school_class_id = teacher.school_class_id;
        mark.section_id = null;
    }
    loadMarkRoster();
}

async function loadMarkRoster() {
    if (!mark.school_class_id) {
        people.value = [];
        return;
    }
    markLoading.value = true;
    try {
        const params = { date: mark.date, school_class_id: mark.school_class_id };
        if (mark.branch_id) params.branch_id = mark.branch_id;
        if (mark.section_id) params.section_id = mark.section_id;
        if (mark.teacher_id) params.teacher_id = mark.teacher_id;
        const { data } = await client.get('/attendance/student', { params });
        people.value = data.people;
    } finally {
        markLoading.value = false;
    }
}

function markAll(status) {
    people.value.forEach((p) => { p.status = status; });
}

async function saveMark() {
    const records = people.value
        .filter((p) => p.status)
        .map((p) => ({ attendable_id: p.id, status: p.status, remarks: p.remarks || null }));
    if (!records.length) {
        pushToast('Mark at least one student before saving.', 'error');
        return;
    }
    markSaving.value = true;
    try {
        await client.post('/attendance/student', { date: mark.date, records });
        pushToast(`Saved attendance for ${records.length} student(s).`, 'success');
        await loadMarkRoster();
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        markSaving.value = false;
    }
}

function onMonthBranchChange() {
    monthGridStudents.value = [];
}

async function loadMonthGrid() {
    if (!monthMark.school_class_id || !monthMark.month) return;
    monthGridLoading.value = true;
    try {
        const params = { month: monthMark.month, school_class_id: monthMark.school_class_id };
        if (monthMark.branch_id) params.branch_id = monthMark.branch_id;
        if (monthMark.section_id) params.section_id = monthMark.section_id;
        const { data } = await client.get('/attendance/student/month', { params });
        monthGridDays.value = data.days;
        monthGridStudents.value = data.students;
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Failed to load month.', 'error');
    } finally {
        monthGridLoading.value = false;
    }
}

function cycleCell(student, cellIndex) {
    const cell = student.cells[cellIndex];
    if (cell.status === 'Holiday') return;
    const order = [null, 'Present', 'Absent'];
    cell.status = order[(order.indexOf(cell.status ?? null) + 1) % order.length];
}

function presentTotal(student) {
    return student.cells.reduce((sum, c) => sum + (c.status === 'Present' ? 1 : 0), 0);
}

async function saveMonthGrid() {
    const records = [];
    monthGridStudents.value.forEach((student) => {
        student.cells.forEach((cell) => {
            if (cell.status === 'Present' || cell.status === 'Absent') {
                records.push({ attendable_id: student.id, date: cell.date, status: cell.status });
            }
        });
    });
    if (!records.length) {
        pushToast('Mark at least one day before saving.', 'error');
        return;
    }
    monthGridSaving.value = true;
    try {
        const { data } = await client.post('/attendance/student/month', { month: monthMark.month, records });
        pushToast(`Saved ${data.saved} day-record(s).`, 'success');
        await loadMonthGrid();
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        monthGridSaving.value = false;
    }
}

async function loadHistory() {
    historyLoading.value = true;
    try {
        const params = { from: history.from, to: history.to };
        if (history.branch_id) params.branch_id = history.branch_id;
        if (history.school_class_id) params.school_class_id = history.school_class_id;
        if (history.section_id) params.section_id = history.section_id;
        if (history.teacher_id) {
            const teacher = teachers.value.find((t) => t.id === history.teacher_id);
            if (teacher?.school_class_id && !params.school_class_id) {
                params.school_class_id = teacher.school_class_id;
            }
        }
        const { data } = await client.get('/attendance/student/sessions', { params });
        historySessions.value = data.sessions || [];
    } finally {
        historyLoading.value = false;
    }
}

function openSessionInMark(date) {
    mark.date = String(date).slice(0, 10);
    if (history.branch_id) mark.branch_id = history.branch_id;
    if (history.school_class_id) mark.school_class_id = history.school_class_id;
    if (history.section_id) mark.section_id = history.section_id;
    activeTab.value = 'mark';
    loadMarkRoster();
}

function exportHistoryCsv() {
    const lines = [['Date', 'Marked', 'Present', 'Absent']];
    historySessions.value.forEach((s) => {
        lines.push([s.date, s.marked_count, s.present_count, s.absent_count]);
    });
    downloadCsv(lines, 'attendance-history.csv');
}

async function loadExcelSheet() {
    if (!excel.school_class_id || !excel.month) return;
    excelLoading.value = true;
    try {
        const params = { month: excel.month, school_class_id: excel.school_class_id };
        if (excel.branch_id) params.branch_id = excel.branch_id;
        if (excel.section_id) params.section_id = excel.section_id;
        const { data } = await client.get('/attendance/student/sheet', { params });
        excelDays.value = data.days || [];
        excelRows.value = data.rows || [];
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not load sheet.', 'error');
        excelRows.value = [];
    } finally {
        excelLoading.value = false;
    }
}

async function exportExcelSheet() {
    const params = new URLSearchParams({ month: excel.month, school_class_id: excel.school_class_id });
    if (excel.branch_id) params.set('branch_id', excel.branch_id);
    if (excel.section_id) params.set('section_id', excel.section_id);
    const response = await client.get(`/attendance/student/sheet/export?${params}`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance-sheet-${excel.month}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

async function loadMine() {
    mineLoading.value = true;
    try {
        const { data } = await client.get('/attendance/mine', { params: { from: mine.from, to: mine.to } });
        mineRecords.value = data.records || [];
        minePerson.value = data.person;
        mineMessage.value = data.message || '';
    } finally {
        mineLoading.value = false;
    }
}

function downloadCsv(rows, filename) {
    const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

watch(activeTab, (tab) => {
    if (tab === 'history' && !historySessions.value.length) loadHistory();
    if (tab === 'mine' && !mineRecords.value.length && !mineMessage.value) loadMine();
});

onMounted(loadLookups);
</script>
