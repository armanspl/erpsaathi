<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Academic Calendar</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Academic Calendar']" class="mt-1" />
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline !text-xs" @click="goToday">Today</button>
                <button type="button" class="btn-primary" @click="openAdd">+ Add entry</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <StatCard label="Total" :value="stats.total" color="indigo" icon="📅" />
            <StatCard label="Holidays" :value="stats.holiday" color="rose" icon="🏖️" />
            <StatCard label="Exams" :value="stats.examination" color="amber" icon="📝" />
            <StatCard label="Programme" :value="stats.programme" color="emerald" icon="🎉" />
            <StatCard label="Deadlines" :value="stats.deadline" color="sky" icon="⏰" />
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div>
                <label class="form-label">Session</label>
                <select v-model="sessionId" class="form-input min-w-[180px]" @change="load">
                    <option :value="null">Current / all</option>
                    <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}{{ s.is_current ? ' (current)' : '' }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Category</label>
                <select v-model="category" class="form-input min-w-[160px]" @change="load">
                    <option value="">All</option>
                    <option v-for="c in categories" :key="c" :value="c">{{ labelCategory(c) }}</option>
                </select>
            </div>
            <p class="pb-2 text-xs text-slate-400">Import/export the glance workbook from Import &amp; Export → Academic Calendar.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <button type="button" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="shiftMonth(-1)">‹</button>
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ monthTitle }}</h2>
                    <button type="button" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="shiftMonth(1)">›</button>
                </div>
                <div class="grid grid-cols-7 border-b border-slate-100 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:border-slate-800">
                    <span v-for="d in weekDays" :key="d" class="py-2">{{ d }}</span>
                </div>
                <div class="grid grid-cols-7 auto-rows-[minmax(84px,1fr)]">
                    <button
                        v-for="cell in calendarCells"
                        :key="cell.key"
                        type="button"
                        class="min-h-[84px] border-b border-r border-slate-100 p-1.5 text-left transition dark:border-slate-800"
                        :class="[
                            cell.inMonth ? 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/60' : 'bg-slate-50/70 text-slate-300 dark:bg-slate-950/40',
                            cell.isToday ? 'ring-2 ring-inset ring-primary-500/40' : '',
                        ]"
                        @click="cell.inMonth && openAddForDay(cell.iso)"
                    >
                        <div class="mb-1 text-[11px] font-semibold" :class="cell.isToday ? 'text-primary-600' : 'text-slate-500'">{{ cell.day }}</div>
                        <div class="space-y-0.5">
                            <span
                                v-for="ev in cell.events.slice(0, 3)"
                                :key="ev.id"
                                class="block truncate rounded px-1 py-0.5 text-[10px] font-medium"
                                :class="categoryChip(ev.category)"
                                @click.stop="openEdit(ev)"
                            >{{ ev.title }}</span>
                            <span v-if="cell.events.length > 3" class="block text-[10px] text-slate-400">+{{ cell.events.length - 3 }} more</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Entries</h3>
                    <p class="text-xs text-slate-400">{{ filteredEntries.length }} item(s)</p>
                </div>
                <div v-if="loading" class="px-4 py-10 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!filteredEntries.length" class="px-4 py-10 text-center text-sm text-slate-400">No calendar entries yet. Import a workbook or add one.</div>
                <ul v-else class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    <li v-for="ev in filteredEntries" :key="ev.id" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="mt-0.5 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="categoryChip(ev.category)">{{ ev.category.slice(0, 4) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ ev.title }}</p>
                            <p class="text-xs text-slate-400">{{ formatEntryDate(ev) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" title="Edit" @click="openEdit(ev)">✎</button>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" title="Delete" @click="remove(ev)">🗑</button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit calendar entry' : 'Add calendar entry'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Title</label>
                <input v-model="form.title" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Category</label>
                <select v-model="form.category" class="form-input">
                    <option v-for="c in categories" :key="c" :value="c">{{ labelCategory(c) }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Start date</label>
                    <input v-model="form.start_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">End date</label>
                    <input v-model="form.end_date" type="date" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Month label</label>
                    <input v-model="form.month_label" type="text" class="form-input" placeholder="AUG, MAY-JUN..." />
                </div>
                <div>
                    <label class="form-label">Date label</label>
                    <input v-model="form.date_label" type="text" class="form-input" placeholder="15-Aug, 24-27 JUL..." />
                </div>
            </div>
            <div>
                <label class="form-label">Session</label>
                <select v-model="form.academic_session_id" class="form-input">
                    <option :value="null">None / current</option>
                    <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Notes</label>
                <textarea v-model="form.notes" class="form-input" rows="3" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const categories = ['holiday', 'examination', 'programme', 'deadline'];

const loading = ref(false);
const saving = ref(false);
const entries = ref([]);
const sessions = ref([]);
const stats = ref({ total: 0, holiday: 0, examination: 0, programme: 0, deadline: 0 });
const sessionId = ref(null);
const category = ref('');
const viewMonth = ref(new Date());
const drawerOpen = ref(false);
const editing = ref(null);
const form = reactive(blankForm());

function blankForm() {
    return {
        title: '',
        category: 'holiday',
        start_date: '',
        end_date: '',
        month_label: '',
        date_label: '',
        academic_session_id: null,
        notes: '',
    };
}

function labelCategory(c) {
    return ({ holiday: 'School Holiday', examination: 'Examination', programme: 'Programme', deadline: 'Deadline' })[c] || c;
}

function categoryChip(c) {
    return ({
        holiday: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        examination: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        programme: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        deadline: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    })[c] || 'bg-slate-100 text-slate-600';
}

const monthTitle = computed(() => viewMonth.value.toLocaleString(undefined, { month: 'long', year: 'numeric' }));

const filteredEntries = computed(() => {
    const y = viewMonth.value.getFullYear();
    const m = viewMonth.value.getMonth();
    return entries.value.filter((ev) => {
        if (!ev.start_date) return true;
        const d = new Date(ev.start_date);
        return d.getFullYear() === y && d.getMonth() === m;
    });
});

const calendarCells = computed(() => {
    const y = viewMonth.value.getFullYear();
    const m = viewMonth.value.getMonth();
    const first = new Date(y, m, 1);
    const startPad = first.getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const today = new Date();
    const cells = [];
    const total = Math.ceil((startPad + daysInMonth) / 7) * 7;

    for (let i = 0; i < total; i++) {
        const dayNum = i - startPad + 1;
        const date = new Date(y, m, dayNum);
        const inMonth = dayNum >= 1 && dayNum <= daysInMonth;
        const iso = inMonth ? toIso(date) : '';
        const dayEvents = inMonth
            ? entries.value.filter((ev) => eventOnDay(ev, iso))
            : [];
        cells.push({
            key: `${y}-${m}-${i}`,
            day: date.getDate(),
            inMonth,
            iso,
            isToday: inMonth && sameDay(date, today),
            events: dayEvents,
        });
    }
    return cells;
});

function toIso(d) {
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

function sameDay(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function eventOnDay(ev, iso) {
    if (!ev.start_date) return false;
    const start = String(ev.start_date).slice(0, 10);
    const end = String(ev.end_date || ev.start_date).slice(0, 10);
    return iso >= start && iso <= end;
}

function formatEntryDate(ev) {
    if (ev.date_label) return [ev.month_label, ev.date_label].filter(Boolean).join(' · ');
    if (!ev.start_date) return ev.month_label || 'No date';
    const s = String(ev.start_date).slice(0, 10);
    const e = ev.end_date ? String(ev.end_date).slice(0, 10) : null;
    return e && e !== s ? `${s} → ${e}` : s;
}

function shiftMonth(delta) {
    const d = new Date(viewMonth.value);
    d.setMonth(d.getMonth() + delta);
    viewMonth.value = d;
}

function goToday() {
    viewMonth.value = new Date();
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (sessionId.value) params.academic_session_id = sessionId.value;
        if (category.value) params.category = category.value;
        const { data } = await client.get('/academics/academic-calendar', { params });
        entries.value = data.entries || [];
        sessions.value = data.sessions || [];
        stats.value = data.stats || stats.value;
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Failed to load calendar.', 'error');
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    editing.value = null;
    Object.assign(form, blankForm());
    form.academic_session_id = sessionId.value;
    drawerOpen.value = true;
}

function openAddForDay(iso) {
    editing.value = null;
    Object.assign(form, blankForm());
    form.start_date = iso;
    form.end_date = iso;
    form.academic_session_id = sessionId.value;
    drawerOpen.value = true;
}

function openEdit(ev) {
    editing.value = ev;
    Object.assign(form, {
        title: ev.title || '',
        category: ev.category || 'holiday',
        start_date: ev.start_date ? String(ev.start_date).slice(0, 10) : '',
        end_date: ev.end_date ? String(ev.end_date).slice(0, 10) : '',
        month_label: ev.month_label || '',
        date_label: ev.date_label || '',
        academic_session_id: ev.academic_session_id || null,
        notes: ev.notes || '',
    });
    drawerOpen.value = true;
}

async function save() {
    if (!form.title.trim()) {
        pushToast('Title is required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            ...form,
            academic_session_id: form.academic_session_id || null,
            start_date: form.start_date || null,
            end_date: form.end_date || null,
            month_label: form.month_label || null,
            date_label: form.date_label || null,
            notes: form.notes || null,
        };
        if (editing.value) {
            await client.put(`/academics/academic-calendar/${editing.value.id}`, payload);
            pushToast('Entry updated.', 'success');
        } else {
            await client.post('/academics/academic-calendar', payload);
            pushToast('Entry added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(ev) {
    if (!confirm(`Delete “${ev.title}”?`)) return;
    try {
        await client.delete(`/academics/academic-calendar/${ev.id}`);
        pushToast('Deleted.', 'success');
        await load();
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Delete failed.', 'error');
    }
}

onMounted(load);
</script>
