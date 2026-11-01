<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Student Attendance</h1>
            <div class="inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
                <button
                    v-for="t in [{ key: 'students', label: 'Students' }, { key: 'working-days', label: 'Working Days' }]"
                    :key="t.key"
                    type="button"
                    class="rounded-md px-3.5 py-1.5 text-sm font-medium transition"
                    :class="activeTab === t.key ? 'bg-white text-primary-700 shadow-sm dark:bg-slate-700 dark:text-primary-300' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    @click="setTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>
        </div>

        <!-- School working days per month -->
        <div v-if="activeTab === 'working-days'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">Working Days</h2>
                    <p class="mt-1 text-xs text-slate-400">Days the school was open each month. Shown on the dashboard, and filled in automatically as each student's Working Days.</p>
                </div>
                <div class="flex items-end gap-2">
                    <div>
                        <label class="form-label">Session</label>
                        <select v-model.number="wd.session_start_year" class="form-input" @change="loadWorkingDays">
                            <option v-for="y in wd.years" :key="y" :value="y">{{ y }}-{{ String((y + 1) % 100).padStart(2, '0') }}</option>
                        </select>
                    </div>
                    <button type="button" class="btn-primary" :disabled="wd.saving || wd.loading || !wd.ready || !wdDirty" @click="saveWorkingDays">
                        {{ wd.saving ? 'Saving...' : 'Save working days' }}
                    </button>
                </div>
            </div>
            <p v-if="!wd.ready && !wd.loading" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ wd.message }}</p>
            <div v-if="wd.loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="w-full max-w-md text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Month</th>
                            <th class="px-3 py-2 text-right font-semibold">Working Days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="m in wd.months" :key="`${m.year}-${m.month}`">
                            <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ m.label }}</td>
                            <td class="px-3 py-2 text-right">
                                <input v-model="m.working_days" type="number" min="0" max="31" class="form-input ml-auto !w-24 !py-1 text-right !text-sm" placeholder="—" :disabled="!wd.ready" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-slate-200 dark:border-slate-700">
                        <tr class="font-semibold">
                            <td class="px-3 py-2.5">Total Working Days</td>
                            <td class="px-3 py-2.5 text-right text-primary-600 dark:text-primary-400">{{ wdTotal }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="mt-2 text-xs text-slate-400">Leave a month blank if it isn't known yet.</p>
            </div>
        </div>

        <template v-if="activeTab === 'students'">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="form-label">Session</label>
                    <select v-model="monthly.session_start_year" class="form-input" @change="loadMonthly">
                        <option v-for="y in monthlyYears" :key="y" :value="y">{{ y }}-{{ String((y + 1) % 100).padStart(2, '0') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="monthly.branch_id" class="form-input" @change="loadMonthly">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="monthly.school_class_id" class="form-input" @change="onMonthlyClassChange">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="monthly.section_id" class="form-input" @change="loadMonthly">
                        <option :value="null">All sections</option>
                        <option v-for="s in monthlySections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Search student</label>
                    <input v-model="monthly.search" type="search" class="form-input" placeholder="Name or Adm No." @keyup.enter="loadMonthly" />
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn-primary" :disabled="monthlyLoading" @click="loadMonthly">
                    {{ monthlyLoading ? 'Loading...' : 'Apply filters' }}
                </button>
                <button v-if="selectedMonthlyStudent" type="button" class="btn-outline" @click="closeMonthlyEditor">
                    Back to list
                </button>
            </div>
            <p class="mt-3 text-xs text-slate-400">Select a class (or search), open a student, enter Days Present for each month (Working Days come from the Working Days tab), then Save.</p>
        </div>

        <div v-if="monthlyLoading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>

        <template v-else-if="selectedMonthlyDetail">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">
                            {{ selectedMonthlyStudent?.name }}
                            <span class="ml-2 text-sm font-normal text-slate-400">Adm No. {{ selectedMonthlyStudent?.admission_no || '—' }}</span>
                        </h2>
                        <p class="mt-1 text-xs text-slate-400">
                            Monthly totals for session {{ monthly.session_start_year }}-{{ String((monthly.session_start_year + 1) % 100).padStart(2, '0') }}
                            — leave both fields blank to clear a month
                        </p>
                    </div>
                    <button type="button" class="btn-primary" :disabled="monthlySaving" @click="saveMonthlyDetail">
                        {{ monthlySaving ? 'Saving...' : 'Save months' }}
                    </button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Month</th>
                                <th class="px-3 py-2 font-semibold text-right">Working Days</th>
                                <th class="px-3 py-2 font-semibold text-right">Days Present</th>
                                <th class="px-3 py-2 font-semibold text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(m, idx) in selectedMonthlyDetail.months" :key="`${m.year}-${m.month}-${idx}`">
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ m.month_label }} {{ m.year }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-model="m.working_days"
                                        type="number"
                                        min="0"
                                        max="31"
                                        class="form-input ml-auto !w-20 !py-1 text-right !text-sm"
                                        :class="m.from_school && 'bg-sky-50 dark:bg-sky-500/10'"
                                        placeholder="—"
                                        @input="m.from_school = false; recalcMonthlyRow(m)"
                                    />
                                    <span v-if="m.from_school" class="mt-0.5 block text-[10px] text-sky-600">from Working Days</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-model="m.days_present"
                                        type="number"
                                        min="0"
                                        max="31"
                                        class="form-input ml-auto !w-20 !py-1 text-right !text-sm"
                                        placeholder="—"
                                        @input="recalcMonthlyRow(m)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right font-medium text-slate-800 dark:text-slate-100">{{ formatPct(m.percentage) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-200 dark:border-slate-700">
                            <tr class="bg-slate-50 font-medium dark:bg-slate-800/50">
                                <td class="px-3 py-2.5">Total (Mar–Sep)</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.half1.working_days || '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.half1.days_present || '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ formatPct(liveMonthlyTotals.half1.percentage) }}</td>
                            </tr>
                            <tr class="bg-slate-50 font-medium dark:bg-slate-800/50">
                                <td class="px-3 py-2.5">Total (Oct–Mar)</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.half2.working_days || '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.half2.days_present || '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ formatPct(liveMonthlyTotals.half2.percentage) }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-3 py-2.5">Full Year Total</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.full.working_days || '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ liveMonthlyTotals.full.days_present || '—' }}</td>
                                <td class="px-3 py-2.5 text-right text-primary-600 dark:text-primary-400">{{ formatPct(liveMonthlyTotals.full.percentage) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div v-if="!monthly.school_class_id && !monthly.search?.trim()" class="px-6 py-16 text-center text-sm text-slate-400">
                Select a class or search a student to enter or edit monthly attendance.
            </div>
            <div v-else-if="!monthlyStudents.length" class="px-6 py-16 text-center text-sm text-slate-400">
                No students match these filters.
            </div>
            <table v-else class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('admission_no')">Adm No. {{ sortArrow('admission_no') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Name {{ sortArrow('name') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('days_present_total')">Days Present {{ sortArrow('days_present_total') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('working_days_total')">Working Days {{ sortArrow('working_days_total') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('full_percentage')">Full Year % {{ sortArrow('full_percentage') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="s in sortedMonthlyStudents" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ s.admission_no || '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ s.days_present_total || 0 }}</td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ s.working_days_total || 0 }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ formatPct(s.full_percentage) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-xs font-semibold text-primary-600 hover:underline" @click="openMonthlyStudent(s)">
                                {{ (s.working_days_total || 0) > 0 ? 'Edit months' : 'Add months' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();
// ?tab=working-days opens the school Working Days tab (the dashboard's "Add" button links here).
const activeTab = ref(route.query.tab === 'working-days' ? 'working-days' : 'students');
function setTab(key) {
    activeTab.value = key;
    router.replace({ query: { ...route.query, tab: key === 'students' ? undefined : key } });
    if (key === 'working-days' && !wd.months.length) loadWorkingDays();
}

// --- School working days per month ---
const wd = reactive({ session_start_year: null, years: [], months: [], saved: '', ready: true, message: '', loading: false, saving: false });
const wdTotal = computed(() => wd.months.reduce((sum, m) => sum + (toNullableNumber(m.working_days) ?? 0), 0));
const wdDirty = computed(() => JSON.stringify(wd.months.map((m) => toNullableNumber(m.working_days))) !== wd.saved);
// session start year => { 'YYYY-MM': working days } — used to pre-fill a student's blank months.
const schoolDaysByYear = reactive({});

function applyWorkingDays(data) {
    wd.session_start_year = data.session_start_year;
    wd.years = data.available_years || [data.session_start_year];
    wd.months = (data.months || []).map((m) => ({ ...m, working_days: m.working_days ?? '' }));
    wd.saved = JSON.stringify(wd.months.map((m) => toNullableNumber(m.working_days)));
    wd.ready = data.ready !== false;
    wd.message = data.message || '';
    applyWorkingDaysCache(data);
}

async function loadWorkingDays() {
    wd.loading = true;
    try {
        const params = wd.session_start_year ? { session_start_year: wd.session_start_year } : { session_start_year: monthly.session_start_year };
        const { data } = await client.get('/attendance/school-working-days', { params });
        applyWorkingDays(data);
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Could not load working days.', 'error');
    } finally {
        wd.loading = false;
    }
}

async function saveWorkingDays() {
    wd.saving = true;
    try {
        const { data } = await client.put('/attendance/school-working-days', {
            session_start_year: wd.session_start_year,
            months: wd.months.map((m) => ({ year: m.year, month: m.month, working_days: toNullableNumber(m.working_days) })),
        });
        applyWorkingDays(data);
        pushToast('Working days saved.', 'success');
    } catch (err) {
        const errors = err?.response?.data?.errors || {};
        pushToast(Object.values(errors)[0]?.[0] || err?.response?.data?.message || 'Could not save working days.', 'error');
    } finally {
        wd.saving = false;
    }
}

async function schoolDaysFor(year) {
    if (!schoolDaysByYear[year]) {
        try {
            const { data } = await client.get('/attendance/school-working-days', { params: { session_start_year: year } });
            applyWorkingDaysCache(data);
        } catch {
            schoolDaysByYear[year] = {}; // pre-fill is a convenience — the editor still works without it
        }
    }
    return schoolDaysByYear[year] || {};
}
function applyWorkingDaysCache(data) {
    schoolDaysByYear[data.session_start_year] = Object.fromEntries(
        (data.months || []).filter((m) => m.working_days != null).map((m) => [`${m.year}-${m.month}`, m.working_days]),
    );
}

// A month pre-filled from Working Days only counts (and is saved) once Days Present is entered.
function isPrefillOnly(m) {
    return m.from_school && toNullableNumber(m.days_present) === null;
}

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);

const monthly = reactive({
    session_start_year: new Date().getFullYear(),
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
});
const monthlyYears = ref([new Date().getFullYear()]);
const monthlyStudents = ref([]);
const monthlyLoading = ref(false);
const monthlySaving = ref(false);
const selectedMonthlyStudent = ref(null);
const selectedMonthlyDetail = ref(null);

const monthlySections = computed(() => sections.value.filter((s) => s.school_class_id === monthly.school_class_id));
const liveMonthlyTotals = computed(() => summarizeEditableMonths(selectedMonthlyDetail.value?.months || []));

const sortKey = ref('name');
const sortDir = ref('asc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

const sortedMonthlyStudents = computed(() => {
    return [...monthlyStudents.value].sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

function formatPct(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${Number(value).toFixed(1)}%`;
}

function toNullableNumber(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

function recalcMonthlyRow(row) {
    const wd = toNullableNumber(row.working_days);
    const dp = toNullableNumber(row.days_present);
    if ((wd === null && dp === null) || isPrefillOnly(row)) {
        row.percentage = null;
        return;
    }
    const working = wd ?? 0;
    const present = dp ?? 0;
    row.percentage = working > 0 ? Math.round((present / working) * 10000) / 100 : 0;
}

function summarizeEditableMonths(months) {
    const half1 = { working_days: 0, days_present: 0 };
    const half2 = { working_days: 0, days_present: 0 };
    months.forEach((m, index) => {
        const wd = toNullableNumber(m.working_days);
        const dp = toNullableNumber(m.days_present);
        if ((wd === null && dp === null) || isPrefillOnly(m)) return;
        const bucket = index < 7 ? half1 : half2;
        bucket.working_days += wd ?? 0;
        bucket.days_present += dp ?? 0;
    });
    const pct = (b) => (b.working_days > 0 ? Math.round((b.days_present / b.working_days) * 10000) / 100 : null);
    const full = {
        working_days: half1.working_days + half2.working_days,
        days_present: half1.days_present + half2.days_present,
    };
    return {
        half1: { ...half1, percentage: pct(half1) },
        half2: { ...half2, percentage: pct(half2) },
        full: { ...full, percentage: pct(full) },
    };
}

function emptyMonthShell(sessionStartYear) {
    const order = [
        [3, 0], [4, 0], [5, 0], [6, 0], [7, 0], [8, 0], [9, 0],
        [10, 0], [11, 0], [12, 0], [1, 1], [2, 1], [3, 1],
    ];
    return order.map(([month, yearOffset]) => {
        const year = sessionStartYear + yearOffset;
        return {
            month,
            year,
            month_label: new Date(year, month - 1, 1).toLocaleString('en-IN', { month: 'long' }),
            working_days: '',
            days_present: '',
            percentage: null,
            imported: false,
        };
    });
}

async function loadMonthly() {
    monthlyLoading.value = true;
    selectedMonthlyStudent.value = null;
    selectedMonthlyDetail.value = null;
    try {
        const params = { session_start_year: monthly.session_start_year };
        if (monthly.branch_id) params.branch_id = monthly.branch_id;
        if (monthly.school_class_id) params.school_class_id = monthly.school_class_id;
        if (monthly.section_id) params.section_id = monthly.section_id;
        if (monthly.search?.trim()) params.search = monthly.search.trim();
        const { data } = await client.get('/attendance/student/monthly-summaries', { params });
        if (data.available_years?.length) {
            monthlyYears.value = data.available_years;
            if (!monthlyYears.value.includes(Number(monthly.session_start_year))) {
                monthly.session_start_year = data.session_start_year;
            }
        } else if (data.session_start_year) {
            monthlyYears.value = [data.session_start_year];
            monthly.session_start_year = data.session_start_year;
        }
        monthlyStudents.value = data.students || [];
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Failed to load monthly attendance.', 'error');
        monthlyStudents.value = [];
    } finally {
        monthlyLoading.value = false;
    }
}

function onMonthlyClassChange() {
    monthly.section_id = null;
    loadMonthly();
}

function openMonthlyStudent(student) {
    selectedMonthlyStudent.value = student;
    const sourceMonths = student.months?.length ? student.months : emptyMonthShell(monthly.session_start_year);
    const months = sourceMonths.map((m) => ({
        ...m,
        working_days: m.working_days === null || m.working_days === undefined ? '' : m.working_days,
        days_present: m.days_present === null || m.days_present === undefined ? '' : m.days_present,
        percentage: m.percentage ?? null,
    }));
    selectedMonthlyDetail.value = {
        months,
        half1: student.half1 || { working_days: 0, days_present: 0, percentage: null },
        half2: student.half2 || { working_days: 0, days_present: 0, percentage: null },
        full: student.full || { working_days: 0, days_present: 0, percentage: null },
    };
    prefillWorkingDays(months, monthly.session_start_year);
}

// Blank Working Days on a student's months come from the school's Working Days tab.
async function prefillWorkingDays(months, year) {
    const school = await schoolDaysFor(year);
    if (selectedMonthlyDetail.value?.months !== months) return; // another student opened meanwhile
    for (const m of months) {
        const days = school[`${m.year}-${m.month}`];
        if (days != null && toNullableNumber(m.working_days) === null) {
            m.working_days = days;
            m.from_school = true;
            recalcMonthlyRow(m);
        }
    }
}

function closeMonthlyEditor() {
    selectedMonthlyStudent.value = null;
    selectedMonthlyDetail.value = null;
}

async function saveMonthlyDetail() {
    if (!selectedMonthlyStudent.value || !selectedMonthlyDetail.value) return;
    monthlySaving.value = true;
    try {
        const months = selectedMonthlyDetail.value.months.map((m) => ({
            month: m.month,
            year: m.year,
            working_days: isPrefillOnly(m) ? null : toNullableNumber(m.working_days),
            days_present: toNullableNumber(m.days_present),
        }));
        const { data } = await client.post('/attendance/student/monthly-summaries', {
            student_id: selectedMonthlyStudent.value.id,
            session_start_year: monthly.session_start_year,
            months,
        });
        pushToast(data.message || 'Monthly attendance saved.', 'success');
        if (data.student) {
            selectedMonthlyStudent.value = { ...selectedMonthlyStudent.value, ...data.student };
            openMonthlyStudent(data.student);
        }
        const listParams = { session_start_year: monthly.session_start_year };
        if (monthly.branch_id) listParams.branch_id = monthly.branch_id;
        if (monthly.school_class_id) listParams.school_class_id = monthly.school_class_id;
        if (monthly.section_id) listParams.section_id = monthly.section_id;
        if (monthly.search?.trim()) listParams.search = monthly.search.trim();
        const list = await client.get('/attendance/student/monthly-summaries', { params: listParams });
        monthlyStudents.value = list.data.students || [];
        if (list.data.available_years?.length) monthlyYears.value = list.data.available_years;
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        monthlySaving.value = false;
    }
}

async function loadLookups() {
    const lookups = await fetchAcademicsLookups();
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
}

onMounted(async () => {
    await loadLookups();
    await loadMonthly();
    if (activeTab.value === 'working-days') loadWorkingDays();
});
</script>
