<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ activeTab.label }}</h1>
                <Breadcrumb :items="['Dashboard', 'Reports', activeTab.label]" class="mt-1" />
            </div>
            <Dropdown align="right">
                <template #trigger>
                    <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting">
                        {{ exporting ? 'Exporting…' : 'Export ▾' }}
                    </button>
                </template>
                <template #panel="{ close }">
                    <div class="w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportReport('pdf'); close()">📕 PDF</button>
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportReport('xlsx'); close()">📊 Excel (.xlsx)</button>
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportReport('csv'); close()">📄 CSV</button>
                    </div>
                </template>
            </Dropdown>
        </div>

        <!-- Tabs -->
        <nav class="flex flex-wrap gap-6 border-b border-slate-200 dark:border-slate-800">
            <RouterLink
                v-for="tab in tabs"
                :key="tab.id"
                :to="tab.path"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="tab.id === activeTab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600'"
            >
                {{ tab.label }}
                <span v-if="tab.id === activeTab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </RouterLink>
        </nav>

        <!-- Filters (class-wise / area-wise) -->
        <div v-if="activeTab.id === 'class-wise' || activeTab.id === 'area-wise'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <template v-if="activeTab.id === 'class-wise'">
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" @change="filters.section_id = null">
                            <option :value="null">All classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input">
                            <option :value="null">All sections</option>
                            <option v-for="s in filterSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission" />
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission" />
                    </div>
                    <div class="flex items-end text-xs text-slate-400">
                        {{ areaOptions.length }} distinct address(es) · {{ areaTotalStudents }} student(s)
                    </div>
                </template>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading…</div>

        <!-- CLASS WISE -->
        <template v-else-if="activeTab.id === 'class-wise'">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Students</h2>
                    <span class="text-xs text-slate-400">{{ classRows.length }} row(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th v-for="h in ['Adm No', 'Name', 'Class', 'Roll', 'Father Name', 'Mobile']" :key="h" class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!classRows.length"><td colspan="6" class="px-4 py-12 text-center text-slate-400">No students match these filters.</td></tr>
                            <tr v-for="r in pagedClassRows" :key="r.admission_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                                <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ [r.school_class, r.section].filter(Boolean).join(' / ') || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.roll_no || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.father || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.mobile || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-if="classRows.length > perPage" v-model="classPage" :per-page="perPage" :total="classRows.length" />
            </div>
        </template>

        <!-- AREA WISE -->
        <template v-else-if="activeTab.id === 'area-wise'">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Addresses</h2>
                    <p class="text-xs text-slate-400">Grouped by area, no duplicates. Click an address to see its students.</p>
                </div>
                <div v-if="!areaOptions.length" class="px-4 py-8 text-center text-sm text-slate-400">No addresses on file.</div>
                <ul v-else class="max-h-72 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    <li v-for="a in areaOptions" :key="a.value">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            :class="filters.area === a.value ? 'bg-primary-50 dark:bg-primary-500/10' : ''"
                            @click="filters.area = a.value"
                        >
                            <span class="font-medium" :class="filters.area === a.value ? 'text-primary-700 dark:text-primary-300' : 'text-slate-700 dark:text-slate-200'">{{ a.label }}</span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ a.count }} student(s)</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div v-if="!filters.area" class="rounded-2xl border border-dashed border-slate-300 px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-700">
                Click an address above to see its students.
            </div>
            <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ selectedAreaLabel }}</h2>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">{{ areaRows.length }} student(s) from this address</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th v-for="h in ['Adm No', 'Name', 'Father Name', 'Mobile', 'Vehicle']" :key="h" class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!areaRows.length"><td colspan="5" class="px-4 py-12 text-center text-slate-400">No students at this address.</td></tr>
                            <tr v-for="r in pagedAreaRows" :key="r.admission_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                                <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.father || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.mobile || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ r.vehicle || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-if="areaRows.length > perPage" v-model="areaPage" :per-page="perPage" :total="areaRows.length" />
            </div>
        </template>

        <!-- FATHER WISE -->
        <template v-else-if="activeTab.id === 'father-wise'">
            <p class="text-xs text-slate-400">Only guardians with two or more children currently studying are listed. Due is the automatic outstanding balance up to the current month for the selected session.</p>
            <div v-if="!fathers.length" class="rounded-2xl border border-dashed border-slate-300 px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-700">
                No guardian has two or more enrolled children.
            </div>
            <div v-for="f in pagedFathers" :key="f.father + f.phone" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ f.father }}</h2>
                        <p class="text-xs text-slate-400"><span v-if="f.phone">{{ f.phone }} · </span>{{ f.children_count }} children</p>
                    </div>
                    <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">Total due ₹{{ money(f.total_due) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th v-for="h in ['Adm No', 'Name', 'Class', 'Dues']" :key="h" class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="c in f.children" :key="c.admission_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ c.admission_no }}</td>
                                <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ c.name }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ c.school_class || '—' }}</td>
                                <td class="px-3 py-2.5 font-semibold" :class="c.due > 0 ? 'text-teal-600' : 'text-slate-400'">₹{{ money(c.due) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination v-if="fathers.length > cardsPerPage" v-model="fatherPage" :per-page="cardsPerPage" :total="fathers.length" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" />
        </template>

        <!-- VEHICLE WISE -->
        <template v-else>
            <div v-if="!vehicles.length" class="rounded-2xl border border-dashed border-slate-300 px-4 py-16 text-center text-sm text-slate-400 dark:border-slate-700">
                No vehicles found.
            </div>
            <div v-for="v in pagedVehicles" :key="v.vehicle_no" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ v.vehicle_no }}<span v-if="v.type" class="ml-1 font-normal text-slate-400">· {{ v.type }}</span></h2>
                        <p class="text-xs text-slate-400"><span v-if="v.driver">{{ v.driver }}<span v-if="v.driver_phone"> · {{ v.driver_phone }}</span> · </span>{{ v.trip_count }} trip(s) · {{ v.student_count }} student(s)</p>
                    </div>
                </div>
                <div v-if="!v.trips.length" class="px-4 py-6 text-center text-xs text-slate-400">No routes assigned to this vehicle.</div>
                <div v-for="t in v.trips" :key="t.trip_no" class="border-t border-slate-100 first:border-t-0 dark:border-slate-800">
                    <div class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                        Trip {{ t.trip_no }} — {{ t.route }}<span v-if="t.route_code" class="text-slate-400"> ({{ t.route_code }})</span> · {{ t.student_count }} student(s)
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                <tr>
                                    <th v-for="h in ['Adm No', 'Name', 'Class', 'Stop']" :key="h" class="px-3 py-2.5 text-xs font-semibold uppercase text-slate-500">{{ h }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-if="!t.students.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">No students on this trip.</td></tr>
                                <tr v-for="s in t.students" :key="s.admission_no" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ s.admission_no }}</td>
                                    <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ s.school_class || '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ s.stop || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <Pagination v-if="vehicles.length > cardsPerPage" v-model="vehiclePage" :per-page="cardsPerPage" :total="vehicles.length" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" />
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import Dropdown from '../../components/common/Dropdown.vue';
import Pagination from '../../components/common/Pagination.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'class-wise', label: 'Class Wise', path: '/reports/class-wise', endpoint: '/reports/register/class-wise' },
    { id: 'area-wise', label: 'Area Wise', path: '/reports/area-wise', endpoint: '/reports/register/area-wise' },
    { id: 'father-wise', label: 'Father Wise', path: '/reports/father-wise', endpoint: '/reports/register/father-wise' },
    { id: 'vehicle-wise', label: 'Vehicle Wise', path: '/reports/vehicle-wise', endpoint: '/reports/register/vehicle-wise' },
];

const route = useRoute();
const activeTab = computed(() => tabs.find((t) => t.path === route.path) || tabs[0]);

const loading = ref(false);
const exporting = ref(false);

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);

const filters = reactive({ branch_id: null, school_class_id: null, section_id: null, search: '', area: null });

const classRows = ref([]);
const areaOptions = ref([]);
const areaRows = ref([]);
const fathers = ref([]);
const vehicles = ref([]);

const perPage = 20;
const cardsPerPage = 10;
const classPage = ref(1);
const areaPage = ref(1);
const fatherPage = ref(1);
const vehiclePage = ref(1);

const slice = (arr, page, size) => arr.slice((page - 1) * size, page * size);
const pagedClassRows = computed(() => slice(classRows.value, classPage.value, perPage));
const pagedAreaRows = computed(() => slice(areaRows.value, areaPage.value, perPage));
const pagedFathers = computed(() => slice(fathers.value, fatherPage.value, cardsPerPage));
const pagedVehicles = computed(() => slice(vehicles.value, vehiclePage.value, cardsPerPage));

const filterSections = computed(() =>
    filters.school_class_id
        ? sections.value.filter((s) => s.school_class_id === filters.school_class_id)
        : sections.value,
);
const selectedAreaLabel = computed(() => areaOptions.value.find((a) => a.value === filters.area)?.label || 'Selected address');
const areaTotalStudents = computed(() => areaOptions.value.reduce((sum, a) => sum + (a.count || 0), 0));

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function resetPages() {
    classPage.value = 1;
    areaPage.value = 1;
    fatherPage.value = 1;
    vehiclePage.value = 1;
}

function currentParams() {
    const p = {};
    if (activeTab.value.id === 'class-wise' || activeTab.value.id === 'area-wise') {
        if (filters.branch_id) p.branch_id = filters.branch_id;
        if (filters.school_class_id) p.school_class_id = filters.school_class_id;
        if (filters.section_id) p.section_id = filters.section_id;
        if (filters.search.trim()) p.search = filters.search.trim();
    }
    if (activeTab.value.id === 'area-wise' && filters.area) p.area = filters.area;
    return p;
}

let reloadTimer = null;
function scheduleReload(delay = 0) {
    if (reloadTimer) clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        reload();
    }, delay);
}

async function reload() {
    const tab = activeTab.value;
    loading.value = true;
    try {
        const { data } = await client.get(tab.endpoint, { params: currentParams() });
        if (tab.id === 'class-wise') {
            classRows.value = data.rows || [];
            classPage.value = 1;
        } else if (tab.id === 'area-wise') {
            areaOptions.value = data.areas || [];
            areaRows.value = data.rows || [];
            areaPage.value = 1;
        } else if (tab.id === 'father-wise') {
            fathers.value = data.fathers || [];
            fatherPage.value = 1;
        } else {
            vehicles.value = data.vehicles || [];
            vehiclePage.value = 1;
        }
    } finally {
        loading.value = false;
    }
}

async function exportReport(format) {
    if (activeTab.value.id === 'area-wise' && !filters.area) {
        pushToast('Select an address first.', 'error');
        return;
    }
    exporting.value = true;
    try {
        const response = await client.get(activeTab.value.endpoint, {
            params: { ...currentParams(), format },
            responseType: 'blob',
        });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : `report-${activeTab.value.id}.${format}`;

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

fetchAcademicsLookups().then((lookups) => {
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
});

watch(() => route.path, () => {
    resetPages();
    reload();
}, { immediate: true });

watch(() => [filters.branch_id, filters.school_class_id, filters.section_id], () => {
    filters.area = null;
    resetPages();
    scheduleReload(0);
});

watch(() => filters.search, () => {
    resetPages();
    scheduleReload(300);
});

watch(() => filters.area, () => {
    areaPage.value = 1;
    scheduleReload(0);
});

watch(() => erpStore.currentSession, () => scheduleReload(0));
</script>
