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
                <span class="ml-auto text-[11px] text-slate-400">{{ selectedColumns.length }} of {{ COLUMNS.length }}</span>
            </div>
            <div class="space-y-0.5 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <label
                    v-for="(col, index) in COLUMNS"
                    :key="col.key"
                    class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <input v-model="selectedColumns" type="checkbox" :value="col.key" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                    <span class="tabular-nums text-slate-400">{{ index + 1 }}.</span>
                    <span>{{ col.label }}</span>
                </label>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Only students marked <strong class="font-medium text-slate-600 dark:text-slate-300">In UDISE</strong> are included.</p>
        </div>

        <div>
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Format</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport('xlsx')">📊 Excel (.xlsx)</button>
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport('csv')">📄 CSV</button>
            </div>
            <p v-if="!canExport" class="mt-1 text-[11px] text-rose-500">Select at least one academic session and one column.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import client from '../../api/client';
import { erpStore } from '../../store';

/** Fixed column order for Student UDISE export (Excel headers = keys). */
const COLUMNS = [
    { key: 'Student Name', label: 'Student Name' },
    { key: 'Gender', label: 'Gender' },
    { key: 'Date of Birth', label: 'Date of Birth' },
    { key: 'Class & Section', label: 'Class & Section' },
    { key: 'Admission Date in Present School', label: 'Admission Date in Present School' },
    { key: "Mother's Name", label: "Mother's Name" },
    { key: "Father's Name", label: "Father's Name" },
    { key: 'Mobile Number', label: 'Mobile Number' },
    { key: 'AADHAAR Number of Student', label: 'AADHAAR Number of Student' },
    { key: "Student's Name (as Per Record)", label: "Student's Name (as Per Record)" },
];

const ALL_KEYS = COLUMNS.map((c) => c.key);

defineProps({
    exporting: { type: Boolean, default: false },
});

const emit = defineEmits(['export']);

const status = ref('All');
const sessionOptions = ref([]);
const selectedSessions = ref([]);
const selectedColumns = ref([...ALL_KEYS]);

const allSessionsSelected = computed(
    () => sessionOptions.value.length > 0 && selectedSessions.value.length === sessionOptions.value.length,
);

const canExport = computed(
    () => selectedColumns.value.length > 0 && (selectedSessions.value.length > 0 || sessionOptions.value.length === 0),
);

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
    selectedColumns.value = [];
}

function emitExport(format) {
    const ordered = ALL_KEYS.filter((k) => selectedColumns.value.includes(k));
    emit('export', {
        format,
        status: status.value,
        sessions: allSessionsSelected.value ? ['All'] : [...selectedSessions.value],
        columns: ordered,
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
