<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Welcome, {{ data?.student?.name?.split(' ')[0] || '' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ data?.student?.school_class }} <span v-if="data?.student?.section">— {{ data.student.section }}</span> · Roll No. {{ data?.student?.roll_no || '—' }}</p>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-if="isModuleVisible('attendance')" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Attendance</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.attendance_percentage !== null ? data.attendance_percentage + '%' : '—' }}</p>
                <RouterLink to="/attendance" class="mt-2 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View details →</RouterLink>
            </div>
            <div v-if="isModuleVisible('pending_fees')" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Pending Fees</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.pending_due !== null ? '₹' + formatNumber(data.pending_due) : '—' }}</p>
                <RouterLink to="/fees" class="mt-2 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View fees →</RouterLink>
            </div>
            <div v-if="isModuleVisible('exam_schedule')" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Next Exam</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ data.next_exam ? `${data.next_exam.subject} — ${formatDate(data.next_exam.date)}` : 'None scheduled' }}</p>
                <RouterLink to="/examination" class="mt-2 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View schedule →</RouterLink>
            </div>
            <div v-if="isModuleVisible('notices')" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Recent Notices</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ data.recent_notices }}</p>
                <RouterLink to="/events-calendar" class="mt-2 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View notices →</RouterLink>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="link in quickLinks"
                :key="link.path"
                :to="link.path"
                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow dark:border-slate-800 dark:bg-slate-900"
            >
                <span class="text-xl">{{ link.icon }}</span>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ link.label }}</span>
            </RouterLink>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import client from '../api/client';
import { isModuleVisible } from '../store';

const loading = ref(true);
const data = ref(null);

const quickLinks = computed(() => [
    { path: '/profile', label: 'My Profile', icon: '🧑‍🎓', key: 'profile' },
    { path: '/examination', label: 'Examination', icon: '📝', key: 'exam_schedule' },
    { path: '/fees', label: 'Fees', icon: '💳', key: 'fee_summary' },
    { path: '/homework', label: 'Homework', icon: '📚', key: 'homework' },
    { path: '/library', label: 'Library', icon: '📖', key: 'library' },
    { path: '/documents', label: 'My Documents', icon: '📄', key: 'documents' },
].filter((l) => isModuleVisible(l.key)));

function formatNumber(n) {
    return Number(n || 0).toLocaleString('en-IN');
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

onMounted(async () => {
    try {
        const { data: res } = await client.get('/dashboard');
        data.value = res;
    } finally {
        loading.value = false;
    }
});
</script>
