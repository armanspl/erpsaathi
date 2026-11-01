<template>
    <div class="sd space-y-5">
        <h1 class="sd-page-title">Examination</h1>

        <nav class="sd-tabs">
            <button
                v-for="t in visibleTabs"
                :key="t.key"
                type="button"
                class="sd-tab"
                :class="{ 'is-active': tab === t.key }"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </nav>

        <!-- Exam Schedule -->
        <div v-if="tab === 'schedule'" class="sd-card overflow-x-auto">
            <table class="sd-table">
                <thead>
                    <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Time</th><th>Room</th><th>Max Marks</th></tr>
                </thead>
                <tbody>
                    <tr v-for="s in schedule" :key="s.id">
                        <td class="font-semibold">{{ s.exam_name }}</td>
                        <td>{{ s.subject }}</td>
                        <td>{{ s.date }}</td>
                        <td>{{ s.start_time }}<span v-if="s.end_time"> – {{ s.end_time }}</span></td>
                        <td>{{ s.room || '—' }}</td>
                        <td>{{ s.max_marks }}</td>
                    </tr>
                    <tr v-if="!schedule.length"><td colspan="6" class="sd-empty">No exams scheduled.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Admit Card -->
        <div v-else-if="tab === 'admit_card'" class="space-y-2">
            <div v-for="e in upcomingExams" :key="e.exam_id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ e.exam_name }}</span>
                <button type="button" class="btn-outline !py-1.5 !text-xs" @click="downloadFile(`/exams/admit-card/${e.exam_id}`, `admit-card-${e.exam_id}.pdf`)">Download Admit Card</button>
            </div>
            <p v-if="!upcomingExams.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No exams found.</p>
        </div>

        <!-- Results -->
        <div v-else-if="tab === 'marks'" class="space-y-4">
            <section v-for="row in marks" :key="row.exam_id" class="sd-card">
                <header class="sd-card__head">
                    <h2><AppIcon name="graduation" class="h-4 w-4" /> {{ row.exam_name }}</h2>
                    <span class="sd-pill" :class="row.result === 'Pass' ? 'is-ok' : 'is-bad'">{{ row.result }}</span>
                </header>
                <dl class="sd-att__stats sd-result-stats">
                    <div><dt>Marks</dt><dd>{{ num(row.obtained) }} / {{ num(row.max_total) }}</dd></div>
                    <div><dt>Percentage</dt><dd>{{ row.percentage }}%</dd></div>
                    <div><dt>Grade</dt><dd>{{ row.grade || '—' }}</dd></div>
                    <div v-if="row.rank"><dt>Class rank</dt><dd>#{{ row.rank }}</dd></div>
                </dl>
                <div class="overflow-x-auto">
                    <table class="sd-table">
                        <thead><tr><th>Subject</th><th class="text-right">Marks</th><th class="text-right">Max</th><th class="w-1/3">Score</th></tr></thead>
                        <tbody>
                            <tr v-for="s in row.subjects" :key="s.subject_id">
                                <td class="font-semibold">{{ s.subject_name }}</td>
                                <td class="text-right" :class="s.is_absent ? 'sd-bad font-semibold' : ''">{{ s.is_absent ? 'Absent' : (s.marks_obtained ?? '—') }}</td>
                                <td class="text-right sd-muted">{{ num(s.max_marks) }}</td>
                                <td>
                                    <div class="sd-meter"><span :style="{ width: subjectPct(s) + '%' }" :class="subjectPct(s) < 33 ? 'is-low' : ''"></span></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="isModuleVisible('report_card')" class="flex justify-end">
                    <button type="button" class="btn-outline !py-1.5 !text-xs" @click="downloadFile(`/exams/report-card/${row.exam_id}`, `report-card-${row.exam_id}.pdf`)">Download Report Card</button>
                </div>
            </section>
            <p v-if="!marks.length" class="sd-card sd-empty">No published results yet. Results appear here once the school publishes them.</p>
        </div>

        <!-- Report Card -->
        <div v-else-if="tab === 'report_card'" class="space-y-2">
            <div v-for="row in marks" :key="row.exam_id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ row.exam_name }}</span>
                <button type="button" class="btn-outline !py-1.5 !text-xs" @click="downloadFile(`/exams/report-card/${row.exam_id}`, `report-card-${row.exam_id}.pdf`)">Download Report Card</button>
            </div>
            <p v-if="!marks.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No published results yet.</p>
        </div>

        <!-- Subject-wise Performance -->
        <div v-else-if="tab === 'subject_performance'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-for="s in subjectPerformance" :key="s.subject_name" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ s.subject_name }}</h3>
                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400">Avg {{ s.average_percentage ?? '—' }}%</span>
                </div>
                <ul class="space-y-1 text-xs text-slate-600 dark:text-slate-300">
                    <li v-for="e in s.entries" :key="e.exam_id" class="flex justify-between">
                        <span>{{ e.exam_name }}</span>
                        <span>{{ e.is_absent ? 'Ab' : (e.marks_obtained ?? '—') }} / {{ e.max_marks }}</span>
                    </li>
                </ul>
            </div>
            <p v-if="!subjectPerformance.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900 sm:col-span-2">No published results yet.</p>
        </div>

        <!-- Previous Results -->
        <div v-else-if="tab === 'previous_results'" class="space-y-3">
            <div v-for="row in previousResults" :key="row.exam_id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.exam_name }}</h3>
                    <span class="text-xs font-medium" :class="row.result === 'Pass' ? 'text-emerald-600' : 'text-rose-600'">{{ row.result }} · {{ row.percentage }}%</span>
                </div>
            </div>
            <p v-if="!previousResults.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No results from previous sessions.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import client from '../api/client';
import { isModuleVisible } from '../store';
import { downloadFile } from '../utils/download';
import { useRoute } from 'vue-router';
import AppIcon from '../../erp/components/common/AppIcon.vue';

function num(n) {
    return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}
function subjectPct(s) {
    if (s.is_absent || !(Number(s.max_marks) > 0)) return 0;
    return Math.min(100, Math.round((Number(s.marks_obtained || 0) / Number(s.max_marks)) * 100));
}

const ALL_TABS = [
    { key: 'marks', label: 'Results', moduleKey: 'marks' },
    { key: 'schedule', label: 'Exam Schedule', moduleKey: 'exam_schedule' },
    { key: 'admit_card', label: 'Admit Card', moduleKey: 'admit_card' },
    { key: 'report_card', label: 'Report Card', moduleKey: 'report_card' },
    { key: 'subject_performance', label: 'Subject-wise Performance', moduleKey: 'subject_performance' },
    { key: 'previous_results', label: 'Previous Results', moduleKey: 'previous_results' },
];
const visibleTabs = ALL_TABS.filter((t) => isModuleVisible(t.moduleKey));
const route = useRoute();
const tab = ref(visibleTabs.find((t) => t.key === route.query.tab)?.key || visibleTabs[0]?.key || 'schedule');

const schedule = ref([]);
const marks = ref([]);
const subjectPerformance = ref([]);
const previousResults = ref([]);
const loaded = { schedule: false, admit_card: false, marks: false, report_card: false, subject_performance: false, previous_results: false };

const upcomingExams = computed(() => {
    const seen = new Map();
    for (const s of schedule.value) {
        if (!seen.has(s.exam_id)) seen.set(s.exam_id, { exam_id: s.exam_id, exam_name: s.exam_name });
    }
    return Array.from(seen.values());
});

async function loadTab(key) {
    if (loaded[key]) return;
    if (key === 'schedule' || key === 'admit_card') {
        if (loaded.schedule) return;
        const { data } = await client.get('/exams/schedule');
        schedule.value = data;
        loaded.schedule = true;
        loaded.admit_card = true;
        return;
    }
    if (key === 'marks' || key === 'report_card') {
        if (loaded.marks) return;
        const { data } = await client.get('/exams/marks');
        marks.value = data;
        loaded.marks = true;
        loaded.report_card = true;
        return;
    }
    if (key === 'subject_performance') {
        const { data } = await client.get('/exams/subject-performance');
        subjectPerformance.value = data;
        loaded.subject_performance = true;
        return;
    }
    if (key === 'previous_results') {
        const { data } = await client.get('/exams/previous-results');
        previousResults.value = data;
        loaded.previous_results = true;
    }
}

watch(tab, (key) => loadTab(key), { immediate: true });
</script>
