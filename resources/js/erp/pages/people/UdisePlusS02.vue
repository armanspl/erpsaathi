<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">UDISE+ S02</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'UDISE+ S02']" class="mt-1" />
                <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                    Students <strong class="font-medium text-slate-700 dark:text-slate-200">not in UDISE</strong>.
                    Prepare and download <strong class="font-medium text-slate-700 dark:text-slate-200">FORM S02/UDISE</strong>
                    — Format to ADD Student (Class 2–12).
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="btn-primary"
                    :disabled="!selectedIds.size"
                    @click="openSelectedPrepare"
                >
                    Prepare ({{ selectedIds.size }})
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            Only students without <strong>In UDISE</strong> appear here. Set District / Block under Settings → School Settings for the form header.
            Select multiple students and click Prepare — the PDF includes every selected student (S.No 1, 2, 3…).
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <input v-model="filterValues.search" type="text" placeholder="Search name, admission no..." class="form-input" />
                </div>
                <select v-model="filterValues.branch_id" class="form-input">
                    <option :value="null">Branch — All</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="filterValues.school_class_id" class="form-input">
                    <option :value="null">Class — All</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filterValues.section_id" class="form-input" :disabled="!filterValues.school_class_id">
                    <option :value="null">Section — All</option>
                    <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="filterValues.status" class="form-input">
                    <option value="Active">Active</option>
                    <option :value="null">Status — All</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Transferred">Transferred</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Not in UDISE" :value="rows.length" color="amber" icon="🎓" />
            <StatCard label="Selected" :value="selectedIds.size" color="sky" icon="☑" />
            <StatCard label="With Aadhaar" :value="withAadhaar" color="emerald" icon="🪪" />
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
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">DOB</th>
                        <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Aadhaar</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">FORM S02</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No students missing from UDISE match your filters.</td>
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
                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ classLabel(r) }}</td>
                        <td class="px-3 py-3 text-slate-500">{{ r.gender || '—' }}</td>
                        <td class="px-3 py-3 text-slate-500">{{ formatDate(r.dob) }}</td>
                        <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ r.aadhar_no || '—' }}</td>
                        <td class="px-3 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="openForm([r])">Prepare</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Prepare FORM S02/UDISE" @close="drawerOpen = false">
            <template v-if="preparedRows.length">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <strong class="text-slate-700 dark:text-slate-200">{{ preparedRows.length }}</strong> student(s) will appear on the form
                    (S.No 1–{{ preparedRows.length }}). Review details and optional reasons, then download.
                </p>

                <div
                    v-for="(r, idx) in preparedRows"
                    :key="r.id"
                    class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">S.No {{ idx + 1 }}</p>
                    <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
                        {{ r.name }}
                        <span class="font-mono text-xs text-slate-400">{{ r.admission_no }}</span>
                    </p>
                    <p class="text-xs text-slate-400">{{ classLabel(r) }} · {{ r.gender || '—' }} · DOB {{ formatDate(r.dob) }}</p>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Father</label>
                            <input :value="r.father_name || ''" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Mother</label>
                            <input :value="r.mother_name || ''" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Mobile</label>
                            <input :value="r.mobile || ''" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div>
                            <label class="form-label">Aadhaar</label>
                            <input :value="r.aadhar_no || ''" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Name as per Aadhaar</label>
                            <input :value="r.name_as_per_aadhaar || r.name || ''" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                        </div>
                    </div>

                    <label class="form-label mt-3">Why not added in Previous Academic Year?</label>
                    <textarea v-model="reasons[r.id]" rows="2" class="form-input" placeholder="Optional reason..." />
                </div>

                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Head of the School</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="principalName" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Designation</label>
                            <input v-model="principalDesignation" type="text" class="form-input" />
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="!preparedRows.length || downloading" @click="downloadPrepared">
                    {{ downloading ? 'Opening...' : `Download FORM S02 (${preparedRows.length})` }}
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
import { openAndDownloadPdfBlob } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const downloading = ref(false);
const drawerOpen = ref(false);
const preparedRows = ref([]);
const reasons = reactive({});
const principalName = ref('');
const principalDesignation = ref('PRINCIPAL');
const rows = ref([]);
const selectedIds = ref(new Set());
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);

const filterValues = reactive({
    search: '',
    branch_id: null,
    school_class_id: null,
    section_id: null,
    status: 'Active',
});

const sectionsForClass = computed(() =>
    sections.value.filter((s) => !filterValues.school_class_id || s.school_class_id === filterValues.school_class_id),
);
const withAadhaar = computed(() => rows.value.filter((r) => r.aadhar_no).length);
const allSelected = computed(() => rows.value.length > 0 && rows.value.every((r) => selectedIds.value.has(r.id)));

function classLabel(r) {
    const parts = [r.school_class_name, r.section_name].filter(Boolean);
    return parts.length ? parts.join(' — ') : '—';
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function toggleAll(checked) {
    selectedIds.value = checked ? new Set(rows.value.map((r) => r.id)) : new Set();
}

function toggleOne(id, checked) {
    const next = new Set(selectedIds.value);
    if (checked) next.add(id);
    else next.delete(id);
    selectedIds.value = next;
}

function openForm(list) {
    preparedRows.value = list;
    list.forEach((r) => {
        if (reasons[r.id] === undefined) reasons[r.id] = '';
    });
    drawerOpen.value = true;
}

function openSelectedPrepare() {
    if (!selectedIds.value.size) return;
    const list = rows.value.filter((r) => selectedIds.value.has(r.id));
    openForm(list);
}

async function downloadForm(ids, reasonMap = {}) {
    downloading.value = true;
    const tab = window.open('about:blank', '_blank');
    try {
        const { data } = await client.post(
            '/people/udise-plus-s02/pdf',
            {
                student_ids: ids,
                reasons: reasonMap,
                principal_name: principalName.value || undefined,
                principal_designation: principalDesignation.value || undefined,
            },
            { responseType: 'blob' },
        );
        openAndDownloadPdfBlob(data, 'udise-s02-form.pdf', tab);
        pushToast(`FORM S02 PDF ready (${ids.length} student${ids.length === 1 ? '' : 's'}).`, 'success');
        drawerOpen.value = false;
    } catch {
        if (tab && !tab.closed) tab.close();
        pushToast('Could not open FORM S02 PDF.', 'error');
    } finally {
        downloading.value = false;
    }
}

function downloadPrepared() {
    if (!preparedRows.value.length) return;
    const ids = preparedRows.value.map((r) => r.id);
    const reasonMap = {};
    ids.forEach((id) => {
        if (reasons[id]) reasonMap[id] = reasons[id];
    });
    downloadForm(ids, reasonMap);
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
        const { data } = await client.get('/people/udise-plus-s02', { params });
        rows.value = data;
        selectedIds.value = new Set();
    } finally {
        loading.value = false;
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
