<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Exam Reports</h1>
            <Breadcrumb :items="['Dashboard', 'Exam Management', 'Exam Reports']" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">Exam</label>
                    <select v-model="examId" class="form-input" @change="load">
                        <option :value="null">Select exam</option>
                        <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="classId" class="form-input" :disabled="!examId" @change="load">
                        <option :value="null">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <template v-if="report">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Pass %" :value="`${report.pass_percentage}%`" color="emerald" icon="✅" />
                <StatCard label="Fail %" :value="`${report.fail_percentage}%`" color="rose" icon="⛔" />
                <StatCard label="Topper" :value="report.topper?.name || '—'" color="indigo" icon="🏆" />
                <StatCard label="Average %" :value="`${report.average_percentage}%`" color="sky" icon="📊" />
            </div>

            <div v-if="report.topper" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                🏆 <strong>{{ report.topper.name }}</strong> ({{ report.topper.admission_no }}) topped with <strong>{{ report.topper.percentage }}%</strong>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Rank</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Percentage</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="!report.rows.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">No results yet for this exam.</td>
                        </tr>
                        <tr v-for="r in report.rows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">#{{ r.rank }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.percentage }}%</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.grade || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.result === 'Pass' ? 'Active' : 'Inactive')">{{ r.result }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
        <div v-else class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
            Select an exam to view its report.
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';

const exams = ref([]);
const classes = ref([]);
const examId = ref(null);
const classId = ref(null);
const report = ref(null);

client.get('/exams').then(({ data }) => (exams.value = data));
client.get('/academics/classes').then(({ data }) => (classes.value = data));

async function load() {
    if (!examId.value) {
        report.value = null;
        return;
    }
    const { data } = await client.get(`/exams/${examId.value}/report`, { params: { school_class_id: classId.value } });
    report.value = data;
}
</script>
