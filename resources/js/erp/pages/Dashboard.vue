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
                <RouterLink to="/fee-management/pay-fee" class="dash-btn dash-btn--primary">Collect fee</RouterLink>
                <RouterLink to="/people/students?add=1" class="dash-btn">Add student</RouterLink>
                <RouterLink :to="{ path: '/import-export', query: { type: 'global-workbook-export' } }" class="dash-btn">Export workbook</RouterLink>
            </div>
        </header>

        <div v-if="error" class="dash-alert">
            {{ error }}
            <button type="button" class="dash-alert__retry" @click="load">Retry</button>
        </div>

        <div v-if="loading" class="dash-skel">
            <div v-for="n in 8" :key="n" class="dash-skel__card" />
        </div>

        <template v-else-if="!error">
            <section class="dash-stats">
                <article v-for="s in peopleStats" :key="s.label" class="dash-stat">
                    <p class="dash-stat__label">{{ s.label }}</p>
                    <p class="dash-stat__value">{{ s.value }}</p>
                </article>
            </section>

            <section class="dash-stats dash-stats--money">
                <article v-for="s in moneyStats" :key="s.label" class="dash-stat dash-stat--accent">
                    <p class="dash-stat__label">{{ s.label }}</p>
                    <p class="dash-stat__value">{{ s.value }}</p>
                </article>
            </section>

            <div class="dash-grid dash-grid--3">
                <DashCard title="Today">
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
                        </div>
                    </div>
                </DashCard>

                <DashCard title="Pending tasks">
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

                <DashCard title="Quick links">
                    <div class="dash-quick">
                        <RouterLink v-for="action in quickActions" :key="action.label" :to="action.to" class="dash-quick__item">
                            {{ action.label }}
                        </RouterLink>
                    </div>
                </DashCard>
            </div>

            <div class="dash-grid dash-grid--2">
                <DashCard title="Class-wise strength" to="/people/students">
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
                        </table>
                    </div>
                </DashCard>

                <DashCard title="Fee by class" to="/fee-management/fee-collection-report">
                    <div v-if="!feeCollectionStatus.length" class="dash-empty center">No fee data for the current session.</div>
                    <div v-else class="dash-scroll dash-fee">
                        <div class="dash-fee__legend">
                            <span class="leg is-collected">Collected</span>
                            <span class="leg is-due">Due</span>
                        </div>
                        <div v-for="c in feeCollectionStatus" :key="c.class" class="dash-fee__row">
                            <div class="dash-fee__head">
                                <span class="name">{{ c.class }}</span>
                                <span class="dash-fee__amts">
                                    <span class="amt is-collected">₹{{ shortMoney(c.collected_amount) }}</span>
                                    <span class="amt is-due">₹{{ shortMoney(c.due_amount) }}</span>
                                </span>
                            </div>
                            <div class="dash-fee__stack" :title="`${c.collected}% collected · ${c.pending} pending`">
                                <span class="seg is-collected" :style="{ width: barWidth(c, 'collected') }" />
                                <span class="seg is-due" :style="{ width: barWidth(c, 'due') }" />
                            </div>
                        </div>
                    </div>
                </DashCard>
            </div>

            <DashCard title="Category-wise (boys / girls)" to="/people/students">
                <div v-if="!categoryByClass.length" class="dash-empty center">No category data yet.</div>
                <div v-else class="dash-cat">
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
            </DashCard>

            <div class="dash-grid dash-grid--3">
                <DashCard title="Recent admissions" to="/admissions/admission">
                    <div v-if="!recentAdmissions.length" class="dash-empty">No admissions yet.</div>
                    <ul v-else class="dash-feed">
                        <li v-for="r in recentAdmissions" :key="r.adm">
                            <div class="min">
                                <p class="name">{{ r.name }}</p>
                                <p class="hint">{{ r.adm }} · {{ r.class }}</p>
                            </div>
                            <span class="hint">{{ r.date }}</span>
                        </li>
                    </ul>
                </DashCard>

                <DashCard title="Upcoming events">
                    <div v-if="!upcomingEvents.length" class="dash-empty">No upcoming events.</div>
                    <ul v-else class="dash-feed">
                        <li v-for="e in upcomingEvents" :key="e.title + e.date">
                            <p class="name">{{ e.title }}</p>
                            <span class="hint">{{ e.date }}</span>
                        </li>
                    </ul>
                </DashCard>

                <DashCard title="Recent activity">
                    <div v-if="!recentActivities.length" class="dash-empty">No recent activity.</div>
                    <ul v-else class="dash-activity">
                        <li v-for="(a, i) in recentActivities" :key="i">{{ a }}</li>
                    </ul>
                </DashCard>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import DashCard from '../components/dashboard/DashCard.vue';
import { erpStore } from '../store';
import client from '../api/client';

const today = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
const firstName = computed(() => (erpStore.user?.name || '').split(' ')[0] || '');

const loading = ref(true);
const error = ref('');
const peopleStats = ref([]);
const moneyStats = ref([]);
const todaySummary = ref({
    students: { present: 0, absent: 0 },
    staff: { present: 0, absent: 0 },
    fee: { received: 0, cash: 0, bank: 0, receipts: 0 },
});
const classStrength = ref([]);
const feeCollectionStatus = ref([]);
const categoryByClass = ref([]);
const recentAdmissions = ref([]);
const pendingTasks = ref([]);
const upcomingEvents = ref([]);
const recentActivities = ref([]);

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

function barWidth(row, kind) {
    const collected = Number(row.collected_amount || 0);
    const due = Number(row.due_amount || 0);
    const total = collected + due;
    if (total <= 0) return '0%';
    const pct = kind === 'collected' ? (collected / total) * 100 : (due / total) * 100;
    return `${Math.max(pct > 0 ? 4 : 0, pct)}%`;
}

function categoryHue(name) {
    let hash = 0;
    const s = String(name || '');
    for (let i = 0; i < s.length; i += 1) hash = (hash * 31 + s.charCodeAt(i)) % 360;
    return hash;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await client.get('/dashboard');
        peopleStats.value = [
            { label: 'Students', value: data.people_stats.students },
            { label: 'Teachers', value: data.people_stats.teachers },
            { label: 'Staff', value: data.people_stats.staff },
            { label: 'Drivers', value: data.people_stats.drivers },
        ];
        moneyStats.value = [
            { label: 'Fee collected', value: `₹${money(data.money_stats.fee_collected)}` },
            { label: 'Fee pending', value: `₹${money(data.money_stats.fee_pending)}` },
            { label: 'Bank balance', value: `₹${money(data.money_stats.bank_balance)}` },
            { label: 'Expense', value: `₹${money(data.money_stats.expense)}` },
        ];
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
        error.value = e.response?.data?.message || 'Could not load dashboard.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => erpStore.currentSession, load);

const quickActions = [
    { label: 'Add student', to: '/people/students?add=1' },
    { label: 'Collect fee', to: '/fee-management/pay-fee' },
    { label: 'Fee due', to: '/fee-management/fee-due' },
    { label: 'Import Excel', to: '/import-export' },
    { label: 'Admit cards', to: '/exam-management/admit-cards' },
];
</script>

<style scoped>
.dash {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif;
    color: var(--erp-cream, #f3efe6);
}

.dash-hero {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0 0.5rem;
    border-bottom: 1px solid var(--erp-border, rgba(198, 167, 94, 0.16));
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
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(1.85rem, 3vw, 2.35rem);
    font-weight: 600;
    line-height: 1.15;
    color: var(--erp-cream, #f3efe6);
}
.dash-sub {
    margin: 0.4rem 0 0;
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
    padding: 0.5rem 0.9rem;
    border-radius: 8px;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.28));
    background: transparent;
    color: var(--erp-cream, #f3efe6);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.dash-btn:hover {
    border-color: var(--erp-gold, #c6a75e);
    color: var(--erp-gold-soft, #e2c98a);
}
.dash-btn--primary {
    background: linear-gradient(135deg, var(--erp-gold, #c6a75e), var(--erp-brand-to, #a8883f));
    border-color: transparent;
    color: var(--erp-brand-ink, #14120e);
}
.dash-btn--primary:hover {
    color: #0b0a08;
    filter: brightness(1.05);
}

.dash-alert {
    border: 1px solid rgba(248, 180, 180, 0.35);
    background: rgba(180, 60, 60, 0.18);
    color: #f6c1c1;
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
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .dash-skel { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.dash-skel__card {
    height: 5.5rem;
    border-radius: 12px;
    background: linear-gradient(90deg, rgba(255,255,255,0.04), rgba(255,255,255,0.08), rgba(255,255,255,0.04));
    background-size: 200% 100%;
    animation: dash-pulse 1.4s ease-in-out infinite;
}
@keyframes dash-pulse {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

.dash-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .dash-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.dash-stat {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    border-radius: 14px;
    padding: 1rem 1.1rem;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18);
}
.dash-stat::after {
    content: '';
    position: absolute;
    inset: auto -20% -40% auto;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(198, 167, 94, 0.16), transparent 70%);
    pointer-events: none;
}
.dash-stat--accent::after {
    background: radial-gradient(circle, rgba(198, 167, 94, 0.28), transparent 70%);
}
.dash-stat__label {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
.dash-stat__value {
    margin: 0.45rem 0 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.85rem;
    font-weight: 600;
    line-height: 1.1;
    color: var(--erp-cream, #f3efe6);
}
.dash-stats--money .dash-stat__value {
    color: var(--erp-gold-soft, #e2c98a);
}

.dash-grid {
    display: grid;
    gap: 1rem;
}
.dash-grid--2 { grid-template-columns: 1fr; }
.dash-grid--3 { grid-template-columns: 1fr; }
@media (min-width: 1024px) {
    .dash-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dash-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

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
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.04);
    font-size: 0.85rem;
    color: var(--erp-muted, #9a958c);
}
.dash-row--split {
    display: grid;
    grid-template-columns: 1fr 1fr;
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
    opacity: 0.8;
}
.dash-row--split .mode strong {
    font-size: 0.92rem;
    font-weight: 700;
}
.dash-row--split .is-cash {
    background: rgba(125, 206, 160, 0.12);
    color: #9fe0b8;
}
.dash-row--split .is-bank {
    background: rgba(125, 180, 230, 0.12);
    color: #9ec8f0;
}
.dash-row__vals {
    display: inline-flex;
    align-items: baseline;
    gap: 0.25rem;
}
.is-ok { color: #7dcea0; }
.is-bad { color: #e8a0a0; }
.is-cream { color: var(--erp-cream, #f3efe6); }
.is-boy { color: #7eb6e8; }
.is-girl { color: #e8a0c8; }
.sep { color: rgba(243, 239, 230, 0.35); }
.hint {
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--erp-muted, #9a958c);
}

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
    padding: 0.55rem 0.4rem;
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--erp-cream, #f3efe6);
    text-decoration: none;
    transition: background 0.15s ease;
}
.dash-task:hover {
    background: rgba(198, 167, 94, 0.08);
}
.dash-badge {
    min-width: 1.5rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    text-align: center;
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.06);
    color: var(--erp-muted, #9a958c);
}
.dash-badge.is-warn {
    background: rgba(198, 167, 94, 0.18);
    color: var(--erp-gold-soft, #e2c98a);
}

.dash-quick {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}
.dash-quick__item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.7rem 0.5rem;
    border-radius: 10px;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: rgba(255, 255, 255, 0.02);
    color: var(--erp-cream, #f3efe6);
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-align: center;
    text-decoration: none;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}
.dash-quick__item:hover {
    border-color: var(--erp-gold, #c6a75e);
    background: rgba(198, 167, 94, 0.1);
    color: var(--erp-gold-soft, #e2c98a);
}

.dash-scroll {
    max-height: 18rem;
    overflow: auto;
    padding-right: 0.15rem;
}
.dash-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.dash-table--tight {
    font-size: 0.78rem;
}
.dash-table th {
    position: sticky;
    top: 0;
    background: var(--erp-surface-solid, #121318);
    padding: 0 0.2rem 0.55rem 0;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
    text-align: left;
}
.dash-table td {
    padding: 0.45rem 0.2rem 0.45rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--erp-muted, #9a958c);
}
.dash-table .name,
.dash-fee .name,
.dash-feed .name,
.dash-cat__title .name {
    color: var(--erp-cream, #f3efe6);
    font-weight: 600;
}
.dash-table .num { text-align: right; }
.dash-table .total { color: var(--erp-gold-soft, #e2c98a); font-weight: 700; }
.dash-table .is-new { color: #c6b4f0; }

.dash-fee {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.dash-fee__legend {
    display: flex;
    gap: 0.85rem;
    margin-bottom: 0.15rem;
}
.dash-fee__legend .leg {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
.dash-fee__legend .leg::before {
    content: '';
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 2px;
}
.dash-fee__legend .is-collected::before { background: #4ecf8a; }
.dash-fee__legend .is-due::before { background: #f0a070; }
.dash-fee__row { min-width: 0; }
.dash-fee__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
    font-size: 0.8rem;
}
.dash-fee__amts {
    display: inline-flex;
    gap: 0.55rem;
    font-size: 0.7rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.dash-fee__amts .is-collected { color: #7dcea0; }
.dash-fee__amts .is-due { color: #f0a070; }
.dash-fee__stack {
    display: flex;
    height: 0.45rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
}
.dash-fee__stack .seg {
    display: block;
    height: 100%;
    min-width: 0;
    transition: width 0.35s ease;
}
.dash-fee__stack .seg.is-collected {
    background: linear-gradient(90deg, #2f9e68, #4ecf8a);
}
.dash-fee__stack .seg.is-due {
    background: linear-gradient(90deg, #d47a45, #f0a070);
}

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
@media (min-width: 1280px) {
    .dash-cat { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.dash-cat__class {
    padding: 0.55rem 0.65rem;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(255, 255, 255, 0.025);
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
    background: hsl(var(--cat-hue, 40) 35% 18% / 0.85);
    border: 1px solid hsl(var(--cat-hue, 40) 45% 40% / 0.35);
    color: var(--erp-cream, #f3efe6);
}
.dash-cat__chip em {
    font-style: normal;
    font-weight: 600;
    margin-right: 0.15rem;
    color: hsl(var(--cat-hue, 40) 70% 72%);
}
.dash-cat__chip strong { font-weight: 700; }

.dash-feed,
.dash-activity {
    list-style: none;
    margin: 0;
    padding: 0;
}
.dash-feed li {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 0.85rem;
}
.dash-feed li:first-child { border-top: 0; padding-top: 0.15rem; }
.dash-feed .min { min-width: 0; }
.dash-feed .name {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dash-feed .hint { margin: 0.15rem 0 0; flex-shrink: 0; }

.dash-activity {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.dash-activity li {
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.04);
    font-size: 0.85rem;
    color: var(--erp-muted, #9a958c);
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
</style>
