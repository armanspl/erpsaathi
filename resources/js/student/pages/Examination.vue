<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Examination</h1>

        <div class="flex flex-wrap gap-1.5 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in visibleTabs"
                :key="t.key"
                type="button"
                class="rounded-t-lg px-3.5 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- Exam Schedule -->
        <div v-if="tab === 'schedule'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr><th class="px-4 py-3">Exam</th><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Time</th><th class="px-4 py-3">Room</th><th class="px-4 py-3">Max Marks</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="s in schedule" :key="s.id">
                        <td class="px-4 py-2.5">{{ s.exam_name }}</td>
                        <td class="px-4 py-2.5">{{ s.subject }}</td>
                        <td class="px-4 py-2.5">{{ s.date }}</td>
                        <td class="px-4 py-2.5">{{ s.start_time }}<span v-if="s.end_time"> – {{ s.end_time }}</span></td>
                        <td class="px-4 py-2.5">{{ s.room || '—' }}</td>
                        <td class="px-4 py-2.5">{{ s.max_marks }}</td>
                    </tr>
                    <tr v-if="!schedule.length"><td colspan="6" class="px-4 py-6 text-center text-slate-400">No exams scheduled.</td></tr>
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

        <!-- Marks / Grades -->
        <div v-else-if="tab === 'marks'" class="space-y-3">
            <div v-for="row in marks" :key="row.exam_id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.exam_name }}</h3>
                    <span class="text-xs font-medium" :class="row.result === 'Pass' ? 'text-emerald-600' : 'text-rose-600'">{{ row.result }} · {{ row.percentage }}% · Grade {{ row.grade || '—' }}</span>
                </div>
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in row.subjects" :key="s.subject_id">
                            <td class="py-1.5 text-slate-600 dark:text-slate-300">{{ s.subject_name }}</td>
                            <td class="py-1.5 text-right font-medium text-slate-700 dark:text-slate-200">{{ s.is_absent ? 'Ab' : (s.marks_obtained ?? '—') }} / {{ s.max_marks }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!marks.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No published results yet.</p>
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

const ALL_TABS = [
    { key: 'schedule', label: 'Exam Schedule', moduleKey: 'exam_schedule' },
    { key: 'admit_card', label: 'Admit Card', moduleKey: 'admit_card' },
    { key: 'marks', label: 'Marks / Grades', moduleKey: 'marks' },
    { key: 'report_card', label: 'Report Card', moduleKey: 'report_card' },
    { key: 'subject_performance', label: 'Subject-wise Performance', moduleKey: 'subject_performance' },
    { key: 'previous_results', label: 'Previous Results', moduleKey: 'previous_results' },
];
const visibleTabs = ALL_TABS.filter((t) => isModuleVisible(t.moduleKey));
const tab = ref(visibleTabs[0]?.key || 'schedule');

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
