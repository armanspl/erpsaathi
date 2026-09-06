<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Enquiry</h1>
                <Breadcrumb :items="['Dashboard', 'Admissions', 'Enquiry']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Enquiry</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="enquiries" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="New" :value="countByLeadStatus('New')" color="amber" icon="✨" />
            <StatCard label="Contacted" :value="countByLeadStatus('Contacted')" color="sky" icon="📞" />
            <StatCard label="Converted" :value="countByLeadStatus('Converted')" color="emerald" icon="✅" />
            <StatCard label="Closed" :value="countByLeadStatus('Closed')" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Enquiry No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Parent</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredEnquiries.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No enquiries match your filters.</td>
                    </tr>
                    <tr v-for="e in filteredEnquiries" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ e.enquiry_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ e.student_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.parent_name }}<br /><span class="text-xs">{{ e.phone }}</span></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.branch?.name || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.class_applying_for?.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(e.lead_status || 'New')">{{ e.lead_status || 'New' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(e)">✏️</button>
                                <button
                                    v-if="canConvert(e)"
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-500/30 dark:hover:bg-primary-500/10"
                                    @click="goRegister(e)"
                                >Register</button>
                                <button
                                    v-if="canConvert(e)"
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 transition hover:bg-emerald-50 dark:text-emerald-300 dark:ring-emerald-500/30 dark:hover:bg-emerald-500/10"
                                    @click="goAdmit(e)"
                                >Admit</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(e)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="formDrawerOpen" :title="editing ? 'Edit Enquiry' : 'Add Enquiry'" @close="formDrawerOpen = false">
            <h4 class="form-section-heading !mt-0">Student</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Student Name</label>
                    <input v-model="form.student_name" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">Student DOB</label>
                    <input v-model="form.dob" type="date" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="form.branch_id" class="form-input">
                        <option :value="null">Select branch</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="form.class_applying_for_id" class="form-input">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Gender</label>
                    <select v-model="form.gender" class="form-input">
                        <option :value="null">—</option>
                        <option>Male</option>
                        <option>Female</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Present School</label>
                    <input v-model="form.present_school" type="text" class="form-input" />
                </div>
            </div>

            <h4 class="form-section-heading">Parent / Guardian</h4>
            <div>
                <label class="form-label">Parent Name</label>
                <input v-model="form.parent_name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Parent Phone</label>
                    <input v-model="form.phone" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">Parent Email</label>
                    <input v-model="form.email" type="email" class="form-input" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.whatsapp_optin" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                WhatsApp Opt-in
            </label>

            <h4 class="form-section-heading">Address</h4>
            <div>
                <label class="form-label">Address Line 1</label>
                <input v-model="form.address_line_1" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">City</label>
                    <input v-model="form.city" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">State</label>
                    <input v-model="form.state" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Pincode</label>
                    <input v-model="form.pincode" type="text" class="form-input" />
                </div>
            </div>

            <h4 class="form-section-heading">Enquiry Details</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Lead Source</label>
                    <input v-model="form.source" type="text" class="form-input" placeholder="Walk-in, Website..." />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.lead_status" class="form-input">
                        <option>New</option>
                        <option>Contacted</option>
                        <option>Converted</option>
                        <option>Closed</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Preferred Contact Time</label>
                    <select v-model="form.preferred_contact_time" class="form-input">
                        <option :value="null">—</option>
                        <option>Morning</option>
                        <option>Afternoon</option>
                        <option>Evening</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Preferred Mode</label>
                    <select v-model="form.preferred_mode" class="form-input">
                        <option :value="null">—</option>
                        <option>Phone</option>
                        <option>Email</option>
                        <option>WhatsApp</option>
                        <option>In-Person</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Preferred Contact Date</label>
                    <input v-model="form.preferred_contact_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Follow-up Date</label>
                    <input v-model="form.next_follow_up_date" type="date" class="form-input" />
                </div>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.transport_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                    Transport Required
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.consent_given" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                    Consent Given
                </label>
            </div>
            <div>
                <label class="form-label">Message</label>
                <textarea v-model="form.remarks" rows="3" class="form-input" />
            </div>

            <template #footer>
                <button type="button" class="btn-outline" @click="formDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import { fetchEnquiries, invalidateAdmissionsLookups } from '../../api/admissions';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const router = useRouter();

const filters = computed(() => [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'branch', label: 'Branch', type: 'select', options: branches.value.map((b) => b.name) },
    { key: 'status', label: 'Status', type: 'select', options: ['New', 'Contacted', 'Converted', 'Closed'] },
]);

const loading = ref(true);
const saving = ref(false);
const enquiries = ref([]);
const classes = ref([]);
const branches = ref([]);
const filterValues = reactive({});

async function load() {
    loading.value = true;
    const [enquiryRows, lookups] = await Promise.all([
        fetchEnquiries({ limit: 300 }),
        fetchAcademicsLookups(),
    ]);
    enquiries.value = enquiryRows;
    classes.value = lookups.classes || [];
    branches.value = lookups.branches || [];
    loading.value = false;
}
load();

function countByLeadStatus(status) {
    return enquiries.value.filter((e) => (e.lead_status || 'New') === status).length;
}

function canConvert(enquiry) {
    const lead = enquiry.lead_status || 'New';
    return lead !== 'Converted' && lead !== 'Closed';
}

const filteredEnquiries = computed(() =>
    enquiries.value.filter((e) => {
        if (filterValues.search && !`${e.student_name} ${e.parent_name} ${e.phone} ${e.enquiry_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.branch && e.branch?.name !== filterValues.branch) return false;
        if (filterValues.status && (e.lead_status || 'New') !== filterValues.status) return false;
        return true;
    }),
);

const formDrawerOpen = ref(false);
const editing = ref(null);

function blankForm() {
    return {
        branch_id: null, student_name: '', dob: '', gender: null, present_school: '',
        parent_name: '', phone: '', email: '', whatsapp_optin: false,
        address_line_1: '', city: '', state: '', pincode: '',
        class_applying_for_id: null, source: '', lead_status: 'New',
        preferred_contact_time: null, preferred_mode: null, preferred_contact_date: '', next_follow_up_date: '',
        transport_required: false, consent_given: false, remarks: '',
    };
}

const form = reactive(blankForm());

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

function openAdd() {
    editing.value = null;
    Object.assign(form, blankForm());
    formDrawerOpen.value = true;
}

function openEdit(enquiry) {
    editing.value = enquiry;
    Object.assign(form, blankForm(), {
        branch_id: enquiry.branch_id,
        student_name: enquiry.student_name,
        dob: toDateInput(enquiry.dob),
        gender: enquiry.gender,
        present_school: enquiry.present_school || '',
        parent_name: enquiry.parent_name,
        phone: enquiry.phone,
        email: enquiry.email || '',
        whatsapp_optin: !!enquiry.whatsapp_optin,
        address_line_1: enquiry.address_line_1 || '',
        city: enquiry.city || '',
        state: enquiry.state || '',
        pincode: enquiry.pincode || '',
        class_applying_for_id: enquiry.class_applying_for_id,
        source: enquiry.source || '',
        lead_status: enquiry.lead_status || 'New',
        preferred_contact_time: enquiry.preferred_contact_time,
        preferred_mode: enquiry.preferred_mode,
        preferred_contact_date: toDateInput(enquiry.preferred_contact_date),
        next_follow_up_date: toDateInput(enquiry.next_follow_up_date),
        transport_required: !!enquiry.transport_required,
        consent_given: !!enquiry.consent_given,
        remarks: enquiry.remarks || '',
    });
    formDrawerOpen.value = true;
}

async function save() {
    if (!form.student_name?.trim() || !form.parent_name?.trim() || !form.phone?.trim() || !form.class_applying_for_id) {
        pushToast('Student name, parent name, phone and class are required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = { ...form };
        if (editing.value) {
            const { data } = await client.put(`/admissions/enquiries/${editing.value.id}`, payload);
            const idx = enquiries.value.findIndex((e) => e.id === data.id);
            if (idx !== -1) enquiries.value[idx] = data;
            pushToast('Enquiry updated.', 'success');
        } else {
            await client.post('/admissions/enquiries', payload);
            pushToast('Enquiry saved.', 'success');
            await load();
        }
        invalidateAdmissionsLookups();
        formDrawerOpen.value = false;
    } catch (err) {
        const msg = err?.response?.data?.message || 'Could not save enquiry.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(enquiry) {
    if (!confirm(`Delete enquiry "${enquiry.enquiry_no}" (${enquiry.student_name})?`)) return;
    await client.delete(`/admissions/enquiries/${enquiry.id}`);
    enquiries.value = enquiries.value.filter((e) => e.id !== enquiry.id);
    invalidateAdmissionsLookups();
    pushToast('Enquiry deleted.', 'success');
}

function goRegister(enquiry) {
    sessionStorage.setItem('admission_enquiry_prefill', String(enquiry.id));
    router.push('/admissions/registration');
}

function goAdmit(enquiry) {
    sessionStorage.setItem('admission_enquiry_prefill', String(enquiry.id));
    router.push('/admissions/admission');
}
</script>
