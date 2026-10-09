<template>
    <div v-if="open && person" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40" @click="$emit('close')" />
        <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" :aria-label="`${person.name} profile`">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-base font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">{{ initials }}</span>
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-bold text-slate-900 dark:text-slate-100">{{ person.name }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ typeLabel }}</span>
                            <span v-if="person.employee_id" class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300">EMP {{ person.employee_id }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(person.status)">{{ person.status }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Close" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                <section>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Casual Leave</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2.5 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 sm:grid-cols-2 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <template v-if="loadingCl">
                            <div class="sm:col-span-2 text-sm text-emerald-800/80 dark:text-emerald-200/80">Loading CL balance...</div>
                        </template>
                        <template v-else-if="clBalance">
                            <div>
                                <dt class="text-xs text-emerald-700/70 dark:text-emerald-300/70">Yearly entitlement</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formatCl(clBalance.yearly_entitlement) }} days</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-emerald-700/70 dark:text-emerald-300/70">CL used</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formatCl(clBalance.yearly_used) }} days</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-emerald-700/70 dark:text-emerald-300/70">CL remaining</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCl(clBalance.yearly_remaining) }} / {{ formatCl(clBalance.yearly_entitlement) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-emerald-700/70 dark:text-emerald-300/70">This month</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formatCl(clBalance.monthly_used) }} used · {{ formatCl(clBalance.monthly_remaining) }} left (limit {{ formatCl(clBalance.monthly_limit) }})</dd>
                            </div>
                        </template>
                        <template v-else>
                            <div class="sm:col-span-2 text-sm text-slate-500">CL balance unavailable.</div>
                        </template>
                    </dl>
                </section>
                <section v-for="section in sections" :key="section.title">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ section.title }}</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2.5 rounded-xl border border-slate-200 p-3.5 sm:grid-cols-2 dark:border-slate-700">
                        <div v-for="item in section.items" :key="item.label" class="min-w-0" :class="item.wide ? 'sm:col-span-2' : ''">
                            <dt class="text-xs text-slate-400">{{ item.label }}</dt>
                            <dd class="mt-0.5 break-words text-sm text-slate-800 dark:text-slate-100">
                                <a v-if="item.href && item.value" :href="item.href" class="text-primary-600 hover:underline dark:text-primary-400">{{ item.value }}</a>
                                <template v-else>{{ item.value || '—' }}</template>
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">
                <button type="button" class="btn-outline" @click="$emit('close')">Close</button>
                <button v-if="canEdit" type="button" class="btn-primary" @click="$emit('edit', person)">Edit</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { normalizeCustomFields } from '../../utils/customFields';

const props = defineProps({
    open: { type: Boolean, default: false },
    person: { type: Object, default: null },
    /** teacher | staff | driver */
    type: { type: String, required: true },
    canEdit: { type: Boolean, default: true },
});
const emit = defineEmits(['close', 'edit']);

const clBalance = ref(null);
const loadingCl = ref(false);

function formatCl(value) {
    const n = Number(value) || 0;
    return Number.isInteger(n) ? String(n) : String(Math.round(n * 100) / 100);
}

async function loadClBalance() {
    if (!props.open || !props.person?.id) {
        clBalance.value = null;
        return;
    }
    loadingCl.value = true;
    try {
        const period = new Date().toISOString().slice(0, 7);
        const { data } = await client.get('/finance-payroll/salary-slips/cl-balance', {
            params: {
                employee_type: props.type,
                employee_id: props.person.id,
                period,
            },
        });
        clBalance.value = data.balance || null;
    } catch {
        clBalance.value = null;
    } finally {
        loadingCl.value = false;
    }
}

watch(() => [props.open, props.person?.id, props.type], loadClBalance, { immediate: true });

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) emit('close');
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

/** Custom-field labels shown in fixed places (written by the Staff Profile import). */
const KNOWN_LABELS = [
    'designation', 'grade', 'subject', 'section', 'basic salary at joining',
    'date of birth', 'category', 'join date', 'address', 'hs year', 'inter year', 'grad year', 'remarks',
];

const typeLabel = computed(() => ({ teacher: 'Teacher', staff: 'Staff', driver: 'Driver' })[props.type] || props.type);

const initials = computed(() =>
    String(props.person?.name || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('') || '—',
);

const fields = computed(() => {
    const map = {};
    normalizeCustomFields(props.person?.custom_field_values).forEach((f) => {
        map[f.label.toLowerCase()] = f.value;
    });
    return map;
});

function money(value) {
    if (value === null || value === undefined || value === '') return '';
    const n = Number(String(value).replace(/[^0-9.]/g, ''));
    return Number.isFinite(n) && n > 0 ? `₹${n.toLocaleString('en-IN')}` : String(value);
}

const sections = computed(() => {
    const p = props.person || {};
    const f = fields.value;

    const job = [
        { label: 'Designation', value: f.designation || (props.type === 'staff' ? p.department : '') },
        { label: 'Grade', value: f.grade },
    ];
    if (props.type === 'staff') job.push({ label: 'Department', value: p.department });
    if (props.type === 'teacher') {
        job.push(
            { label: 'Subject', value: f.subject || (p.subjects || []).map((s) => s.name).join(', ') },
            { label: 'Section', value: f.section },
            { label: 'Class head', value: assignments(p, 'head') },
            { label: 'Assistance teacher', value: assignments(p, 'assistant') },
        );
    }
    if (props.type === 'driver') {
        job.push(
            { label: 'License No', value: p.license_no },
            { label: 'Vehicle No', value: p.vehicle_no },
        );
    }
    job.push(
        { label: 'Basic salary (present)', value: money(p.salary) },
        { label: 'Basic salary at joining', value: money(f['basic salary at joining']) },
    );

    const list = [
        {
            title: 'Contact',
            items: [
                { label: 'Phone', value: p.phone, href: p.phone ? `tel:${p.phone}` : '' },
                { label: 'Email (Gmail)', value: p.email, href: p.email ? `mailto:${p.email}` : '' },
            ],
        },
        { title: 'Job', items: job },
        {
            title: 'Personal',
            items: [
                { label: 'Date of birth', value: f['date of birth'] },
                { label: 'Category', value: f.category },
                { label: 'Join date', value: f['join date'] },
                { label: 'Address', value: f.address, wide: true },
            ],
        },
        {
            title: 'Education',
            items: [
                { label: 'HS year', value: f['hs year'] },
                { label: 'Inter year', value: f['inter year'] },
                { label: 'Grad year', value: f['grad year'] },
                { label: 'Qualification / Remarks', value: f.remarks, wide: true },
            ],
        },
    ];

    const other = normalizeCustomFields(p.custom_field_values)
        .filter((x) => !KNOWN_LABELS.includes(x.label.toLowerCase()))
        .map((x) => ({ label: x.label, value: x.value }));
    if (other.length) list.push({ title: 'Other details', items: other });

    return list;
});

function assignments(teacher, role) {
    const rows = (teacher.class_assignments || []).filter((a) => a.role === role);
    return rows.map((a) => (a.school_class?.name || '') + (a.section ? `-${a.section.name}` : '')).join(', ');
}
</script>
