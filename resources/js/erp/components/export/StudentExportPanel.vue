<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Status</p>
                <select v-model="status" class="form-input">
                    <option value="All">All</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Academic Sessions</p>
                    <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400" @click="toggleAllSessions">
                        {{ allSessionsSelected ? 'Clear' : 'All Sessions' }}
                    </button>
                </div>
                <div class="max-h-24 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                    <p v-if="!sessionOptions.length" class="px-1 py-2 text-xs text-slate-400">Loading sessions...</p>
                    <label
                        v-for="name in sessionOptions"
                        :key="name"
                        class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <input v-model="selectedSessions" type="checkbox" :value="name" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        <span>{{ name }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Columns</p>
                <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="selectAllColumns">Select all</button>
                <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="deselectAllColumns">Deselect all</button>
                <span class="ml-auto text-[11px] text-slate-400">{{ selectedColumns.length }} of {{ ALL_KEYS.length }}</span>
            </div>
            <input v-model="columnSearch" type="search" class="form-input mb-3" placeholder="Search fields…" />
            <div class="max-h-[42vh] space-y-4 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <div v-for="group in filteredGroups" :key="group.label">
                    <div class="mb-1.5 flex items-center justify-between">
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ group.label }}</p>
                        <button type="button" class="text-[11px] font-medium text-primary-600 hover:underline" @click="toggleGroup(group)">
                            {{ groupAllSelected(group) ? 'Clear group' : 'Select group' }}
                        </button>
                    </div>
                    <div class="grid gap-0.5 sm:grid-cols-2">
                        <label
                            v-for="col in group.columns"
                            :key="col.key"
                            class="flex items-start gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 dark:text-slate-300"
                            :class="col.mandatory ? 'opacity-90' : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800'"
                        >
                            <input
                                v-model="selectedColumns"
                                type="checkbox"
                                :value="col.key"
                                :disabled="col.mandatory"
                                class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <span>{{ col.label }}</span>
                            <span v-if="col.mandatory" class="ml-auto shrink-0 text-[10px] font-medium uppercase tracking-wide text-slate-400">Required</span>
                        </label>
                    </div>
                </div>
                <p v-if="!filteredGroups.length" class="py-6 text-center text-sm text-slate-400">No fields match your search.</p>
            </div>
        </div>

        <div>
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Format</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport('xlsx')">📊 Excel (.xlsx)</button>
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport('csv')">📄 CSV</button>
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport('pdf')">📕 PDF</button>
            </div>
            <p v-if="!canExport" class="mt-1 text-[11px] text-rose-500">Select at least one academic session.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import client from '../../api/client';
import { erpStore } from '../../store';

/**
 * key = exact Master Export header (backend column name).
 * label = UI name from the Student Master / UDISE field list.
 */
const COLUMN_GROUPS = [
    {
        label: 'Basic Student Information',
        columns: [
            { key: 'Name', label: 'Student Name', mandatory: true },
            { key: 'Class & Section', label: 'Class & Section' },
            { key: 'SESSION', label: 'Academic Year' },
            { key: 'Student PEN', label: 'Permanent Education Number (PEN)' },
            { key: 'Aadhaar Status', label: 'Aadhaar Status' },
            { key: 'Gender', label: 'Gender' },
            { key: 'DOB', label: 'Date of Birth' },
            { key: 'Mother Name', label: "Mother's Name" },
            { key: 'Father Name', label: "Father's Name" },
            { key: 'Guardian Name (Optional)', label: "Guardian's Name" },
            { key: 'AADHAAR No.', label: 'AADHAAR Number of Student' },
            { key: 'Name As per AADHAAR', label: 'Name as per AADHAAR' },
            { key: 'ADDRESS', label: 'Address' },
            { key: 'PINCODE', label: 'Pincode' },
            { key: 'MOBILE', label: 'Mobile Number' },
            { key: 'ALTERNATE MOBILE NUMBER (Optional)', label: 'Alternate Mobile Number' },
            { key: 'EMAIL ID (STUDENT/PARENT/GUARDIAN) (Optional)', label: 'Contact email-id' },
            { key: 'MOTHER TONGUE', label: 'Mother Tongue' },
            { key: 'Social Category', label: 'Social Category' },
            { key: 'Minority Group', label: 'Minority Group' },
            { key: 'BPL beneficiary', label: 'Whether BPL beneficiary' },
            { key: 'Whether Antyodaya Anna Yojana (AAY) beneficiary?', label: 'Whether Antyodaya Anna Yojana (AAY)' },
            { key: 'BELONGS TO EWS/DISADVANTAGED GROUP?', label: 'Whether belongs to EWS / Disadvantaged Group' },
            { key: 'CWSN', label: 'Whether CWSN' },
            { key: 'Type of Impairments', label: 'Type of Impairments' },
            { key: 'CHILD IS INDIAN NATIONAL?', label: 'Indian Nationality' },
            { key: 'Is Child Identified as Out of School-Child', label: 'Is Child Identified as Out of School-Child' },
            { key: 'When the Child is mainstreamed', label: 'When the Child is mainstreamed' },
            { key: 'Whether having Disability Certificate?', label: 'Whether having Disability Certificate?' },
            { key: 'Disability Percentage', label: 'Disability Percentage' },
            { key: 'Bld Grp', label: 'Blood Group' },
        ],
    },
    {
        label: 'Admission / Academic Information',
        columns: [
            { key: 'Adm No.', label: 'Admission Number in Present School', mandatory: true },
            { key: 'ADM DATE', label: 'Admission Date in Present School' },
            { key: 'Class/Section Roll No', label: 'Class/Section Roll No' },
            { key: 'Medium of Instruction', label: 'Medium of Instruction' },
            { key: 'Languages Group Studied', label: 'Languages Group Studied' },
            { key: 'Academic Stream opted', label: 'Academic Stream opted' },
            { key: 'Subjects Group Studied', label: 'Subjects Group Studied' },
            { key: 'PREVIOUS ACADEMIC YEAR SCHOOLING STATUS', label: 'Status in Previous Academic Year' },
            { key: 'CLASS STUDIES IN PREVIOUS ACADEMIC YEAR', label: 'Grade/Class Studied in Previous Year' },
            { key: 'ADMITED/ ENROLLED UNDER RTE/EWS? (For Private Unaided only)', label: 'Whether Admitted under Section 12C of RTE Act?' },
            { key: 'Amount Claimed from Government for RTE', label: 'Amount Claimed from Government for RTE' },
            { key: 'RESULT FOR PREVIOUS EXAM', label: 'Examination Result in Previous Class' },
            { key: 'MARKS % OF PREVIOUS EXAM', label: 'Marks Obtained (%)' },
            { key: 'CLASS ATTENDED DAYS (PREVIOUS YEAR)', label: 'No. of days child attended school (Prev. Year)' },
        ],
    },
    {
        label: 'Facilities / Other Information',
        columns: [
            { key: 'Whether Facilities provided to Student', label: 'Whether Facilities provided to Student' },
            { key: 'Facilities provided in case of CWSN', label: 'Facilities provided in case of CWSN' },
            { key: 'Appeared in State/National Competitions/Olympiads', label: 'Appeared in State/National Competitions/Olympiads' },
            { key: 'NCC', label: 'NCC' },
            { key: 'NSS', label: 'NSS' },
            { key: 'Scouts and Guides', label: 'Scouts and Guides' },
            { key: "Student's Height (in CMs)", label: "Student's Height (in CMs)" },
            { key: "Student's Weight (in KGs)", label: "Student's Weight (in KGs)" },
            { key: 'Approximate Distance of residence to school', label: 'Approximate Distance of residence to school' },
            { key: 'Completed Highest Education Level of Parents', label: 'Completed Highest Education Level of Parents' },
        ],
    },
    {
        label: 'Additional Master Fields',
        columns: [
            { key: 'Class', label: 'Class' },
            { key: 'Section', label: 'Section' },
            { key: 'ROLL', label: 'Roll' },
            { key: 'Student Type', label: 'Student Type' },
            { key: 'Adm Type', label: 'Admission Type' },
            { key: 'Class Admitted', label: 'Class Admitted' },
            { key: 'Transport', label: 'Transport' },
            { key: 'Stoppage', label: 'Stoppage' },
            { key: 'Vehicle', label: 'Vehicle' },
            { key: 'Hostel', label: 'Hostel' },
            { key: 'clsl', label: 'CLSL' },
            { key: 'Student State Code', label: 'Student State Code' },
            { key: 'Is Repeater', label: 'Is Repeater' },
            { key: 'APPEARED FOR EXAM IN PREVIOUS CLASS', label: 'Appeared for Exam in Previous Class' },
            { key: 'C%', label: 'Attendance %' },
            { key: 'Status', label: 'Status' },
            { key: 'TC Number', label: 'TC Number' },
            { key: 'TC Date', label: 'TC Date' },
            { key: 'Last Class Studied', label: 'Last Class Studied' },
            { key: 'STATUS', label: 'Promotion Status' },
            { key: 'UDISE', label: 'UDISE' },
        ],
    },
];

/** Unique keys only — UI may show the same key under two labels; selecting either includes the column once. */
const UNIQUE_COLUMNS = (() => {
    const seen = new Set();
    const list = [];
    for (const group of COLUMN_GROUPS) {
        for (const col of group.columns) {
            if (seen.has(col.key)) continue;
            seen.add(col.key);
            list.push(col);
        }
    }
    return list;
})();

const ALL_KEYS = UNIQUE_COLUMNS.map((c) => c.key);
const MANDATORY_KEYS = UNIQUE_COLUMNS.filter((c) => c.mandatory).map((c) => c.key);

defineProps({
    exporting: { type: Boolean, default: false },
});

const emit = defineEmits(['export']);

const status = ref('All');
const sessionOptions = ref([]);
const selectedSessions = ref([]);
const selectedColumns = ref([...ALL_KEYS]);
const columnSearch = ref('');

const allSessionsSelected = computed(
    () => sessionOptions.value.length > 0 && selectedSessions.value.length === sessionOptions.value.length,
);

const canExport = computed(() => selectedSessions.value.length > 0 || sessionOptions.value.length === 0);

const filteredGroups = computed(() => {
    const q = columnSearch.value.trim().toLowerCase();
    return COLUMN_GROUPS.map((group) => ({
        ...group,
        columns: q
            ? group.columns.filter((c) => c.label.toLowerCase().includes(q) || c.key.toLowerCase().includes(q))
            : group.columns,
    })).filter((g) => g.columns.length);
});

async function loadSessions() {
    let names = (erpStore.sessionRecords || []).map((s) => s.name);
    if (!names.length) {
        const { data } = await client.get('/settings/academic-sessions');
        names = data.map((s) => s.name);
    }
    sessionOptions.value = names;
    selectedSessions.value = [...names];
}

function toggleAllSessions() {
    selectedSessions.value = allSessionsSelected.value ? [] : [...sessionOptions.value];
}

function selectAllColumns() {
    selectedColumns.value = [...ALL_KEYS];
}

function deselectAllColumns() {
    selectedColumns.value = [...MANDATORY_KEYS];
}

function groupAllSelected(group) {
    const keys = [...new Set(group.columns.map((c) => c.key))];
    return keys.every((k) => selectedColumns.value.includes(k));
}

function toggleGroup(group) {
    const keys = [...new Set(group.columns.map((c) => c.key))];
    if (groupAllSelected(group)) {
        selectedColumns.value = selectedColumns.value.filter((k) => !keys.includes(k) || MANDATORY_KEYS.includes(k));
    } else {
        selectedColumns.value = [...new Set([...selectedColumns.value, ...keys])];
    }
}

watch(selectedColumns, (cols) => {
    const missing = MANDATORY_KEYS.filter((k) => !cols.includes(k));
    if (missing.length) {
        selectedColumns.value = [...cols, ...missing];
    }
});

function emitExport(format) {
    const ordered = ALL_KEYS.filter((k) => selectedColumns.value.includes(k));
    emit('export', {
        format,
        status: status.value,
        sessions: allSessionsSelected.value ? ['All'] : [...selectedSessions.value],
        columns: ordered.length === ALL_KEYS.length ? ['All'] : ordered,
    });
}

onMounted(loadSessions);
watch(() => erpStore.sessionRecords?.length, () => {
    if (erpStore.sessionRecords?.length && !sessionOptions.value.length) {
        sessionOptions.value = erpStore.sessionRecords.map((s) => s.name);
        selectedSessions.value = [...sessionOptions.value];
    }
});
</script>
