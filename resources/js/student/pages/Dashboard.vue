<template>
    <div class="sd space-y-5">
        <!-- Welcome -->
        <section class="sd-hero">
            <div class="sd-hero__avatar">{{ initials }}</div>
            <div class="min-w-0 flex-1">
                <p class="sd-hero__kicker">{{ greeting }}</p>
                <h1 class="sd-hero__title">{{ data?.student?.name || studentStore.user.name || 'Student' }}</h1>
                <p class="sd-hero__sub">
                    {{ data?.student?.school_class || '—' }}<span v-if="data?.student?.section"> — {{ data.student.section }}</span>
                    · Roll No. {{ data?.student?.roll_no || '—' }}
                    · Adm. No. {{ data?.student?.admission_no || studentStore.user.admission_no || '—' }}
                </p>
            </div>
            <span v-if="data?.fees?.session" class="sd-hero__chip">Session {{ data.fees.session }}</span>
        </section>

        <div v-if="loading" class="sd-skel">
            <div v-for="n in 4" :key="n" class="sd-skel__card"></div>
        </div>

        <template v-else-if="data">
            <!-- Fee cards -->
            <section v-if="data.fees" class="sd-kpis" aria-label="Fee summary">
                <article class="sd-kpi">
                    <div class="sd-kpi__top">
                        <p class="sd-kpi__label">Total Fee</p>
                        <span class="sd-kpi__icon"><AppIcon name="wallet" class="h-5 w-5" /></span>
                    </div>
                    <p class="sd-kpi__value">{{ money(data.fees.total_payable) }}</p>
                    <p class="sd-kpi__sub">Whole session<span v-if="data.fees.concession > 0"> · after {{ money(data.fees.concession) }} concession</span></p>
                </article>
                <article class="sd-kpi is-emerald">
                    <div class="sd-kpi__top">
                        <p class="sd-kpi__label">Paid</p>
                        <span class="sd-kpi__icon"><AppIcon name="check-circle" class="h-5 w-5" /></span>
                    </div>
                    <p class="sd-kpi__value">{{ money(data.fees.paid) }}</p>
                    <div class="sd-kpi__bar" role="img" :aria-label="`${paidPercent}% paid`"><span :style="{ width: paidPercent + '%' }"></span></div>
                    <p class="sd-kpi__sub">{{ paidPercent }}% of total fee paid</p>
                </article>
                <article class="sd-kpi is-rose">
                    <div class="sd-kpi__top">
                        <p class="sd-kpi__label">Due Now</p>
                        <span class="sd-kpi__icon"><AppIcon name="alert" class="h-5 w-5" /></span>
                    </div>
                    <p class="sd-kpi__value">{{ money(data.fees.due_now) }}</p>
                    <p class="sd-kpi__sub">
                        <template v-if="data.fees.due_now > 0">Pending: {{ dueMonthsLabel }}</template>
                        <template v-else>Nothing due till this month 🎉</template>
                    </p>
                </article>
                <article class="sd-kpi is-amber">
                    <div class="sd-kpi__top">
                        <p class="sd-kpi__label">Remaining (Session)</p>
                        <span class="sd-kpi__icon"><AppIcon name="clock" class="h-5 w-5" /></span>
                    </div>
                    <p class="sd-kpi__value">{{ money(data.fees.balance) }}</p>
                    <p class="sd-kpi__sub">Still to pay, including upcoming months</p>
                </article>
            </section>

            <div class="sd-grid">
                <!-- Attendance -->
                <section v-if="isModuleVisible('attendance')" class="sd-card">
                    <header class="sd-card__head">
                        <h2><AppIcon name="calendar-check" class="h-4 w-4" /> Attendance</h2>
                        <RouterLink to="/attendance" class="sd-link">View details →</RouterLink>
                    </header>
                    <div v-if="data.attendance && data.attendance.working_days > 0" class="sd-att">
                        <div class="sd-ring" :style="{ '--p': data.attendance_percentage || 0 }">
                            <span>{{ data.attendance_percentage }}%</span>
                        </div>
                        <dl class="sd-att__stats">
                            <div><dt>Working days</dt><dd>{{ data.attendance.working_days }}</dd></div>
                            <div><dt>Present</dt><dd class="is-ok">{{ data.attendance.days_present }}</dd></div>
                            <div><dt>Absent</dt><dd class="is-bad">{{ data.attendance.days_absent }}</dd></div>
                        </dl>
                    </div>
                    <p v-else class="sd-empty">No attendance recorded this session yet.</p>
                    <div v-if="data.attendance?.months?.length" class="sd-bars">
                        <div v-for="m in data.attendance.months" :key="m.label" class="sd-bars__col" :title="`${m.label}: ${m.days_present}/${m.working_days} days`">
                            <div class="sd-bars__track"><span :style="{ height: Math.min(100, m.percentage) + '%' }"></span></div>
                            <small>{{ m.label }}</small>
                        </div>
                    </div>
                </section>

                <!-- Latest result -->
                <section v-if="isModuleVisible('marks')" class="sd-card">
                    <header class="sd-card__head">
                        <h2><AppIcon name="graduation" class="h-4 w-4" /> Latest Result</h2>
                        <RouterLink to="/examination?tab=marks" class="sd-link">All results →</RouterLink>
                    </header>
                    <div v-if="data.latest_result" class="sd-result">
                        <p class="sd-result__exam">{{ data.latest_result.exam_name }}</p>
                        <div class="sd-result__row">
                            <span class="sd-result__pct">{{ data.latest_result.percentage }}%</span>
                            <span class="sd-pill" :class="data.latest_result.result === 'Pass' ? 'is-ok' : 'is-bad'">{{ data.latest_result.result }}</span>
                        </div>
                        <dl class="sd-att__stats">
                            <div><dt>Marks</dt><dd>{{ num(data.latest_result.obtained) }} / {{ num(data.latest_result.max_total) }}</dd></div>
                            <div><dt>Grade</dt><dd>{{ data.latest_result.grade || '—' }}</dd></div>
                            <div v-if="data.latest_result.rank"><dt>Class rank</dt><dd>#{{ data.latest_result.rank }}</dd></div>
                        </dl>
                    </div>
                    <p v-else class="sd-empty">No published results yet.</p>
                    <p v-if="isModuleVisible('exam_schedule')" class="sd-next">
                        <AppIcon name="calendar" class="h-4 w-4" />
                        <span>Next exam: <b>{{ data.next_exam ? `${data.next_exam.subject} — ${formatDate(data.next_exam.date)}` : 'None scheduled' }}</b></span>
                    </p>
                </section>

                <!-- Functions / events -->
                <section v-if="isModuleVisible('events_calendar')" class="sd-card">
                    <header class="sd-card__head">
                        <h2><AppIcon name="presentation" class="h-4 w-4" /> Upcoming Functions</h2>
                        <RouterLink to="/events-calendar" class="sd-link">Calendar →</RouterLink>
                    </header>
                    <ul v-if="data.upcoming_events?.length" class="sd-events">
                        <li v-for="e in data.upcoming_events" :key="e.id">
                            <div class="sd-date"><b>{{ dayOf(e.event_date) }}</b><small>{{ monthOf(e.event_date) }}</small></div>
                            <div class="min-w-0">
                                <p class="sd-events__title">{{ e.title }}</p>
                                <p class="sd-events__meta">
                                    <span v-if="e.start_time">{{ e.start_time }}<span v-if="e.end_time"> – {{ e.end_time }}</span></span>
                                    <span v-if="e.venue"> · {{ e.venue }}</span>
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="sd-empty">No upcoming functions.</p>
                    <p v-if="isModuleVisible('notices') && data.recent_notices" class="sd-next">
                        <AppIcon name="message" class="h-4 w-4" />
                        <span><b>{{ data.recent_notices }}</b> new notice{{ data.recent_notices === 1 ? '' : 's' }} in the last 2 weeks</span>
                    </p>
                </section>
            </div>

            <!-- Fee history -->
            <section v-if="isModuleVisible('fee_history') || isModuleVisible('paid_fees')" class="sd-card">
                <header class="sd-card__head">
                    <h2><AppIcon name="history" class="h-4 w-4" /> Fee History</h2>
                    <RouterLink to="/fees?tab=paid" class="sd-link">View all →</RouterLink>
                </header>
                <div class="overflow-x-auto">
                    <table class="sd-table">
                        <thead>
                            <tr><th>Receipt No.</th><th>Date</th><th>Fee Heads</th><th>Mode</th><th class="text-right">Amount</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in data.recent_payments" :key="p.id">
                                <td class="font-semibold">{{ p.receipt_no }}</td>
                                <td>{{ formatDate(p.payment_date, true) }}</td>
                                <td class="sd-muted">{{ p.fee_heads || '—' }}</td>
                                <td><span class="sd-pill">{{ p.payment_mode || '—' }}</span></td>
                                <td class="text-right font-semibold">{{ money(p.amount) }}</td>
                            </tr>
                            <tr v-if="!data.recent_payments?.length"><td colspan="5" class="sd-empty">No payments recorded yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Quick links -->
            <div class="sd-links">
                <RouterLink v-for="link in quickLinks" :key="link.path" :to="link.path" class="sd-links__item">
                    <span class="sd-links__icon">{{ link.icon }}</span>
                    <span>{{ link.label }}</span>
                </RouterLink>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import client from '../api/client';
import { isModuleVisible, studentStore } from '../store';
import AppIcon from '../../erp/components/common/AppIcon.vue';

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

const initials = computed(() => (data.value?.student?.name || studentStore.user.name || 'S')
    .split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join(''));

const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
});

const paidPercent = computed(() => {
    const f = data.value?.fees;
    if (!f || !(f.total_payable > 0)) return 0;
    return Math.min(100, Math.round((f.paid / f.total_payable) * 100));
});

const dueMonthsLabel = computed(() => {
    const months = data.value?.fees?.due_months || [];
    return months.length > 3 ? `${months.slice(0, 3).join(', ')} +${months.length - 3} more` : months.join(', ');
});

function money(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}
function num(n) {
    return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}
function formatDate(d, withYear = false) {
    if (!d) return '';
    return new Date(`${d}T00:00:00`).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', ...(withYear ? { year: 'numeric' } : {}) });
}
function dayOf(d) {
    return d ? String(new Date(`${d}T00:00:00`).getDate()).padStart(2, '0') : '';
}
function monthOf(d) {
    return d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-IN', { month: 'short' }) : '';
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
