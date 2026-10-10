<template>
    <!-- The routine tables open in a plain new tab (Open full view) instead of being drawn here by Vue:
         redrawing a few hundred table cells on a tab switch made Chrome 154 hang / crash the tab
         (its optimiser miscompiles Vue's patch() for this page; fine with --jitless). -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div v-if="!groups.length" class="py-10 text-center text-sm text-slate-400">This routine has no periods filled in yet.</div>
        <template v-else>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Routine view — like the school's Excel sheets</h3>
            <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                <li v-for="g in groups" :key="g.id">
                    <b>{{ g.label }}</b> — class wise: SUB / TEACH per period, each teacher's period count ({{ totalPeriods(g) }} periods), and the LEISURE row (free teachers per period).
                </li>
                <li><b>Teacher wise</b> — SUB / CLASS per period for every teacher, free periods marked FREE, clashes in red.</li>
            </ul>
            <label class="mt-4 inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <input v-model="includeIdle" type="checkbox" class="rounded border-slate-300" />
                Include teachers with no periods (they show as free all day)
            </label>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="printRoutine('view')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    Open full view
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="printRoutine('all')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                    Print
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-400">Opens in a new browser tab — allow pop-ups for this site if nothing appears.</p>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { pushToast } from '../../utils/toast';

/**
 * Read-only Class Routine view, laid out like the school's Excel workbook:
 *  - one class-wise sheet per day group (MON / TUE / WED, THU / FRI / SAT): SUB + TEACH rows per class,
 *    the teacher list with each teacher's period count, and the LEISURE rows (who is free each period);
 *  - TEACHER WISE: SUB + CLASS rows per teacher.
 * Days with identical timetables are shown as one group; a routine whose days differ (e.g. built by
 * hand) shows those days separately. Works the same for imported and manually created routines.
 */
const props = defineProps({
    // GET /academics/class-routine/{id}: { title, periods_per_day, days: [{ day_of_week, entries }], ... }
    sheet: { type: Object, required: true },
    classes: { type: Array, default: () => [] },   // all classes, in display order
    subjects: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },  // active teachers { id, name, color }
    schoolName: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});

const DAY_SHORT = { 1: 'MON', 2: 'TUE', 3: 'WED', 4: 'THU', 5: 'FRI', 6: 'SAT' };
const DAY_GROUPS = [[1, 2, 3], [4, 5, 6]];

const includeIdle = ref(false);
const printMenu = ref(false);
const printWrap = ref(null);

const periods = computed(() => Array.from({ length: props.sheet.periods_per_day || 0 }, (_, i) => i + 1));

// day_of_week -> "classId|period" -> entry
const byDay = computed(() => {
    const map = {};
    (props.sheet.days || []).forEach((d) => {
        map[d.day_of_week] = {};
        (d.entries || []).forEach((e) => { map[d.day_of_week][`${e.school_class_id}|${e.period_number}`] = e; });
    });
    return map;
});

function daySignature(dow) {
    return JSON.stringify(Object.entries(byDay.value[dow] || {}).sort(([a], [b]) => a.localeCompare(b))
        .map(([k, e]) => [k, e.subject_id, e.subject_label, e.teacher_id, e.teacher_label]));
}

// MON-TUE-WED / THU-FRI-SAT when those days match (as in the school's workbook), else each day alone.
const groups = computed(() => {
    const out = [];
    DAY_GROUPS.forEach((days) => {
        const filled = days.filter((d) => Object.keys(byDay.value[d] || {}).length);
        if (!filled.length) return;
        const sigs = new Set(days.map(daySignature));
        if (sigs.size === 1) {
            out.push({ id: days.join('-'), label: days.map((d) => DAY_SHORT[d]).join(' / '), day: days[0] });
        } else {
            days.forEach((d) => out.push({ id: String(d), label: DAY_SHORT[d], day: d }));
        }
    });
    return out;
});

const tabs = computed(() => [...groups.value.map((g) => ({ id: g.id, label: g.label })), ...(groups.value.length ? [{ id: 'teacher', label: 'Teacher wise' }] : [])]);
const activeTab = ref('');
const teacherGroupId = ref('');
watch(groups, (gs) => {
    if (!tabs.value.some((t) => t.id === activeTab.value)) activeTab.value = gs[0]?.id || '';
    if (!gs.some((g) => g.id === teacherGroupId.value)) teacherGroupId.value = gs[0]?.id || '';
}, { immediate: true });

const activeGroup = computed(() => groups.value.find((g) => g.id === activeTab.value) || null);
const teacherGroup = computed(() => groups.value.find((g) => g.id === teacherGroupId.value) || groups.value[0]);
const activeTabLabel = computed(() => (activeTab.value === 'teacher' ? `Teacher wise — ${teacherGroup.value?.label || ''}` : `Class wise — ${activeGroup.value?.label || ''}`));

// Classes that appear anywhere in this routine, in the school's class order.
const routineClasses = computed(() => {
    const used = new Set();
    Object.values(byDay.value).forEach((day) => Object.values(day).forEach((e) => used.add(e.school_class_id)));
    const known = props.classes.filter((c) => used.has(c.id));
    const extra = [...used].filter((id) => !props.classes.some((c) => c.id === id)).map((id) => ({ id, name: findClassName(id) }));
    return [...known, ...extra];
});
function findClassName(id) {
    for (const day of Object.values(byDay.value)) {
        for (const e of Object.values(day)) if (e.school_class_id === id && e.school_class?.name) return e.school_class.name;
    }
    return `#${id}`;
}
const classNameById = computed(() => Object.fromEntries(routineClasses.value.map((c) => [c.id, c.name])));

function slot(group, classId, period) {
    return group ? byDay.value[group.day]?.[`${classId}|${period}`] || null : null;
}
function subjectOf(e) {
    if (!e) return '';
    return e.subject?.name || props.subjects.find((s) => s.id === e.subject_id)?.name || e.subject_label || '';
}
// Matched teachers are keyed by id, text-only (unmatched import) names by the name itself.
function teacherKeyOf(e) {
    if (!e) return null;
    if (e.teacher_id) return `t${e.teacher_id}`;
    const label = (e.teacher_label || '').trim().toUpperCase();
    return label ? `l${label}` : null;
}
function teacherNameOf(e) {
    if (!e) return '';
    if (e.teacher_id) return e.teacher?.name || props.teachers.find((t) => t.id === e.teacher_id)?.name || `Teacher #${e.teacher_id}`;
    return (e.teacher_label || '').trim();
}
function badgeStyle(e) {
    const color = e?.color || e?.teacher?.color || props.teachers.find((t) => t.id === e?.teacher_id)?.color;
    return color ? { background: color, color: contrastColor(color) } : { background: '#e2e8f0', color: '#1e293b' };
}
function contrastColor(hex) {
    const h = String(hex || '').replace('#', '');
    if (h.length !== 6) return '#1e293b';
    const lum = (0.299 * parseInt(h.slice(0, 2), 16) + 0.587 * parseInt(h.slice(2, 4), 16) + 0.114 * parseInt(h.slice(4, 6), 16)) / 255;
    return lum > 0.6 ? '#000000' : '#ffffff';
}
function remarksOf(classId) {
    for (const day of Object.values(byDay.value)) {
        for (const e of Object.values(day)) if (e.school_class_id === classId && e.remarks) return e.remarks;
    }
    return '';
}

// teacherKey -> period -> [entries] for one day
function teacherSlots(group) {
    const out = {};
    Object.values((group && byDay.value[group.day]) || {}).forEach((e) => {
        const key = teacherKeyOf(e);
        if (!key) return;
        if (!out[key]) out[key] = {};
        if (!out[key][e.period_number]) out[key][e.period_number] = [];
        out[key][e.period_number].push(e);
    });
    return out;
}
const slotsCache = computed(() => Object.fromEntries(groups.value.map((g) => [g.id, teacherSlots(g)])));
function slotsFor(group) {
    return (group && slotsCache.value[group.id]) || {};
}
function countFor(group, key) {
    return Object.values(slotsFor(group)[key] || {}).reduce((n, list) => n + list.length, 0);
}
function totalPeriods(group) {
    return Object.values(slotsFor(group)).reduce((n, byPeriod) => n + Object.values(byPeriod).reduce((m, l) => m + l.length, 0), 0);
}

// All active teachers + any teacher named in the routine (matched-but-inactive, or text-only names).
const fullRoster = computed(() => {
    const roster = new Map(props.teachers.map((t) => [`t${t.id}`, { key: `t${t.id}`, name: t.name }]));
    Object.values(byDay.value).forEach((day) => Object.values(day).forEach((e) => {
        const key = teacherKeyOf(e);
        if (key && !roster.has(key)) roster.set(key, { key, name: teacherNameOf(e) });
    }));
    return [...roster.values()].sort((a, b) => a.name.localeCompare(b.name));
});
// Teachers who teach somewhere in this routine (any day) — the list when "no periods" ones are hidden.
const teachingKeys = computed(() => {
    const keys = new Set();
    Object.values(byDay.value).forEach((day) => Object.values(day).forEach((e) => { const k = teacherKeyOf(e); if (k) keys.add(k); }));
    return keys;
});
function rosterFor() {
    return includeIdle.value ? fullRoster.value : fullRoster.value.filter((t) => teachingKeys.value.has(t.key));
}
function freeTeachers(group, period) {
    const slots = slotsFor(group);
    return rosterFor().filter((t) => !slots[t.key]?.[period]?.length);
}

function teacherCell(group, key, period) {
    const list = slotsFor(group)[key]?.[period] || [];
    if (!list.length) return { sub: 'FREE', cls: '', free: true, clash: false };
    return {
        sub: [...new Set(list.map(subjectOf).filter(Boolean))].join(' / '),
        cls: list.map((e) => classNameById.value[e.school_class_id] || findClassName(e.school_class_id)).join(' + '),
        free: false,
        clash: list.length > 1,
    };
}
function cellClass(group, key, period) {
    const c = teacherCell(group, key, period);
    if (c.clash) return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    if (c.free) return 'text-slate-300 dark:text-slate-600';
    return 'text-slate-700 dark:text-slate-200';
}


// ---- Printing ----
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function header(title) {
    return `<div class="hd"><div class="school">${esc(props.schoolName)}</div>
        <div class="title">${esc(props.sheet.title || 'Class Routine')}${props.subtitle ? ` &middot; ${esc(props.subtitle)}` : ''}</div>
        <div class="sheet-name">${esc(title)}</div></div>`;
}

function classWiseHtml(group) {
    const ps = periods.value;
    const roster = rosterFor();
    const teachers = `<table class="teachers"><thead><tr><th>#</th><th>TEACHERS</th><th>PERIOD</th></tr></thead><tbody>${
        roster.map((t, i) => `<tr><td>${i + 1}</td><td>${esc(t.name)}</td><td class="c">${countFor(group, t.key)}</td></tr>`).join('')
    }</tbody><tfoot><tr><td></td><td>TOTAL</td><td class="c">${totalPeriods(group)}</td></tr></tfoot></table>`;

    const rows = routineClasses.value.map((c) => {
        const subs = ps.map((p) => `<td class="c sub">${esc(subjectOf(slot(group, c.id, p)))}</td>`).join('');
        const teach = ps.map((p) => {
            const e = slot(group, c.id, p);
            if (!teacherKeyOf(e)) return '<td></td>';
            const st = badgeStyle(e);
            return `<td class="c teach" style="background:${st.background};color:${st.color}">${esc(teacherNameOf(e))}</td>`;
        }).join('');
        return `<tr><td rowspan="2" class="cls">${esc(c.name)}</td><td class="lbl">SUB</td>${subs}<td rowspan="2">${esc(remarksOf(c.id))}</td></tr>
            <tr class="sep"><td class="lbl">TEACH</td>${teach}</tr>`;
    }).join('');
    const grid = `<table class="grid"><thead><tr><th>CLASS</th><th>PERIOD</th>${ps.map((p) => `<th>${p}</th>`).join('')}<th>REMARKS</th></tr></thead><tbody>${rows}</tbody></table>`;

    const leisure = `<table class="grid leisure"><thead><tr><th>LEISURE</th>${ps.map((p) => `<th>${p}</th>`).join('')}</tr></thead><tbody><tr><td class="lbl">FREE</td>${
        ps.map((p) => `<td class="c">${freeTeachers(group, p).map((t) => esc(t.name)).join('<br>') || '&mdash;'}</td>`).join('')
    }</tr></tbody></table>`;

    return `<section>${header(`CLASS WISE — ${group.label}`)}<div class="cols"><div class="left">${teachers}</div><div class="right">${grid}${leisure}</div></div></section>`;
}

function teacherWiseHtml(group) {
    const ps = periods.value;
    const rows = rosterFor().map((t) => {
        const cells = ps.map((p) => teacherCell(group, t.key, p));
        const cls = (c) => (c.clash ? ' clash' : c.free ? ' free' : '');
        return `<tr><td rowspan="2" class="cls">${esc(t.name)}</td><td class="lbl">SUB</td>${cells.map((c) => `<td class="c sub${cls(c)}">${esc(c.sub)}</td>`).join('')}<td rowspan="2" class="c">${countFor(group, t.key)}</td></tr>
            <tr class="sep"><td class="lbl">CLASS</td>${cells.map((c) => `<td class="c${cls(c)}">${esc(c.cls)}</td>`).join('')}</tr>`;
    }).join('');
    return `<section>${header(`TEACHER WISE — ${group.label}`)}<table class="grid"><thead><tr><th>TEACHER</th><th>PERIOD</th>${ps.map((p) => `<th>${p}</th>`).join('')}<th>PERIODS</th></tr></thead><tbody>${rows}</tbody></table></section>`;
}

// mode "view": open the whole routine in a new tab to look at (Print button there); "all": print it.
function printRoutine(mode) {
    printMenu.value = false;
    let sections;
    if (mode === 'all' || mode === 'view') {
        sections = [...groups.value.map(classWiseHtml), ...groups.value.map(teacherWiseHtml)];
    } else {
        sections = [activeTab.value === 'teacher' ? teacherWiseHtml(teacherGroup.value) : classWiseHtml(activeGroup.value)];
    }

    const win = window.open('', '_blank');
    if (!win) {
        pushToast('Allow pop-ups for this site to print.', 'error');
        return;
    }
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(props.sheet.title || 'Class Routine')}</title><style>
        @page { size: A4 landscape; margin: 7mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; font-size: 9px; }
        section { page-break-after: always; } section:last-child { page-break-after: auto; }
        .hd { text-align: center; margin-bottom: 5px; } .school { font-size: 15px; font-weight: bold; }
        .title { font-size: 10px; color: #444; } .sheet-name { font-size: 12px; font-weight: bold; margin-top: 2px; letter-spacing: .5px; }
        .cols { display: flex; gap: 6px; align-items: flex-start; } .left { flex: 0 0 auto; } .right { flex: 1 1 auto; }
        table { border-collapse: collapse; width: 100%; } .teachers { width: auto; }
        th, td { border: 1px solid #888; padding: 2px 3px; vertical-align: middle; }
        th { background: #e5e7eb; font-weight: bold; text-align: center; }
        .c { text-align: center; } .sub { font-weight: bold; text-transform: uppercase; } .teach { font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .cls { font-weight: bold; text-align: center; text-transform: uppercase; } .lbl { font-size: 7px; color: #555; font-weight: bold; text-align: center; }
        tr.sep td { border-bottom: 1.5px solid #333; }
        .leisure { margin-top: 6px; } .leisure th { background: #d1fae5; } .leisure td { vertical-align: top; font-weight: bold; color: #065f46; }
        .free { color: #9ca3af; font-weight: normal; } .clash { background: #fee2e2; color: #b91c1c; }
        tfoot td { font-weight: bold; background: #f3f4f6; }
        .bar { position: sticky; top: 0; background: #fff; padding: 8px; text-align: right; border-bottom: 1px solid #ddd; }
        .bar button { font-size: 13px; padding: 6px 14px; cursor: pointer; }
        @media screen { body { padding: 0 10px 20px; font-size: 11px; } section { margin-top: 18px; padding-bottom: 18px; border-bottom: 2px dashed #ccc; } }
        @media print { .bar { display: none; } }
    </style></head><body>${mode === 'view' ? '<div class="bar"><button onclick="window.print()">Print</button></div>' : ''}${sections.join('')}
        ${mode === 'view' ? '' : '<script>window.onload = function () { window.focus(); window.print(); };<\/script>'}
    </body></html>`);
    win.document.close();
}

function closePrintMenu(e) {
    if (!printWrap.value?.contains(e.target)) printMenu.value = false;
}
onMounted(() => document.addEventListener('click', closePrintMenu));
onBeforeUnmount(() => document.removeEventListener('click', closePrintMenu));
</script>
