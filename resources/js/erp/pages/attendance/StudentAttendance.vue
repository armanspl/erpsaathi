<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Student Attendance</h1>
        </div>

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
            <p class="mt-3 text-xs text-slate-400">Select a class (or search), open a student, enter Working Days + Days Present for each month, then Save.</p>
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
                                        placeholder="—"
                                        @input="recalcMonthlyRow(m)"
                                    />
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
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adm No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Days Present</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Working Days</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Full Year %</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="s in monthlyStudents" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
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
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

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
    if (wd === null && dp === null) {
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
        if (wd === null && dp === null) return;
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
            working_days: toNullableNumber(m.working_days),
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
});
</script>
