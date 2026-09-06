<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">ID Cards</h1>
                <Breadcrumb :items="['Dashboard', 'Documents', 'ID Cards']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Issue ID Card</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="ID cards" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Cards Issued" :value="cards.length" color="indigo" icon="🪪" />
            <StatCard label="Active" :value="cards.filter((c) => c.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Reissued/Lost" :value="cards.filter((c) => c.status !== 'Active').length" color="amber" icon="⚠️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Card No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Holder</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Valid Until</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredCards.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No ID cards match your filters.</td>
                    </tr>
                    <tr v-for="c in filteredCards" :key="c.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ c.card_no }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ c.holder?.name || '—' }}</p>
                            <p class="text-xs text-slate-400">{{ c.holder?.admission_no || c.holder?.employee_id || '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{{ c.holder_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ c.valid_until ? formatDate(c.valid_until) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(c.status === 'Active' ? 'Active' : 'Inactive')">{{ c.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit person details" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEditPerson(c)">✏️</button>
                                <button type="button" title="Download" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" :disabled="downloadingId === c.id" @click="downloadCard(c)">{{ downloadingId === c.id ? '...' : '⬇' }}</button>
                                <button v-if="c.status === 'Active'" type="button" title="Reissue" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-amber-600 dark:hover:bg-slate-800" @click="reissue(c)">♻</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(c)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Issue ID Card" @close="drawerOpen = false">
            <div>
                <label class="form-label">Holder Type</label>
                <select v-model="form.holder_type" class="form-input" @change="form.holder_id = null">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="staff">Staff</option>
                </select>
            </div>
            <div>
                <label class="form-label">Person</label>
                <SearchableSelect
                    v-model="form.holder_id"
                    :options="candidateOptions"
                    :placeholder="candidatesLoading ? 'Loading people...' : 'Search person...'"
                    :disabled="candidatesLoading"
                    empty-label="Select person"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Issued Date</label>
                    <input v-model="form.issued_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Valid Until</label>
                    <input v-model="form.valid_until" type="date" class="form-input" />
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Issue' }}</button>
            </template>
        </SlideOver>

        <!-- Edit Person Details -->
        <SlideOver :open="personDrawerOpen" title="Edit Person Details" @close="personDrawerOpen = false">
            <template v-if="editingCard">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Editing the <span class="capitalize font-medium text-slate-700 dark:text-slate-200">{{ editingCard.holder_type }}</span> record linked to card <span class="font-mono">{{ editingCard.card_no }}</span>. These are the fields printed on the ID card.
                </p>

                <template v-if="editingCard.holder_type === 'student'">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="personForm.name" type="text" class="form-input" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Admission No</label>
                            <input v-model="personForm.admission_no" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Roll No</label>
                            <input v-model="personForm.roll_no" type="text" class="form-input" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Gender</label>
                            <select v-model="personForm.gender" class="form-input">
                                <option :value="null">—</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Date of Birth</label>
                            <input v-model="personForm.dob" type="date" class="form-input" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Blood Group</label>
                            <input v-model="personForm.blood_group" type="text" class="form-input" placeholder="e.g. O+" />
                        </div>
                        <div>
                            <label class="form-label">Mobile</label>
                            <input v-model="personForm.mobile" type="text" class="form-input" />
                        </div>
                    </div>
                </template>

                <template v-else-if="editingCard.holder_type === 'teacher'">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="personForm.name" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Employee ID</label>
                        <input :value="personForm.employee_id" type="text" class="form-input bg-slate-50 dark:bg-slate-800/50" readonly />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="personForm.phone" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input v-model="personForm.email" type="email" class="form-input" />
                        </div>
                    </div>
                </template>

                <template v-else-if="editingCard.holder_type === 'staff'">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="personForm.name" type="text" class="form-input" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Employee ID</label>
                            <input v-model="personForm.employee_id" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Department</label>
                            <input v-model="personForm.department" type="text" class="form-input" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="personForm.phone" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input v-model="personForm.email" type="email" class="form-input" />
                        </div>
                    </div>
                </template>
            </template>

            <template #footer>
                <button type="button" class="btn-outline" @click="personDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="savingPerson" @click="savePerson">{{ savingPerson ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import SearchableSelect from '../../components/common/SearchableSelect.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Reissued', 'Lost'] },
];

const loading = ref(true);
const saving = ref(false);
const cards = ref([]);
const students = ref([]);
const teachers = ref([]);
const staff = ref([]);
const candidatesLoaded = ref(false);
const candidatesLoading = ref(false);
const filterValues = reactive({});
const drawerOpen = ref(false);
const downloadingId = ref(null);

const form = reactive({
    holder_type: 'student',
    holder_id: null,
    issued_date: new Date().toISOString().slice(0, 10),
    valid_until: '',
});

const personEndpoints = { student: 'students', teacher: 'teachers', staff: 'staff' };
const editingCard = ref(null);
const personForm = reactive({});
const personDrawerOpen = ref(false);
const savingPerson = ref(false);

const candidates = computed(() => ({
    student: students.value,
    teacher: teachers.value,
    staff: staff.value,
}[form.holder_type] || []));

const candidateOptions = computed(() =>
    candidates.value.map((p) => ({
        value: p.id,
        label: `${p.name} (${p.admission_no || p.employee_id || p.id})`,
    })),
);

const filteredCards = computed(() =>
    cards.value.filter((c) => {
        const holderName = c.holder?.name || '';
        if (filterValues.search && !`${holderName} ${c.card_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && c.status !== filterValues.status) return false;
        return true;
    }),
);

async function loadCards() {
    loading.value = true;
    try {
        const { data } = await client.get('/documents/id-cards');
        cards.value = Array.isArray(data) ? data : [];
    } finally {
        loading.value = false;
    }
}

/** People lists are only needed for Issue — never block the cards table on them. */
async function ensureCandidates() {
    if (candidatesLoaded.value || candidatesLoading.value) return;
    candidatesLoading.value = true;
    try {
        const [studentsRes, teachersRes, staffRes] = await Promise.all([
            client.get('/people/students', { params: { for: 'fee', limit: 2000 } }),
            client.get('/people/teachers'),
            client.get('/people/staff'),
        ]);
        students.value = Array.isArray(studentsRes.data) ? studentsRes.data : [];
        teachers.value = Array.isArray(teachersRes.data) ? teachersRes.data : [];
        staff.value = Array.isArray(staffRes.data) ? staffRes.data : [];
        candidatesLoaded.value = true;
    } catch {
        pushToast('Could not load people list for issuing cards.', 'error');
    } finally {
        candidatesLoading.value = false;
    }
}

loadCards();

async function openAdd() {
    Object.assign(form, {
        holder_type: 'student',
        holder_id: null,
        issued_date: new Date().toISOString().slice(0, 10),
        valid_until: '',
    });
    drawerOpen.value = true;
    await ensureCandidates();
}

async function save() {
    saving.value = true;
    try {
        const { data } = await client.post('/documents/id-cards', form);
        cards.value = [data, ...cards.value];
        pushToast('ID card issued.', 'success');
        drawerOpen.value = false;
    } catch (error) {
        const msg = error?.response?.data?.errors?.holder_id?.[0]
            || error?.response?.data?.message
            || 'Could not issue ID card.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function reissue(card) {
    try {
        const { data } = await client.patch(`/documents/id-cards/${card.id}/reissue`);
        const idx = cards.value.findIndex((c) => c.id === card.id);
        if (idx !== -1) cards.value[idx] = { ...cards.value[idx], status: 'Reissued' };
        cards.value = [data, ...cards.value];
        pushToast(`New ID card issued for ${card.holder?.name || 'holder'}.`, 'success');
    } catch {
        pushToast('Could not reissue ID card.', 'error');
    }
}

async function remove(card) {
    const prev = cards.value;
    cards.value = cards.value.filter((c) => c.id !== card.id);
    try {
        await client.delete(`/documents/id-cards/${card.id}`);
        pushToast(`ID card "${card.card_no}" deleted.`, 'success');
    } catch {
        cards.value = prev;
        pushToast('Could not delete ID card.', 'error');
    }
}

async function openEditPerson(card) {
    editingCard.value = card;
    Object.keys(personForm).forEach((k) => delete personForm[k]);
    Object.assign(personForm, { ...(card.holder || {}) });
    if (personForm.dob) personForm.dob = String(personForm.dob).slice(0, 10);
    personDrawerOpen.value = true;

    // Refresh holder so Save has required fields (e.g. school_class_id) without slowing the list.
    try {
        const path = personEndpoints[card.holder_type];
        const { data } = await client.get(`/people/${path}/${card.holder_id}`);
        Object.keys(personForm).forEach((k) => delete personForm[k]);
        Object.assign(personForm, data);
        if (personForm.dob) personForm.dob = String(personForm.dob).slice(0, 10);
    } catch {
        // Keep slim holder snapshot already in the form.
    }
}

async function savePerson() {
    if (!editingCard.value) return;
    savingPerson.value = true;
    try {
        const path = personEndpoints[editingCard.value.holder_type];
        const { data } = await client.put(`/people/${path}/${editingCard.value.holder_id}`, personForm);
        const cardId = editingCard.value.id;
        cards.value = cards.value.map((c) => (
            c.id === cardId
                ? { ...c, holder: { ...c.holder, ...data } }
                : c
        ));
        pushToast('Person details updated.', 'success');
        personDrawerOpen.value = false;
    } catch (error) {
        const msg = error?.response?.data?.message || 'Could not save person details.';
        pushToast(msg, 'error');
    } finally {
        savingPerson.value = false;
    }
}

async function downloadCard(card) {
    downloadingId.value = card.id;
    try {
        await downloadPdf(`/documents/id-cards/${card.id}/pdf`, `id-card-${card.card_no}.pdf`);
    } catch {
        pushToast('Could not open ID card PDF.', 'error');
    } finally {
        downloadingId.value = null;
    }
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
