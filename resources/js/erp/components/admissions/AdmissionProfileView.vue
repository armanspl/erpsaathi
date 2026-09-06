<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400" @click="$emit('close')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back to Admissions
            </button>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400">
                {{ student.status || 'Active' }}
            </span>
        </div>

        <!-- Profile header -->
        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-6 p-5 lg:grid-cols-[1fr_260px] lg:p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                        <img v-if="photoUrl" :src="photoUrl" alt="" class="h-full w-full object-cover" />
                        <svg v-else class="h-12 w-12 text-slate-300 dark:text-slate-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">{{ student.name }}</h2>
                        <div class="mt-4 grid grid-cols-2 gap-x-8 gap-y-3 sm:max-w-lg">
                            <div v-for="item in headerMeta" :key="item.label">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="rounded-xl bg-primary-50/80 p-4 ring-1 ring-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/20">
                    <ul class="space-y-3.5 text-sm">
                        <li v-for="item in quickInfo" :key="item.label" class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/80 text-primary-600 shadow-sm ring-1 ring-primary-100 dark:bg-slate-900/40 dark:ring-primary-500/20">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/></svg>
                            </span>
                            <div>
                                <p class="text-[11px] font-medium text-slate-400">{{ item.label }}</p>
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ item.value }}</p>
                            </div>
                        </li>
                    </ul>
                    <div class="mt-4 border-t border-primary-100 pt-3 dark:border-primary-500/20">
                        <span class="inline-flex items-center rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white shadow-sm">
                            {{ student.status || 'Active' }}
                        </span>
                    </div>
                </aside>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <ProfileCard title="Personal Information" :rows="personalRows" />
            <ProfileCard title="Parent / Guardian Information" :rows="parentRows" />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Address Information</h3>
                </header>
                <p class="px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-primary-500">Current</p>
                <div v-for="row in currentAddressRows" :key="'c-' + row.label" class="flex items-start justify-between gap-4 border-b border-slate-50 px-4 py-2.5 dark:border-slate-800/80">
                    <span class="shrink-0 text-sm text-slate-400">{{ row.label }}</span>
                    <span class="text-right text-sm font-semibold text-slate-800 dark:text-slate-100">{{ row.value }}</span>
                </div>
                <p class="px-4 pb-1 pt-4 text-[11px] font-bold uppercase tracking-wider text-primary-500">
                    Permanent{{ student.permanent_same_as_current ? ' (Same as Current)' : '' }}
                </p>
                <div v-for="row in permanentAddressRows" :key="'p-' + row.label" class="flex items-start justify-between gap-4 border-b border-slate-50 px-4 py-2.5 last:border-0 dark:border-slate-800/80">
                    <span class="shrink-0 text-sm text-slate-400">{{ row.label }}</span>
                    <span class="text-right text-sm font-semibold text-slate-800 dark:text-slate-100">{{ row.value }}</span>
                </div>
            </section>

            <ProfileCard title="Medical Information" :rows="medicalRows" />
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h3 class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Documents</h3>
            </header>
            <div class="px-4 py-4">
                <p v-if="!uploadedDocs.length" class="text-sm text-slate-400">No documents uploaded.</p>
                <ul v-else class="grid gap-2 sm:grid-cols-2">
                    <li v-for="doc in uploadedDocs" :key="doc.key" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800/60">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ doc.label }}</span>
                        <button type="button" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400" @click="downloadDoc(doc)">Download</button>
                    </li>
                </ul>
            </div>
        </section>

        <ProfileCard title="Additional Information" :rows="additionalRows" two-col />

        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary" @click="$emit('pay-fee')">Pay fee</button>
            <button type="button" class="btn-outline" @click="$emit('mark-attendance')">Mark attendance</button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ProfileCard from './ProfileCard.vue';
import client from '../../api/client';
import { erpStore } from '../../store';
import { downloadStudentDocument } from '../../utils/downloadExport';
import { STUDENT_DOCUMENT_TYPES, PARENT_PHOTO_DOCUMENT_TYPES } from '../../data/studentDocumentTypes';

const props = defineProps({
    student: { type: Object, required: true },
});

defineEmits(['close', 'pay-fee', 'mark-attendance']);

const detail = computed(() => props.student.additional_detail || {});
const udise = computed(() => props.student.udise_detail || {});
const father = computed(() => props.student.father || {});
const mother = computed(() => props.student.mother || {});
const guardian = computed(() => props.student.guardian || {});

function dash(value) {
    return value === null || value === undefined || value === '' ? '—' : value;
}

function formatDate(value) {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

const sessionYear = computed(() => {
    const current = erpStore.currentSession;
    if (current && current !== 'All Sessions' && current !== 'All') return current;
    return erpStore.sessionRecords?.find((s) => s.is_current)?.name || '—';
});

const admissionLine = computed(() => {
    const status = props.student.admission_status || 'Admitted';
    const date = formatDate(props.student.admission_date);
    return [status, date || sessionYear.value].filter(Boolean).join(' · ');
});

const headerMeta = computed(() => [
    { label: 'Roll Number', value: dash(props.student.roll_no) },
    { label: 'Admission ID', value: dash(props.student.admission_no) },
    { label: 'Date of Birth', value: dash(formatDate(props.student.dob)) },
    { label: 'Blood Group', value: dash(props.student.blood_group) },
    { label: 'Gender', value: dash(props.student.gender) },
    { label: 'Nationality', value: dash(props.student.nationality) },
]);

const quickInfo = computed(() => [
    { label: 'Branch', value: dash(props.student.branch?.name), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { label: 'Class', value: dash(props.student.school_class?.name), icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0v6' },
    { label: 'Section', value: dash(props.student.section?.name), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { label: 'Admission', value: admissionLine.value, icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
]);

const personalRows = computed(() => [
    { label: 'First Name', value: props.student.first_name },
    { label: 'Middle Name', value: props.student.middle_name },
    { label: 'Last Name', value: props.student.last_name },
    { label: 'Date of Birth', value: formatDate(props.student.dob) },
    { label: 'Gender', value: props.student.gender },
    { label: 'Nationality', value: props.student.nationality },
    { label: 'Religion', value: props.student.religion },
    { label: 'Category', value: props.student.category },
    { label: 'Mother Tongue', value: udise.value.mother_tongue },
    { label: 'PEN Number', value: detail.value.pen_no || udise.value.student_pen },
    { label: 'Aadhaar Number', value: props.student.aadhar_no },
    { label: 'Email', value: props.student.email },
    { label: 'Phone Number', value: props.student.mobile },
]);

const parentRows = computed(() => [
    { label: "Father's Name", value: father.value.name },
    { label: "Father's Occupation", value: father.value.occupation },
    { label: "Father's Phone", value: father.value.phone },
    { label: "Mother's Name", value: mother.value.name },
    { label: "Mother's Occupation", value: mother.value.occupation },
    { label: "Mother's Phone", value: mother.value.phone },
    { label: 'Guardian Name', value: guardian.value.name },
    { label: 'Guardian Phone', value: guardian.value.phone },
]);

const currentAddressRows = computed(() => [
    { label: 'Address', value: dash(props.student.address) },
    { label: 'City', value: dash(props.student.city) },
    { label: 'State', value: dash(props.student.state) },
    { label: 'Pin Code', value: dash(props.student.pincode) },
]);

const permanentAddressRows = computed(() => {
    const same = props.student.permanent_same_as_current;
    return [
        { label: 'Address', value: dash(same ? props.student.address : props.student.permanent_address_line_1) },
        { label: 'City', value: dash(same ? props.student.city : props.student.permanent_city) },
        { label: 'State', value: dash(same ? props.student.state : props.student.permanent_state) },
        { label: 'Pin Code', value: dash(same ? props.student.pincode : props.student.permanent_pincode) },
    ];
});

const medicalRows = computed(() => [
    { label: 'Blood Group', value: props.student.blood_group },
    { label: 'Known Allergies', value: detail.value.allergies },
    { label: 'Chronic Illness', value: detail.value.medical_conditions },
    { label: 'Emergency Contact', value: detail.value.emergency_contact_name },
    { label: 'Emergency Phone', value: detail.value.emergency_contact_phone },
]);

const additionalRows = computed(() => [
    { label: 'Session Year', value: sessionYear.value },
    { label: 'Re-admission', value: props.student.re_admission ? 'Yes' : 'No' },
    { label: 'School Transport', value: udise.value.uses_transport ? 'Yes' : 'No' },
    { label: 'Previous School', value: detail.value.last_school_name },
    { label: 'Previous Class', value: detail.value.previous_class },
    { label: 'Previous Grade', value: detail.value.last_exam_marks },
    { label: 'Created', value: formatDate(props.student.created_at) },
    { label: 'Updated', value: formatDate(props.student.updated_at) },
]);

const allDocTypes = [...STUDENT_DOCUMENT_TYPES, ...PARENT_PHOTO_DOCUMENT_TYPES];
const uploadedDocs = computed(() => {
    const docs = props.student.documents || {};
    return allDocTypes.filter((d) => docs[d.column]);
});

const photoUrl = ref(null);
let photoObjectUrl = null;

onMounted(async () => {
    if (!props.student.documents?.photo_path) return;
    try {
        const response = await client.get(`/people/students/${props.student.id}/documents/photo`, { responseType: 'blob' });
        photoObjectUrl = URL.createObjectURL(response.data);
        photoUrl.value = photoObjectUrl;
    } catch {
        photoUrl.value = null;
    }
});

onUnmounted(() => {
    if (photoObjectUrl) URL.revokeObjectURL(photoObjectUrl);
});

function downloadDoc(doc) {
    downloadStudentDocument(props.student.id, doc.key, `${props.student.admission_no} ${props.student.name} ${doc.label}`);
}
</script>
