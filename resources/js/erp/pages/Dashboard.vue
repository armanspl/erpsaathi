<template>
    <div class="dash">
        <header class="dash-hero">
            <div class="dash-hero__copy">
                <p class="dash-kicker">Overview</p>
                <h1 class="dash-title">Welcome{{ firstName ? `, ${firstName}` : '' }}</h1>
                <p class="dash-sub">
                    {{ today }}
                    <span v-if="erpStore.currentSession"> · Session {{ erpStore.currentSession }}</span>
                    <span v-if="erpStore.currentBranch"> · {{ erpStore.currentBranch }}</span>
                </p>
            </div>
            <div class="dash-actions">
                <RouterLink to="/fee-management/fee-receipt" class="dash-btn dash-btn--primary">
                    <AppIcon name="wallet" class="h-4 w-4" /> Collect fee
                </RouterLink>
                <RouterLink to="/people/students?add=1" class="dash-btn">
                    <AppIcon name="user-plus" class="h-4 w-4" /> Add student
                </RouterLink>
                <RouterLink :to="{ path: '/import-export', query: { type: 'global-workbook-export' } }" class="dash-btn">
                    <AppIcon name="download" class="h-4 w-4" /> Export workbook
                </RouterLink>
            </div>
        </header>

        <div v-if="error" class="dash-alert" role="alert">
            {{ error }}
            <button type="button" class="dash-alert__retry" @click="load">Retry</button>
        </div>

        <div v-if="loading" class="dash-skel" aria-busy="true" aria-label="Loading dashboard">
            <div v-for="n in 8" :key="n" class="dash-skel__card" />
        </div>

        <template v-else-if="!error">
            <!-- Summary cards -->
            <section class="dash-kpis" aria-label="Summary">
                <article v-for="k in kpis" :key="k.label" class="dash-kpi" :class="`is-${k.tone}`">
                    <div class="dash-kpi__top">
                        <p class="dash-kpi__label">{{ k.label }}</p>
                        <span class="dash-kpi__icon" aria-hidden="true"><AppIcon :name="k.icon" class="h-5 w-5" /></span>
                    </div>
                    <p class="dash-kpi__value">{{ k.value }}</p>
                    <div v-if="k.progress !== undefined" class="dash-kpi__bar" role="img" :aria-label="`${k.progress}% collected`">
                        <span :style="{ width: `${k.progress}%` }" />
                    </div>
                    <p class="dash-kpi__sub">{{ k.sub }}</p>
                </article>
            </section>

            <!-- Secondary figures -->
            <section class="dash-strip" aria-label="More figures">
                <div v-for="s in strip" :key="s.label" class="dash-strip__item">
                    <span class="dash-strip__icon" aria-hidden="true"><AppIcon :name="s.icon" class="h-4 w-4" /></span>
                    <span class="min">
                        <span class="dash-strip__label">{{ s.label }}</span>
                        <span class="dash-strip__value">{{ s.value }}</span>
                    </span>
                </div>
            </section>

            <div class="dash-grid dash-grid--wide">
                <DashCard title="Fee collection overview" icon="chart" to="/fee-management/fee-collection-report">
                    <div v-if="!feeCollectionStatus.length" class="dash-empty center">No fee data for the current session.</div>
                    <template v-else>
                        <div class="dash-fee__summary">
                            <div class="dash-fee__total">
                                <span class="leg is-collected">Collected</span>
                                <strong>₹{{ money(feeTotals.collected) }}</strong>
                            </div>
                            <div class="dash-fee__total">
                                <span class="leg is-due">Due</span>
                                <strong>₹{{ money(feeTotals.due) }}</strong>
                            </div>
                            <div class="dash-fee__total">
                                <span class="leg is-rate">Collection rate</span>
                                <strong>{{ feeTotals.rate }}%</strong>
                            </div>
                        </div>
                        <div class="dash-chart" role="img" :aria-label="`Fee collected and due by class: ₹${money(feeTotals.collected)} collected, ₹${money(feeTotals.due)} due`">
                            <div class="dash-chart__plot">
                                <div
                                    v-for="c in feeCollectionStatus"
                                    :key="c.class"
                                    class="dash-chart__col"
                                    :title="`${c.class}: ₹${money(c.collected_amount)} collected · ₹${money(c.due_amount)} due`"
                                >
                                    <span class="dash-chart__amt">₹{{ shortMoney(Number(c.collected_amount || 0) + Number(c.due_amount || 0)) }}</span>
                                    <div class="dash-chart__track">
                                        <div class="dash-chart__bar" :style="{ height: colHeight(c) }">
                                            <span class="seg is-due" :style="{ flexGrow: Number(c.due_amount || 0) }" />
                                            <span class="seg is-collected" :style="{ flexGrow: Number(c.collected_amount || 0) }" />
                                        </div>
                                    </div>
                                    <span class="dash-chart__label">{{ c.class }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </DashCard>

                <DashCard title="Quick actions" icon="activity">
                    <div class="dash-quick">
                        <RouterLink v-for="action in quickActions" :key="action.label" :to="action.to" class="dash-quick__item">
                            <span class="dash-quick__icon" aria-hidden="true"><AppIcon :name="action.icon" class="h-5 w-5" /></span>
                            {{ action.label }}
                        </RouterLink>
                    </div>
                </DashCard>
            </div>

            <div class="dash-grid dash-grid--3">
                <DashCard title="Today" icon="calendar-check">
                    <div class="dash-rows">
                        <div class="dash-row">
                            <span>Student attendance</span>
                            <span class="dash-row__vals">
                                <strong class="is-ok">{{ todaySummary.students.present }}</strong>
                                <span class="sep">/</span>
                                <strong class="is-bad">{{ todaySummary.students.absent }}</strong>
                                <span class="hint">P / A</span>
                            </span>
                        </div>
                        <div class="dash-row">
                            <span>Staff attendance</span>
                            <span class="dash-row__vals">
                                <strong class="is-ok">{{ todaySummary.staff.present }}</strong>
                                <span class="sep">/</span>
                                <strong class="is-bad">{{ todaySummary.staff.absent }}</strong>
                                <span class="hint">P / A</span>
                            </span>
                        </div>
                        <div class="dash-row">
                            <span>Fee collected</span>
                            <span class="dash-row__vals">
                                <strong class="is-cream">₹{{ money(todaySummary.fee.received) }}</strong>
                                <span class="hint">({{ todaySummary.fee.receipts }})</span>
                            </span>
                        </div>
                        <div class="dash-row dash-row--split">
                            <span class="mode is-cash">
                                <i>Cash</i>
                                <strong>₹{{ money(todaySummary.fee.cash) }}</strong>
                            </span>
                            <span class="mode is-bank">
                                <i>Bank / UPI</i>
                                <strong>₹{{ money(todaySummary.fee.bank) }}</strong>
                            </span>
                            <span class="mode is-total">
                                <i>Total</i>
                                <strong>₹{{ money(todayCashBankTotal) }}</strong>
                            </span>
                        </div>
                    </div>
                </DashCard>

                <DashCard title="Pending tasks" icon="alert">
                    <ul class="dash-list">
                        <li v-for="t in pendingTasks" :key="t.label">
                            <RouterLink :to="t.to" class="dash-task">
                                <span>{{ t.label }}</span>
                                <span class="dash-badge" :class="t.count > 0 ? 'is-warn' : ''">{{ t.count }}</span>
                            </RouterLink>
                        </li>
                        <li v-if="!pendingTasks.length" class="dash-empty">Nothing pending.</li>
                    </ul>
                </DashCard>

                <DashCard title="Upcoming events" icon="calendar" to="/academics/academic-calendar">
                    <div v-if="!upcomingEvents.length" class="dash-empty">No upcoming events.</div>
                    <ul v-else class="dash-events">
                        <li v-for="e in upcomingEvents" :key="e.title + e.date">
                            <span class="dash-events__date" aria-hidden="true">
                                <AppIcon name="calendar" class="h-4 w-4" />
                            </span>
                            <span class="min">
                                <span class="name">{{ e.title }}</span>
                                <span class="hint">{{ e.date }}</span>
                            </span>
                        </li>
                    </ul>
                </DashCard>
            </div>

            <div class="dash-grid dash-grid--wide">
                <DashCard title="Recent students" icon="graduation" to="/people/students">
                    <div v-if="!recentAdmissions.length" class="dash-empty center">No admissions yet.</div>
                    <div v-else class="dash-scroll">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Adm. no.</th>
                                    <th>Class</th>
                                    <th class="num">Admitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in recentAdmissions" :key="r.adm">
                                    <td>
                                        <span class="dash-person">
                                            <span class="dash-avatar" aria-hidden="true">{{ initials(r.name) }}</span>
                                            <span class="name">{{ r.name }}</span>
                                        </span>
                                    </td>
                                    <td class="mono">{{ r.adm }}</td>
                                    <td>{{ r.class }}</td>
                                    <td class="num">{{ r.date }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashCard>

                <DashCard title="Recent activity" icon="history">
                    <div v-if="!recentActivities.length" class="dash-empty">No recent activity.</div>
                    <ul v-else class="dash-activity">
                        <li v-for="(a, i) in recentActivities" :key="i">{{ a }}</li>
                    </ul>
                </DashCard>
            </div>

            <div class="dash-grid dash-grid--2">
                <DashCard title="Class-wise strength" icon="users" to="/people/students">
                    <div v-if="!classStrength.length" class="dash-empty center">No active students yet.</div>
                    <div v-else class="dash-scroll">
                        <table class="dash-table dash-table--tight">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th class="num">Boys</th>
                                    <th class="num">Girls</th>
                                    <th class="num">Total</th>
                                    <th class="num is-new">New B</th>
                                    <th class="num is-new">New G</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in classStrength" :key="c.class">
                                    <td class="name">{{ c.class }}</td>
                                    <td class="num is-boy">{{ c.boys }}</td>
                                    <td class="num is-girl">{{ c.girls }}</td>
                                    <td class="num total">{{ c.boys + c.girls }}</td>
                                    <td class="num is-new">{{ c.new_boys || 0 }}</td>
                                    <td class="num is-new">{{ c.new_girls || 0 }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="dash-table__summary">
                                    <td class="name">Total</td>
                                    <td class="num is-boy">{{ classStrengthTotals.boys }}</td>
                                    <td class="num is-girl">{{ classStrengthTotals.girls }}</td>
                                    <td class="num total">{{ classStrengthTotals.total }}</td>
                                    <td class="num is-new">{{ classStrengthTotals.newBoys }}</td>
                                    <td class="num is-new">{{ classStrengthTotals.newGirls }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </DashCard>

                <DashCard title="Category-wise (boys / girls)" icon="layers" to="/people/students">
                    <div v-if="!categoryByClass.length" class="dash-empty center">No category data yet.</div>
                    <template v-else>
                        <div class="dash-cat__summary">
                            <span class="dash-cat__summary-item">
                                <em>Total</em>
                                <strong class="is-boy">{{ categorySummary.boys }}B</strong>
                                <span class="sep">/</span>
                                <strong class="is-girl">{{ categorySummary.girls }}G</strong>
                            </span>
                            <span v-if="categorySummary.rteBoys + categorySummary.rteGirls > 0" class="dash-cat__summary-item is-rte">
                                <em>RTE</em>
                                <strong class="is-boy">{{ categorySummary.rteBoys }}B</strong>
                                <span class="sep">/</span>
                                <strong class="is-girl">{{ categorySummary.rteGirls }}G</strong>
                            </span>
                        </div>
                        <div class="dash-cat">
                            <div v-for="row in categoryByClass" :key="row.class" class="dash-cat__class">
                                <div class="dash-cat__title">
                                    <span class="name">{{ row.class }}</span>
                                    <span class="hint">{{ row.boys }}B · {{ row.girls }}G</span>
                                </div>
                                <div class="dash-cat__chips">
                                    <span
                                        v-for="cat in row.categories"
                                        :key="cat.category"
                                        class="dash-cat__chip"
                                        :class="{ 'is-rte': cat.category === 'RTE' }"
                                        :style="{ '--cat-hue': categoryHue(cat.category) }"
                                    >
                                        <em>{{ cat.category }}</em>
                                        <strong class="is-boy">{{ cat.boys }}</strong>
                                        <span class="sep">/</span>
                                        <strong class="is-girl">{{ cat.girls }}</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </DashCard>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '../components/common/AppIcon.vue';
import DashCard from '../components/dashboard/DashCard.vue';
import { erpStore, loadSessions } from '../store';
import client from '../api/client';

const today = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
const firstName = computed(() => (erpStore.user?.name || '').split(' ')[0] || '');

const loading = ref(true);
const error = ref('');
const people = ref({ students: 0, teachers: 0, staff: 0, drivers: 0 });
const moneyData = ref({ fee_collected: 0, fee_pending: 0, bank_balance: 0, expense: 0 });
const todaySummary = ref({
    students: { present: 0, absent: 0 },
    staff: { present: 0, absent: 0 },
    fee: { received: 0, cash: 0, bank: 0, receipts: 0 },
});
const todayCashBankTotal = computed(() => Number(todaySummary.value.fee.cash || 0) + Number(todaySummary.value.fee.bank || 0));
const classStrength = ref([]);
const classStrengthTotals = computed(() => classStrength.value.reduce((acc, c) => {
    acc.boys += Number(c.boys || 0);
    acc.girls += Number(c.girls || 0);
    acc.newBoys += Number(c.new_boys || 0);
    acc.newGirls += Number(c.new_girls || 0);
    acc.total = acc.boys + acc.girls;
    return acc;
}, { boys: 0, girls: 0, total: 0, newBoys: 0, newGirls: 0 }));
const feeCollectionStatus = ref([]);
const categoryByClass = ref([]);
const categorySummary = computed(() => categoryByClass.value.reduce((acc, row) => {
    acc.boys += Number(row.boys || 0);
    acc.girls += Number(row.girls || 0);
    acc.rteBoys += Number(row.rte_boys || 0);
    acc.rteGirls += Number(row.rte_girls || 0);
    return acc;
}, { boys: 0, girls: 0, rteBoys: 0, rteGirls: 0 }));
const recentAdmissions = ref([]);
const pendingTasks = ref([]);
const upcomingEvents = ref([]);
const recentActivities = ref([]);
/** Session key used for the in-flight / last successful dashboard fetch — skips duplicate loads. */
let loadedForSession = null;
let loadSeq = 0;
/** Ignore session watcher until the first mount load finishes (avoids double fetch during hydration). */
let sessionWatchReady = false;

// Summary cards — all figures come straight from GET /dashboard.
const collectionRate = computed(() => {
    const collected = Number(moneyData.value.fee_collected || 0);
    const billed = collected + Number(moneyData.value.fee_pending || 0);
    return billed > 0 ? Math.round((collected / billed) * 100) : 0;
});
const kpis = computed(() => {
    const p = people.value;
    const staffTotal = Number(p.teachers || 0) + Number(p.staff || 0) + Number(p.drivers || 0);
    return [
        { label: 'Total students', value: money(p.students), sub: 'Enrolled students', icon: 'graduation', tone: 'green' },
        { label: 'Total staff', value: money(staffTotal), sub: `${plural(p.teachers, 'teacher')} · ${p.staff} staff · ${plural(p.drivers, 'driver')}`, icon: 'users', tone: 'teal' },
        { label: 'Fees collected', value: `₹${money(moneyData.value.fee_collected)}`, sub: `${collectionRate.value}% of billed fees`, icon: 'wallet', tone: 'emerald', progress: collectionRate.value },
        { label: 'Pending fees', value: `₹${money(moneyData.value.fee_pending)}`, sub: 'Outstanding dues', icon: 'clock', tone: 'amber' },
    ];
});
const strip = computed(() => [
    { label: 'Bank balance', value: `₹${money(moneyData.value.bank_balance)}`, icon: 'bank' },
    { label: 'Expense', value: `₹${money(moneyData.value.expense)}`, icon: 'receipt' },
    { label: 'Collected today', value: `₹${money(todaySummary.value.fee.received)} · ${todaySummary.value.fee.receipts} receipts`, icon: 'trend' },
    { label: 'Attendance today', value: `${todaySummary.value.students.present} present · ${todaySummary.value.students.absent} absent`, icon: 'calendar-check' },
]);

const feeTotals = computed(() => {
    const collected = feeCollectionStatus.value.reduce((s, c) => s + Number(c.collected_amount || 0), 0);
    const due = feeCollectionStatus.value.reduce((s, c) => s + Number(c.due_amount || 0), 0);
    const billed = collected + due;
    return { collected, due, rate: billed > 0 ? Math.round((collected / billed) * 100) : 0 };
});
const feeMax = computed(() => Math.max(1, ...feeCollectionStatus.value.map((c) => Number(c.collected_amount || 0) + Number(c.due_amount || 0))));
function colHeight(c) {
    const total = Number(c.collected_amount || 0) + Number(c.due_amount || 0);
    return `${total > 0 ? Math.max(4, (total / feeMax.value) * 100) : 0}%`;
}

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function shortMoney(n) {
    const v = Number(n || 0);
    if (v >= 10000000) return `${(v / 10000000).toFixed(1)}Cr`;
    if (v >= 100000) return `${(v / 100000).toFixed(1)}L`;
    if (v >= 1000) return `${(v / 1000).toFixed(v >= 10000 ? 0 : 1)}k`;
    return v.toLocaleString('en-IN');
}

function plural(n, word) {
    return `${Number(n || 0)} ${word}${Number(n) === 1 ? '' : 's'}`;
}

function initials(name) {
    return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
}

function categoryHue(name) {
    let hash = 0;
    const s = String(name || '');
    for (let i = 0; i < s.length; i += 1) hash = (hash * 31 + s.charCodeAt(i)) % 360;
    return hash;
}

async function ensureSessionsReady() {
    if (erpStore.sessionRecords.length) return;
    try {
        await loadSessions();
    } catch {
        // Header may still load sessions; proceed with localStorage session header.
    }
}

async function load() {
    const sessionKey = erpStore.currentSession || localStorage.getItem('erp_current_session') || '';
    if (loadedForSession === sessionKey && !loading.value && !error.value) {
        return;
    }

    const seq = ++loadSeq;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await client.get('/dashboard');
        if (seq !== loadSeq) return;
        loadedForSession = sessionKey;
        people.value = { students: 0, teachers: 0, staff: 0, drivers: 0, ...(data.people_stats || {}) };
        moneyData.value = { fee_collected: 0, fee_pending: 0, bank_balance: 0, expense: 0, ...(data.money_stats || {}) };
        todaySummary.value = {
            students: data.today_summary?.students || { present: 0, absent: 0 },
            staff: data.today_summary?.staff || { present: 0, absent: 0 },
            fee: {
                received: data.today_summary?.fee?.received || 0,
                cash: data.today_summary?.fee?.cash || 0,
                bank: data.today_summary?.fee?.bank || 0,
                receipts: data.today_summary?.fee?.receipts || 0,
            },
        };
        classStrength.value = data.class_strength || [];
        feeCollectionStatus.value = data.fee_collection_status || [];
        categoryByClass.value = data.category_by_class || [];
        recentAdmissions.value = data.recent_admissions || [];
        pendingTasks.value = data.pending_tasks || [];
        upcomingEvents.value = data.upcoming_events || [];
        recentActivities.value = data.recent_activities || [];
    } catch (e) {
        if (seq !== loadSeq) return;
        error.value = e.response?.data?.message || 'Could not load dashboard.';
    } finally {
        if (seq === loadSeq) loading.value = false;
    }
}

onMounted(async () => {
    await ensureSessionsReady();
    await load();
    sessionWatchReady = true;
});

watch(
    () => erpStore.currentSession,
    (next, prev) => {
        if (!sessionWatchReady) return;
        if (next === prev) return;
        if (loadedForSession === next) return;
        load();
    },
);

const quickActions = [
    { label: 'Add student', to: '/people/students?add=1', icon: 'user-plus' },
    { label: 'Collect fee', to: '/fee-management/fee-receipt', icon: 'wallet' },
    { label: 'Fee due', to: '/fee-management/fee-due', icon: 'clock' },
    { label: 'Fee receipt', to: '/fee-management/fee-receipt', icon: 'receipt' },
    { label: 'Import Excel', to: '/import-export', icon: 'upload' },
    { label: 'Admit cards', to: '/exam-management/admit-cards', icon: 'id-card' },
];
</script>

<style scoped>
.dash {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-family: var(--erp-font, 'Manrope', ui-sans-serif, system-ui, sans-serif);
    color: var(--erp-cream, #f3efe6);
}
.min { min-width: 0; }

/* ── Hero ── */
.dash-hero {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0 0.25rem;
}
@media (min-width: 640px) {
    .dash-hero {
        flex-direction: row;
        align-items: flex-end;
        justify-content: space-between;
    }
}
.dash-kicker {
    margin: 0 0 0.2rem;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--erp-gold, #c6a75e);
}
.dash-title {
    margin: 0;
    font-family: var(--erp-font-display, 'Cormorant Garamond', Georgia, serif);
    font-size: clamp(1.7rem, 3vw, 2.15rem);
    font-weight: 650;
    letter-spacing: -0.015em;
    line-height: 1.15;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.dash-sub {
    margin: 0.35rem 0 0;
    font-size: 0.875rem;
    color: var(--erp-muted, #9a958c);
}
.dash-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.dash-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.95rem;
    border-radius: 10px;
    border: 1px solid var(--erp-btn2-border, var(--erp-border, rgba(198, 167, 94, 0.28)));
    background: var(--erp-btn2-bg, transparent);
    color: var(--erp-btn2-text, var(--erp-cream, #f3efe6));
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    box-shadow: var(--erp-shadow-xs, none);
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.dash-btn:hover {
    border-color: var(--erp-gold, #c6a75e);
    background: var(--erp-btn2-hover-bg, transparent);
    color: var(--erp-gold-soft, #e2c98a);
    transform: translateY(-1px);
}
.dash-btn--primary {
    background: linear-gradient(180deg, var(--erp-brand-from, #c6a75e), var(--erp-brand-to, #a8883f));
    border-color: transparent;
    color: var(--erp-brand-ink, #14120e);
    box-shadow: var(--sg-shadow-brand, none);
}
.dash-btn--primary:hover {
    color: var(--erp-brand-ink-hover, #0b0a08);
    background: var(--erp-btn1-hover-bg, linear-gradient(135deg, var(--erp-gold, #c6a75e), var(--erp-brand-to, #a8883f)));
    filter: var(--erp-btn1-hover-filter, brightness(1.05));
}

.dash-alert {
    border: 1px solid var(--erp-alert-border, rgba(248, 180, 180, 0.35));
    background: var(--erp-alert-bg, rgba(180, 60, 60, 0.18));
    color: var(--erp-alert-text, #f6c1c1);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    font-size: 0.875rem;
}
.dash-alert__retry {
    margin-left: 0.5rem;
    font-weight: 700;
    text-decoration: underline;
    background: none;
    border: 0;
    color: inherit;
    cursor: pointer;
}

.dash-skel {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
}
@media (min-width: 1024px) {
    .dash-skel { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.dash-skel__card {
    height: 7rem;
    border-radius: var(--erp-radius-card, 14px);
    background: linear-gradient(90deg, var(--erp-skeleton, rgba(255,255,255,0.04)), var(--erp-skeleton-shine, rgba(255,255,255,0.08)), var(--erp-skeleton, rgba(255,255,255,0.04)));
    background-size: 200% 100%;
    animation: dash-pulse 1.4s ease-in-out infinite;
}
@keyframes dash-pulse {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

/* ── Summary cards ── */
.dash-kpis {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.9rem;
}
@media (min-width: 560px) {
    .dash-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1200px) {
    .dash-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.dash-kpi {
    --kpi: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
    --kpi-bg: var(--erp-stat-icon-bg, rgba(198, 167, 94, 0.12));
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 1.05rem 1.15rem 1rem;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    border-radius: var(--erp-radius-card, 14px);
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    box-shadow: var(--erp-shadow, 0 10px 28px rgba(0, 0, 0, 0.18));
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
/* Restrained accent: a thin top rule in the card's tone. */
.dash-kpi::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--kpi);
    opacity: 0.85;
}
.dash-kpi:hover {
    transform: translateY(-2px);
    box-shadow: var(--erp-shadow-raised, var(--erp-shadow, 0 10px 28px rgba(0, 0, 0, 0.18)));
}
/* Card tones come from the template (--erp-kpi-*); the fallbacks are School Green's. */
.dash-kpi.is-teal { --kpi: var(--erp-kpi-2, #0D9488); --kpi-bg: var(--erp-kpi-2-bg, rgb(13 148 136 / 0.12)); }
.dash-kpi.is-emerald { --kpi: var(--erp-kpi-3, #059669); --kpi-bg: var(--erp-kpi-3-bg, rgb(5 150 105 / 0.12)); }
.dash-kpi.is-amber { --kpi: var(--erp-kpi-4, #D97706); --kpi-bg: var(--erp-kpi-4-bg, rgb(217 119 6 / 0.12)); }
:global(.dark) .dash-kpi.is-teal { --kpi: var(--erp-kpi-2, #2DD4BF); --kpi-bg: var(--erp-kpi-2-bg, rgb(45 212 191 / 0.12)); }
:global(.dark) .dash-kpi.is-emerald { --kpi: var(--erp-kpi-3, #34D399); --kpi-bg: var(--erp-kpi-3-bg, rgb(52 211 153 / 0.12)); }
:global(.dark) .dash-kpi.is-amber { --kpi: var(--erp-kpi-4, #FBBF24); --kpi-bg: var(--erp-kpi-4-bg, rgb(251 191 36 / 0.12)); }
.dash-kpi__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}
.dash-kpi__label {
    margin: 0.2rem 0 0;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
.dash-kpi__icon {
    display: inline-flex;
    flex-shrink: 0;
    width: 2.6rem;
    height: 2.6rem;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: var(--kpi-bg);
    color: var(--kpi);
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.35), 0 4px 10px -6px var(--kpi);
}
.dash-kpi__value {
    margin: 0;
    font-family: var(--erp-font-display, 'Cormorant Garamond', Georgia, serif);
    font-size: clamp(1.55rem, 2.4vw, 1.9rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.dash-kpi__bar {
    height: 6px;
    margin-top: 0.2rem;
    overflow: hidden;
    border-radius: 999px;
    background: var(--erp-chart-track, rgba(255, 255, 255, 0.08));
    box-shadow: var(--erp-chart-track-shadow, none);
}
.dash-kpi__bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--kpi);
    transition: width 0.4s ease;
}
.dash-kpi__sub {
    margin: 0;
    font-size: 0.78rem;
    color: var(--erp-muted, #9a958c);
}

/* ── Secondary strip ── */
.dash-strip {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.6rem;
}
@media (min-width: 560px) {
    .dash-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1200px) {
    .dash-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.dash-strip__item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    border-radius: 12px;
    background: var(--erp-tint, rgba(255, 255, 255, 0.03));
}
.dash-strip__icon {
    display: inline-flex;
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    color: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
    box-shadow: var(--erp-shadow-xs, 0 1px 2px rgba(0, 0, 0, 0.15));
}
.dash-strip__label {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
.dash-strip__value {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.92rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}

/* ── Grids ── */
.dash-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: 1fr;
}
@media (min-width: 1024px) {
    .dash-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dash-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .dash-grid--wide { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
}

/* ── Fee collection chart ── */
.dash-fee__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem 1.5rem;
    margin-bottom: 1rem;
}
.dash-fee__total {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.dash-fee__total strong {
    font-size: 1.15rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.leg {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
.leg::before {
    content: '';
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 3px;
}
.leg.is-collected::before { background: var(--erp-chart-1-solid, #34d399); }
.leg.is-due::before { background: var(--erp-chart-2-solid, #f43f5e); }
.leg.is-rate::before { background: var(--erp-border, rgba(255, 255, 255, 0.2)); }
.dash-chart {
    overflow-x: auto;
    padding-bottom: 0.25rem;
}
.dash-chart__plot {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    min-width: 100%;
    height: 15rem;
    padding: 0.5rem 0.25rem 0;
    position: relative;
}
/* Full-width gridlines every 25%, spanning the bar area (between the amount and class labels). */
.dash-chart__plot::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 1.8rem;
    bottom: 1.85rem;
    background-image: linear-gradient(to bottom, var(--erp-line, rgba(255, 255, 255, 0.06)) 1px, transparent 1px);
    background-size: 100% 25%;
    border-bottom: 1px solid var(--erp-border, rgba(255, 255, 255, 0.1));
    pointer-events: none;
}
/* Bar area: bar heights are a % of this. */
.dash-chart__track {
    position: relative;
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    min-height: 0;
    align-items: flex-end;
    justify-content: center;
}
.dash-chart__col {
    display: flex;
    flex: 1 0 2.6rem;
    max-width: 4.5rem;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.3rem;
    height: 100%;
}
.dash-chart__amt {
    font-size: 0.68rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--erp-muted, #9a958c);
}
.dash-chart__bar {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 2.6rem;
    min-height: 0;
    overflow: hidden;
    border-radius: 8px 8px 4px 4px;
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.25), 0 6px 14px -8px rgb(0 0 0 / 0.35);
    transition: height 0.4s ease;
}
.dash-chart__bar .seg {
    display: block;
    flex-basis: 0;
    min-height: 0;
}
.dash-chart__bar .seg.is-collected { background: var(--erp-chart-1, linear-gradient(180deg, #34d399, #15803d)); }
.dash-chart__bar .seg.is-due { background: var(--erp-chart-2, linear-gradient(180deg, #fb7185, #be123c)); }
.dash-chart__label {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-top: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}

/* ── Quick actions ── */
.dash-quick {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
}
.dash-quick__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.9rem 0.5rem;
    border-radius: 12px;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface-quiet, rgba(255, 255, 255, 0.02));
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    box-shadow: var(--erp-shadow-xs, none);
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.dash-quick__icon {
    display: inline-flex;
    width: 2.4rem;
    height: 2.4rem;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: var(--erp-stat-icon-bg, rgba(198, 167, 94, 0.12));
    color: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
}
.dash-quick__item:hover {
    border-color: var(--erp-hover-border, var(--erp-gold, #c6a75e));
    background: var(--erp-tint-strong, rgba(198, 167, 94, 0.1));
    color: var(--erp-gold-soft, #e2c98a);
    transform: translateY(-2px);
    box-shadow: var(--erp-shadow-raised, none);
}

/* ── Today ── */
.dash-rows {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.dash-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: 10px;
    background: var(--erp-tint, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--erp-line, rgba(255, 255, 255, 0.04));
    font-size: 0.85rem;
    color: var(--erp-muted, #9a958c);
}
.dash-row--split {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    padding: 0.5rem;
}
.dash-row--split .mode {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.45rem 0.55rem;
    border-radius: 8px;
    font-size: 0.72rem;
}
.dash-row--split .mode i {
    font-style: normal;
    opacity: 0.85;
}
.dash-row--split .mode strong {
    font-size: 0.92rem;
    font-weight: 700;
}
.dash-row--split .is-cash {
    background: var(--erp-ok-bg, rgba(125, 206, 160, 0.12));
    color: var(--erp-ok, #9fe0b8);
}
.dash-row--split .is-bank {
    background: var(--erp-info-bg, rgba(125, 180, 230, 0.12));
    color: var(--erp-info, #9ec8f0);
}
.dash-row--split .is-total {
    background: var(--erp-total-bg, rgba(243, 239, 230, 0.1));
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.dash-row__vals {
    display: inline-flex;
    align-items: baseline;
    gap: 0.25rem;
}
.is-ok { color: var(--erp-ok, #7dcea0); }
.is-bad { color: var(--erp-bad, #e8a0a0); }
.is-cream { color: var(--erp-cream, #f3efe6); }
.is-boy { color: var(--erp-boy, #7eb6e8); }
.is-girl { color: var(--erp-girl, #e8a0c8); }
.sep { color: var(--erp-sep, rgba(243, 239, 230, 0.35)); }
.hint {
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--erp-muted, #9a958c);
}

/* ── Pending tasks ── */
.dash-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.dash-task {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.55rem;
    border-radius: 9px;
    font-size: 0.86rem;
    color: var(--erp-cream, #f3efe6);
    text-decoration: none;
    transition: background 0.15s ease;
}
.dash-task:hover {
    background: var(--erp-hover, rgba(198, 167, 94, 0.08));
}
.dash-badge {
    min-width: 1.6rem;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    text-align: center;
    font-size: 0.72rem;
    font-weight: 700;
    background: var(--erp-badge-bg, rgba(255, 255, 255, 0.06));
    color: var(--erp-muted, #9a958c);
}
.dash-badge.is-warn {
    background: var(--erp-warn-bg, rgba(198, 167, 94, 0.18));
    color: var(--erp-warn-text, var(--erp-gold-soft, #e2c98a));
}

/* ── Upcoming events ── */
.dash-events {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.dash-events li {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.55rem 0.6rem;
    border-radius: 10px;
    border: 1px solid var(--erp-line, rgba(255, 255, 255, 0.05));
    background: var(--erp-tint, rgba(255, 255, 255, 0.03));
}
.dash-events__date {
    display: inline-flex;
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: var(--erp-stat-icon-bg, rgba(198, 167, 94, 0.12));
    color: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
}
.dash-events .name {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}
.dash-events .hint { display: block; }

/* ── Tables ── */
.dash-scroll {
    max-height: 19rem;
    overflow: auto;
    padding-right: 0.15rem;
}
.dash-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.dash-table--tight {
    table-layout: fixed;
    font-size: 0.8rem;
}
.dash-table--tight th:first-child,
.dash-table--tight td:first-child {
    width: 24%;
}
.dash-table--tight th.num,
.dash-table--tight td.num {
    width: 15.2%;
}
.dash-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--erp-tint, var(--erp-surface-solid, #121318));
    padding: 0.55rem 0.6rem;
    font-size: 0.66rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
    text-align: left;
}
.dash-table th:first-child { border-radius: 8px 0 0 8px; }
.dash-table th:last-child { border-radius: 0 8px 8px 0; }
.dash-table td {
    padding: 0.55rem 0.6rem;
    border-top: 1px solid var(--erp-line, rgba(255, 255, 255, 0.05));
    color: var(--erp-muted, #9a958c);
}
.dash-table tbody tr:first-child td { border-top: 0; }
.dash-table tbody tr {
    transition: background 0.12s ease;
}
.dash-table tbody tr:hover {
    background: var(--erp-hover, rgba(255, 255, 255, 0.04));
}
.dash-table .name,
.dash-feed .name,
.dash-cat__title .name {
    color: var(--erp-cream, #f3efe6);
    font-weight: 600;
}
.dash-table .mono { font-variant-numeric: tabular-nums; }
.dash-table .num { text-align: right; }
.dash-table .total { color: var(--erp-heading, var(--erp-gold-soft, #e2c98a)); font-weight: 700; }
.dash-table .is-new { color: var(--erp-new, #c6b4f0); }
.dash-table__summary {
    position: sticky;
    bottom: 0;
}
.dash-table__summary td {
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
    border-top: 1px solid var(--erp-border, rgba(198, 167, 94, 0.28));
    background: var(--erp-surface-solid, #121318);
    font-weight: 700;
}
.dash-table__summary .name { color: var(--erp-gold, #c6a75e); }
.dash-person {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}
.dash-avatar {
    display: inline-flex;
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--erp-stat-icon-bg, rgba(198, 167, 94, 0.12));
    color: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
    font-size: 0.7rem;
    font-weight: 700;
}

/* ── Category chips ── */
.dash-cat__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}
.dash-cat__summary-item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    background: var(--erp-tint, rgba(255, 255, 255, 0.045));
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.25));
}
.dash-cat__summary-item em {
    font-style: normal;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    font-size: 0.68rem;
    color: var(--erp-gold, #c6a75e);
    margin-right: 0.1rem;
}
.dash-cat__summary-item.is-rte {
    background: var(--erp-tint-strong, rgba(198, 167, 94, 0.14));
    border-color: var(--erp-gold, #c6a75e);
}
.dash-cat__summary-item.is-rte em { color: var(--erp-gold-soft, #e2c98a); }
.dash-cat {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.65rem;
    max-height: 16rem;
    overflow: auto;
}
@media (min-width: 768px) {
    .dash-cat { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.dash-cat__class {
    padding: 0.55rem 0.65rem;
    border-radius: 10px;
    border: 1px solid var(--erp-line, rgba(255, 255, 255, 0.05));
    background: var(--erp-tint, rgba(255, 255, 255, 0.025));
}
.dash-cat__title {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
    font-size: 0.82rem;
}
.dash-cat__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.dash-cat__chip {
    display: inline-flex;
    align-items: baseline;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    background: var(--erp-chip-bg, hsl(var(--cat-hue, 40) 35% 18% / 0.85));
    border: 1px solid var(--erp-chip-border, hsl(var(--cat-hue, 40) 45% 40% / 0.35));
    color: var(--erp-cream, #f3efe6);
}
.dash-cat__chip em {
    font-style: normal;
    font-weight: 600;
    margin-right: 0.15rem;
    color: var(--erp-chip-label, hsl(var(--cat-hue, 40) 70% 72%));
}
.dash-cat__chip strong { font-weight: 700; }
.dash-cat__chip.is-rte {
    background: var(--erp-tint-strong, rgba(198, 167, 94, 0.16));
    border-color: var(--erp-gold, #c6a75e);
}
.dash-cat__chip.is-rte em { color: var(--erp-gold-soft, #e2c98a); }

/* ── Recent activity (timeline) ── */
.dash-activity {
    list-style: none;
    margin: 0;
    padding: 0 0 0 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    border-left: 2px solid var(--erp-line, rgba(255, 255, 255, 0.06));
}
.dash-activity li {
    position: relative;
    font-size: 0.85rem;
    line-height: 1.45;
    color: var(--erp-cream, #f3efe6);
}
.dash-activity li::before {
    content: '';
    position: absolute;
    left: calc(-0.9rem - 6px);
    top: 0.35rem;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--erp-surface, #121318);
    border: 2px solid var(--erp-stat-icon, var(--erp-gold, #c6a75e));
}

.dash-empty {
    padding: 0.85rem 0.2rem;
    font-size: 0.85rem;
    color: var(--erp-muted, #9a958c);
}
.dash-empty.center {
    text-align: center;
    padding: 1.75rem 0.5rem;
}

@media (prefers-reduced-motion: reduce) {
    .dash-kpi,
    .dash-btn,
    .dash-quick__item {
        transition: none;
    }
    .dash-kpi:hover,
    .dash-btn:hover,
    .dash-quick__item:hover {
        transform: none;
    }
}
</style>
