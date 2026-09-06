<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Exam Seat Planning</h1>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Create Seat Plan
                </button>
            </div>
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">Session year: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ currentSessionName || '—' }}</span></p>

        <div v-if="sheetsLoading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>

        <template v-else>
            <div v-if="sheets.length" class="flex flex-wrap gap-2">
                <button
                    v-for="s in sheets"
                    :key="s.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="selectedSheetId === s.id ? 'bg-primary-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                    @click="selectSheet(s.id)"
                >
                    {{ s.label }}
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="selectedSheetId === s.id ? 'bg-white/20' : 'bg-primary-600 text-white'">live</span>
                </button>
            </div>

            <div v-if="!sheets.length" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                Create a seat plan to assign students to exam rooms. Use Create Seat Plan in the header.
            </div>

            <template v-else-if="sheetDetail">
                <div class="flex flex-wrap gap-3">
                    <button
                        v-for="room in sheetDetail.rooms"
                        :key="room.id"
                        type="button"
                        class="min-w-[120px] rounded-xl border p-3 text-left transition"
                        :class="selectedRoomId === room.id ? 'border-primary-500 ring-1 ring-primary-500' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'"
                        @click="selectedRoomId = room.id"
                    >
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ room.name }}</div>
                        <div class="text-xs text-slate-400">{{ room.assigned }}/{{ sheetDetail.seats_per_room }} assigned</div>
                    </button>
                </div>

                <div v-if="selectedRoom" class="grid gap-5 lg:grid-cols-[1fr_260px]">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 class="inline-flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-slate-100">
                                    {{ selectedRoom.name }}
                                    <span class="rounded-full bg-primary-600 px-2 py-0.5 text-[11px] font-semibold text-white">live</span>
                                </h2>
                                <p class="mt-0.5 text-sm text-slate-500">{{ roomSubtitle }}</p>
                            </div>
                            <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-xs" @click="exportRoom">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                Export
                            </button>
                        </div>

                        <div class="mt-4 rounded-lg border border-slate-200 py-2 text-center text-xs font-semibold uppercase tracking-widest text-slate-400 dark:border-slate-700">Whiteboard</div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full border-separate" style="border-spacing: 0.5rem">
                                <tbody>
                                    <tr v-for="r in roomGrid" :key="r.row_no">
                                        <td class="w-10 align-top pt-3 text-xs font-semibold text-slate-400">R{{ r.row_no }}</td>
                                        <td v-for="bench in r.benches" :key="bench.col_no" class="align-top">
                                            <div class="rounded-lg border p-2" :class="benchClass(bench)">
                                                <div v-for="seat in bench.seats" :key="seat.id" class="text-xs">
                                                    <span class="font-semibold" :class="seat.student ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400'">S{{ seat.seat_no }}</span>
                                                    <div :class="seat.student ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400'">{{ seat.student?.name || 'Unassigned' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-sky-400" />Boys room/seat</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-pink-400" />Girls room/seat</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" />Assigned</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600" />Vacant</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Summary</h3>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500">Rooms</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ sheetDetail.rooms.length }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Assigned</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ sheetDetail.assigned }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Vacant</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ sheetDetail.capacity - sheetDetail.assigned }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Capacity</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ sheetDetail.capacity }}</dd></div>
                            </dl>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">This room</h3>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500">Room</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ selectedRoom.name }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Layout</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ sheetDetail.rows }} x {{ sheetDetail.columns }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Assigned</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ selectedRoom.assigned }}/{{ sheetDetail.seats_per_room }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- Create Seat Plan modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Create Seat Plan</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="form-label">Session year</label>
                        <input :value="currentSessionName" type="text" class="form-input bg-slate-50 dark:bg-slate-800/50" readonly />
                    </div>
                    <div>
                        <label class="form-label">Exam</label>
                        <select v-model="form.exam_id" class="form-input">
                            <option :value="null">Select exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="form.branch_id" class="form-input" @change="form.school_class_id = null; form.section_id = null">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Class</label>
                            <select v-model="form.school_class_id" class="form-input" :disabled="!form.branch_id" @change="form.section_id = null">
                                <option :value="null">{{ form.branch_id ? 'All classes' : 'Select a branch first' }}</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <select v-model="form.section_id" class="form-input" :disabled="!form.school_class_id">
                                <option :value="null">{{ form.school_class_id ? 'All sections' : 'Select a class first' }}</option>
                                <option v-for="s in sectionsForClass(form.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Rows</label>
                            <input v-model.number="form.rows" type="number" min="1" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Columns</label>
                            <input v-model.number="form.columns" type="number" min="1" class="form-input" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Students per bench</label>
                            <input v-model.number="form.students_per_bench" type="number" min="1" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Number of rooms (optional)</label>
                            <input v-model.number="form.room_count" type="number" min="1" class="form-input" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Seats per room = rows Ã— columns Ã— students per bench. Rooms = ceil(students in scope Ã· seats per room) when number of rooms is left empty.</p>

                    <div>
                        <label class="form-label">Fill order</label>
                        <select v-model="form.fill_order" class="form-input">
                            <option value="roll_number">roll_number</option>
                            <option value="admission_no">admission_no</option>
                            <option value="name">name</option>
                        </select>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" v-model="form.separate_by_gender" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                        Separate rooms by gender
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Room prefix</label>
                            <input v-model="form.room_prefix" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Room suffix</label>
                            <input v-model="form.room_suffix" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Creating...' : 'Create plan' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const exams = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const sheets = ref([]);
const sheetsLoading = ref(true);
const currentSessionName = ref('');

const selectedSheetId = ref(null);
const sheetDetail = ref(null);
const selectedRoomId = ref(null);

const formOpen = ref(false);
const saving = ref(false);
const form = reactive({
    exam_id: null,
    branch_id: null,
    school_class_id: null,
    section_id: null,
    rows: 5,
    columns: 4,
    students_per_bench: 1,
    room_count: null,
    fill_order: 'roll_number',
    separate_by_gender: false,
    room_prefix: 'Room',
    room_suffix: '',
});

function sectionsForClass(classId) {
    return sections.value.filter((s) => s.school_class_id === classId);
}

const selectedRoom = computed(() => sheetDetail.value?.rooms.find((r) => r.id === selectedRoomId.value) || null);

const roomGrid = computed(() => {
    if (!selectedRoom.value) return [];
    const byRow = {};
    selectedRoom.value.seats.forEach((seat) => {
        byRow[seat.row_no] ??= {};
        byRow[seat.row_no][seat.col_no] ??= [];
        byRow[seat.row_no][seat.col_no].push(seat);
    });
    return Object.keys(byRow).map(Number).sort((a, b) => a - b).map((rowNo) => ({
        row_no: rowNo,
        benches: Object.keys(byRow[rowNo]).map(Number).sort((a, b) => a - b).map((colNo) => ({
            col_no: colNo,
            seats: byRow[rowNo][colNo].sort((a, b) => a.bench_slot - b.bench_slot),
        })),
    }));
});

const roomSubtitle = computed(() => {
    if (!sheetDetail.value || !selectedRoom.value) return '';
    const parts = [
        sheetDetail.value.exam?.name,
        sheetDetail.value.branch?.name,
        sheetDetail.value.school_class?.name,
        sheetDetail.value.section?.name,
        sheetDetail.value.session_name ? `Session ${sheetDetail.value.session_name}` : null,
    ].filter(Boolean);
    return parts.join(' · ');
});

function benchClass(bench) {
    const allAssigned = bench.seats.every((s) => s.student);
    const anyAssigned = bench.seats.some((s) => s.student);
    if (allAssigned) return 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10';
    if (anyAssigned) return 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10';
    return 'border-slate-200 dark:border-slate-700';
}

async function loadLookups() {
    const [academics, sessionsRes, examsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
        client.get('/exams'),
    ]);
    branches.value = academics.branches || [];
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    exams.value = examsRes.data;
    currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
}

async function loadSheets() {
    sheetsLoading.value = true;
    try {
        await loadLookups();
        const { data } = await client.get('/exams/seat-plans');
        sheets.value = data;
        if (data.length && !selectedSheetId.value) {
            await selectSheet(data[0].id);
        }
    } finally {
        sheetsLoading.value = false;
    }
}

async function selectSheet(id) {
    selectedSheetId.value = id;
    const { data } = await client.get(`/exams/seat-plans/${id}`);
    sheetDetail.value = data;
    selectedRoomId.value = data.rooms[0]?.id || null;
}

function openCreate() {
    Object.assign(form, {
        exam_id: null,
        branch_id: null,
        school_class_id: null,
        section_id: null,
        rows: 5,
        columns: 4,
        students_per_bench: 1,
        room_count: null,
        fill_order: 'roll_number',
        separate_by_gender: false,
        room_prefix: 'Room',
        room_suffix: '',
    });
    formOpen.value = true;
}

async function save() {
    if (!form.exam_id || !form.branch_id) {
        pushToast('Select an exam and branch.', 'error');
        return;
    }
    if (!form.rows || !form.columns || !form.students_per_bench) {
        pushToast('Rows, columns, and students per bench are required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const { data } = await client.post('/exams/seat-plans', form);
        pushToast('Seat plan created.', 'success');
        formOpen.value = false;
        await loadSheets();
        await selectSheet(data.id);
    } finally {
        saving.value = false;
    }
}

async function exportRoom() {
    if (!selectedRoom.value) return;
    const response = await client.get(`/exams/seat-plans/rooms/${selectedRoom.value.id}/export`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `${selectedRoom.value.name.toLowerCase().replace(/\s+/g, '-')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

loadSheets();
</script>
