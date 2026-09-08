<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">UDISE+ S03</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'UDISE+ S03']" class="mt-1" />
                <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                    Download <strong class="font-medium text-slate-700 dark:text-slate-200">FORM S03/UDISE</strong> — Format to update student details — pre-filled from school settings and student records. Shows all students by default; filter by status below if needed.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="btn-outline"
                    :disabled="!selectedIds.size || zipping"
                    @click="downloadZip"
                >
                    {{ zipping ? 'Preparing ZIP...' : `Download S03 ZIP (${selectedIds.size})` }}
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            Set <strong>District</strong> and <strong>Block</strong> under Settings → School Settings so the form header matches your UDISE portal.
            Prepare opens with FOR and Existing details locked from the student record — edit Updated details before download if needed.
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <input
                        v-model="filterValues.search"
                        type="text"
                        placeholder="Search name, admission no, PEN..."
                        class="form-input"
                    />
                </div>
                <select v-model="filterValues.branch_id" class="form-input">
                    <option :value="null">Branch — All</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="filterValues.school_class_id" class="form-input" @change="filterValues.section_id = null">
                    <option :value="null">Class — All</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filterValues.section_id" class="form-input" :disabled="!filterValues.school_class_id">
                    <option :value="null">Section — All</option>
                    <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="filterValues.status" class="form-input">
                    <option :value="null">Status — All</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Transferred">Transferred</option>
                </select>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" class="btn-outline !py-1.5 !text-xs" @click="resetFilters">Reset</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Students" :value="rows.length" color="indigo" icon="🎓" />
            <StatCard label="With PEN" :value="withPen" color="emerald" icon="🆔" />
            <StatCard label="Missing PEN" :value="rows.length - withPen" color="amber" icon="⚠️" />
            <StatCard label="Selected" :value="selectedIds.size" color="sky" icon="☑" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="w-10 px-3 py-3">
                            <input type="checkbox" class="rounded border-slate-300" :checked="allSelected" @change="toggleAll($event.target.checked)" />
                        </th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adm No</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">PEN</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Aadhaar</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">FORM S03</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No students match your filters.</td>
                    </tr>
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-3 py-3">
                            <input type="checkbox" class="rounded border-slate-300" :checked="selectedIds.has(r.id)" @change="toggleOne(r.id, $event.target.checked)" />
                        </td>
                        <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ r.admission_no || '—' }}</td>
                        <td class="px-3 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                            <p class="text-xs text-slate-400">{{ r.mobile || '—' }}</p>
                        </td>
                        <td class="px-3 py-3 text-slate-500 dark:text-slate-400">
                            {{ r.school_class_name || '—' }}{{ r.section_name ? ` (${r.section_name})` : '' }}
                        </td>
                        <td class="px-3 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="{
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400': r.status === 'Active' || !r.status,
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300': r.status === 'Inactive',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': r.status === 'Transferred',
                                }"
                            >
                                {{ r.status || 'Active' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 font-mono text-xs" :class="r.student_pen ? 'text-slate-700 dark:text-slate-200' : 'text-amber-600'">
                            {{ r.student_pen || 'Missing' }}
                        </td>
                        <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ maskAadhaar(r.aadhar_no) }}</td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="openForm(r)">Prepare</button>
                                <button
                                    type="button"
                                    class="btn-primary !py-1 !text-xs"
                                    :disabled="downloadingId === r.id"
                                    @click="quickDownload(r)"
                                >
                                    {{ downloadingId === r.id ? '...' : 'Download' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Prepare FORM S03/UDISE" @close="drawerOpen = false">
            <template v-if="active">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    FOR and Existing details are loaded from ERP for <strong class="text-slate-700 dark:text-slate-200">{{ active.name }}</strong> and cannot be edited.
                    Change <em>Updated</em> for the corrected values on the form.
                </p>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">FOR</p>
                    <div class="mt-2 space-y-3">
                        <div>
                            <label class="form-label">Student's PEN</label>
                            <input v-model="form.student_pen" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="form.for_name" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Mobile Number</label>
                            <input v-model="form.mobile" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Existing Details in UDISE Plus</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="form.existing_name" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Date of Birth</label>
                            <input v-model="form.existing_dob" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly placeholder="DD/MM/YYYY" />
                        </div>
                        <div>
                            <label class="form-label">Gender</label>
                            <input v-model="form.existing_gender" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">AADHAAR Number</label>
                            <input v-model="form.existing_aadhaar" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Name as per AADHAAR</label>
                            <input v-model="form.existing_name_as_per_aadhaar" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Class &amp; Section</label>
                            <input v-model="form.existing_class_section" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Mother's Name</label>
                            <input v-model="form.existing_mother_name" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Father's Name</label>
                            <input v-model="form.existing_father_name" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-primary-200 bg-primary-50/40 p-3 dark:border-primary-500/30 dark:bg-primary-500/10">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Details to be Updated in UDISE Plus</p>
                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="copyExistingToUpdated">
                            Copy from Existing
                        </button>
                    </div>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="form.updated_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Date of Birth</label>
                            <input v-model="form.updated_dob" type="text" class="form-input" placeholder="DD/MM/YYYY" />
                        </div>
                        <div>
                            <label class="form-label">Gender</label>
                            <input v-model="form.updated_gender" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">AADHAAR Number</label>
                            <input v-model="form.updated_aadhaar" type="text" class="form-input" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Name as per AADHAAR</label>
                            <input v-model="form.updated_name_as_per_aadhaar" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Class &amp; Section</label>
                            <input v-model="form.updated_class_section" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Mother's Name</label>
                            <input v-model="form.updated_mother_name" type="text" class="form-input" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Father's Name</label>
                            <input v-model="form.updated_father_name" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Documents attached</p>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="form.attach_school_register" type="checkbox" class="rounded border-slate-300" />
                        Copy of School Register
                    </label>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="form.attach_birth_certificate" type="checkbox" class="rounded border-slate-300" />
                        Copy of Birth Certificate
                    </label>
                </div>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Undertaking</p>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="form.undertaking_school" type="checkbox" class="rounded border-slate-300" />
                        Undertaking by School
                    </label>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="form.undertaking_officer" type="checkbox" class="rounded border-slate-300" />
                        Undertaking by Block/ District level Officer
                    </label>
                </div>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Head of the School</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="form.principal_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Designation</label>
                            <input v-model="form.principal_designation" type="text" class="form-input" />
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="!active || downloadingId === active?.id" @click="downloadPrepared">
                    {{ downloadingId === active?.id ? 'Opening...' : 'Download FORM S03' }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const rows = ref([]);
const selectedIds = ref(new Set());
const downloadingId = ref(null);
const zipping = ref(false);
const drawerOpen = ref(false);
const active = ref(null);
const classes = ref([]);
const sections = ref([]);
const branches = ref([]);

const filterValues = reactive({
    search: '',
    branch_id: null,
    school_class_id: null,
    section_id: null,
    status: null,
});

const form = reactive({
    student_pen: '',
    for_name: '',
    mobile: '',
    existing_name: '',
    existing_dob: '',
    existing_gender: '',
    existing_aadhaar: '',
    existing_name_as_per_aadhaar: '',
    existing_class_section: '',
    existing_mother_name: '',
    existing_father_name: '',
    updated_name: '',
    updated_dob: '',
    updated_gender: '',
    updated_aadhaar: '',
    updated_name_as_per_aadhaar: '',
    updated_class_section: '',
    updated_mother_name: '',
    updated_father_name: '',
    attach_school_register: true,
    attach_birth_certificate: false,
    undertaking_school: true,
    undertaking_officer: false,
    principal_name: '',
    principal_designation: 'PRINCIPAL',
});

const sectionsForClass = computed(() =>
    sections.value.filter((s) => !filterValues.school_class_id || s.school_class_id === filterValues.school_class_id),
);

const withPen = computed(() => rows.value.filter((r) => r.student_pen).length);
const allSelected = computed(() => rows.value.length > 0 && rows.value.every((r) => selectedIds.value.has(r.id)));

function resetFilters() {
    Object.assign(filterValues, { search: '', branch_id: null, school_class_id: null, section_id: null, status: null });
}

function toggleAll(checked) {
    if (checked) rows.value.forEach((r) => selectedIds.value.add(r.id));
    else selectedIds.value.clear();
    selectedIds.value = new Set(selectedIds.value);
}

function toggleOne(id, checked) {
    if (checked) selectedIds.value.add(id);
    else selectedIds.value.delete(id);
    selectedIds.value = new Set(selectedIds.value);
}

function maskAadhaar(value) {
    if (!value) return '—';
    const digits = String(value).replace(/\D/g, '');
    if (digits.length < 4) return value;
    return `${'X'.repeat(Math.max(0, digits.length - 4))}${digits.slice(-4)}`;
}

/** Format ERP DOB (Y-m-d or ISO) as DD/MM/YYYY for the S03 form. */
function formatDobDisplay(value) {
    if (!value) return '';
    const s = String(value).slice(0, 10);
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if (m) return `${m[3]}/${m[2]}/${m[1]}`;
    return String(value);
}

function classSectionLabel(row) {
    const cls = row.school_class_name || '';
    const sec = row.section_name || '';
    if (cls && sec) return `${cls} - ${sec}`;
    return cls || sec || '';
}

/** Snapshot of student fields used on both S03 columns. */
function detailSnapshot(row) {
    return {
        name: row.name || '',
        dob: formatDobDisplay(row.dob),
        gender: row.gender || '',
        aadhaar: row.aadhar_no || '',
        name_as_per_aadhaar: row.name_as_per_aadhaar || row.name || '',
        class_section: classSectionLabel(row),
        mother_name: row.mother_name || '',
        father_name: row.father_name || '',
    };
}

function applySnapshotTo(prefix, snap) {
    form[`${prefix}_name`] = snap.name;
    form[`${prefix}_dob`] = snap.dob;
    form[`${prefix}_gender`] = snap.gender;
    form[`${prefix}_aadhaar`] = snap.aadhaar;
    form[`${prefix}_name_as_per_aadhaar`] = snap.name_as_per_aadhaar;
    form[`${prefix}_class_section`] = snap.class_section;
    form[`${prefix}_mother_name`] = snap.mother_name;
    form[`${prefix}_father_name`] = snap.father_name;
}

function readSnapshotFrom(prefix) {
    return {
        name: form[`${prefix}_name`] || '',
        dob: form[`${prefix}_dob`] || '',
        gender: form[`${prefix}_gender`] || '',
        aadhaar: form[`${prefix}_aadhaar`] || '',
        name_as_per_aadhaar: form[`${prefix}_name_as_per_aadhaar`] || '',
        class_section: form[`${prefix}_class_section`] || '',
        mother_name: form[`${prefix}_mother_name`] || '',
        father_name: form[`${prefix}_father_name`] || '',
    };
}

function copyExistingToUpdated() {
    applySnapshotTo('updated', readSnapshotFrom('existing'));
}

async function loadLookups() {
    const data = await fetchAcademicsLookups();
    classes.value = data.classes || [];
    sections.value = data.sections || [];
    branches.value = data.branches || [];
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (filterValues.search) params.search = filterValues.search;
        if (filterValues.branch_id) params.branch_id = filterValues.branch_id;
        if (filterValues.school_class_id) params.school_class_id = filterValues.school_class_id;
        if (filterValues.section_id) params.section_id = filterValues.section_id;
        if (filterValues.status) params.status = filterValues.status;
        const { data } = await client.get('/people/udise-plus', { params });
        rows.value = data;
        selectedIds.value = new Set();
    } finally {
        loading.value = false;
    }
}

function openForm(row) {
    active.value = row;
    const snap = detailSnapshot(row);
    Object.assign(form, {
        student_pen: row.student_pen || '',
        for_name: row.name || '',
        mobile: row.mobile || '',
        attach_school_register: true,
        attach_birth_certificate: false,
        undertaking_school: true,
        undertaking_officer: false,
        principal_name: form.principal_name,
        principal_designation: form.principal_designation || 'PRINCIPAL',
    });
    applySnapshotTo('existing', snap);
    applySnapshotTo('updated', snap);
    drawerOpen.value = true;
}

async function quickDownload(row) {
    downloadingId.value = row.id;
    try {
        await downloadPdf(`/people/udise-plus/${row.id}/s03-pdf`, `udise-s03-${row.admission_no || row.id}.pdf`);
    } catch {
        pushToast('Could not open FORM S03 PDF.', 'error');
    } finally {
        downloadingId.value = null;
    }
}

async function downloadPrepared() {
    if (!active.value) return;
    downloadingId.value = active.value.id;
    try {
        const params = { ...form };
        await downloadPdf(
            `/people/udise-plus/${active.value.id}/s03-pdf`,
            `udise-s03-${active.value.admission_no || active.value.id}.pdf`,
            params,
        );
        drawerOpen.value = false;
    } catch {
        pushToast('Could not open FORM S03 PDF.', 'error');
    } finally {
        downloadingId.value = null;
    }
}

async function downloadZip() {
    if (!selectedIds.value.size) return;
    zipping.value = true;
    try {
        const response = await client.get('/people/udise-plus/zip', {
            params: { student_ids: [...selectedIds.value] },
            responseType: 'blob',
            paramsSerializer: {
                serialize: (params) => {
                    const q = new URLSearchParams();
                    (params.student_ids || []).forEach((id) => q.append('student_ids[]', id));
                    return q.toString();
                },
            },
        });
        triggerBlobDownload(new Blob([response.data], { type: 'application/zip' }), 'udise-s03-forms.zip');
    } catch {
        pushToast('Could not generate S03 ZIP.', 'error');
    } finally {
        zipping.value = false;
    }
}

watch(() => filterValues.school_class_id, () => {
    filterValues.section_id = null;
});

let loadTimer;
watch(filterValues, () => {
    clearTimeout(loadTimer);
    loadTimer = setTimeout(load, 250);
}, { deep: true });

onMounted(async () => {
    await loadLookups();
    await load();
});
</script>
