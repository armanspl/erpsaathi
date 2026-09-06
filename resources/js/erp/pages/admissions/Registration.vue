<template>
    <div v-if="viewStudent && isAdmissionPage" class="space-y-5">
        <AdmissionProfileView
            :student="viewStudent"
            @close="viewStudent = null"
            @pay-fee="onPayFee"
            @mark-attendance="onMarkAttendance"
        />
    </div>

    <div v-else class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Admissions', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">{{ addButtonLabel }}</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" :label="isAdmissionPage ? 'admissions' : 'registrations'" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="New" :value="pipelineCounts.New" color="amber" icon="✨" />
            <StatCard label="Registered" :value="pipelineCounts.Registered" color="sky" icon="📝" />
            <StatCard label="Admitted" :value="pipelineCounts.Admitted" color="emerald" icon="✅" />
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Admission No</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Parent</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredStudents.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No records match your filters.</td>
                    </tr>
                    <tr v-for="s in filteredStudents" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ s.admission_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.father?.name || '—' }}<br /><span class="text-xs">{{ s.father?.phone || s.mobile || '—' }}</span></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.branch?.name || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.school_class?.name || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.section?.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(s.admission_status || 'Registered')">{{ s.admission_status || 'Registered' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-1">
                                <template v-if="isAdmissionPage">
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-800" @click="openView(s)">View</button>
                                </template>
                                <template v-else>
                                    <button
                                        v-if="(s.admission_status || 'Registered') !== 'Admitted'"
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 transition hover:bg-emerald-50 dark:text-emerald-300 dark:ring-emerald-500/30"
                                        :disabled="promotingId === s.id"
                                        @click="promoteToAdmitted(s)"
                                    >{{ promotingId === s.id ? '...' : 'Promote to Admitted' }}</button>
                                    <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-500/30" @click="goNewAdmission(s)">New Admission</button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Registration / Admission -->
        <SlideOver :open="drawerOpen" :title="editing ? 'Edit' : formTitle" wide @close="drawerOpen = false">
            <h4 class="form-section-heading !mt-0">Student Details</h4>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">First Name</label>
                    <input v-model="form.first_name" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">Middle Name</label>
                    <input v-model="form.middle_name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Last Name</label>
                    <input v-model="form.last_name" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input v-model="form.mobile" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Date of Birth</label>
                    <input v-model="form.dob" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Admission Date</label>
                    <input v-model="form.admission_date" type="date" class="form-input" />
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
                    <label class="form-label">Blood Group</label>
                    <input v-model="form.blood_group" type="text" class="form-input" placeholder="e.g. O+" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Category</label>
                    <input v-model="form.category" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Religion</label>
                    <input v-model="form.religion" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">PEN Number</label>
                    <input v-model="form.pen_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Nationality</label>
                    <input v-model="form.nationality" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Mother Tongue</label>
                <input v-model="form.mother_tongue" type="text" class="form-input" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.re_admission" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                Re-admission
            </label>

            <h4 class="form-section-heading">Applied Class</h4>
            <div>
                <label class="form-label">Academic Session</label>
                <select v-model="form.academic_session_id" class="form-input">
                    <option :value="null">Current session</option>
                    <option v-for="sess in academicSessions" :key="sess.id" :value="sess.id">{{ sess.name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Admission ID</label>
                    <input v-model="form.admission_no" type="text" class="form-input" required />
                </div>
                <div>
                    <label class="form-label">Roll Number</label>
                    <input v-model.number="form.roll_no" type="number" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Branch</label>
                <select v-model="form.branch_id" class="form-input">
                    <option :value="null">Select branch</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="form.school_class_id" class="form-input" required @change="form.section_id = null">
                        <option :value="null">Select class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="form.section_id" class="form-input">
                        <option :value="null">Select section</option>
                        <option v-for="sec in sectionsForSelectedClass" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                    </select>
                </div>
            </div>

            <h4 class="form-section-heading">Transport</h4>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.uses_transport" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                Uses School Transport
            </label>

            <h4 class="form-section-heading">Addresses</h4>
            <p class="form-label !mb-2">Current Address</p>
            <div>
                <label class="form-label">Address Line 1</label>
                <input v-model="form.address" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Address Line 2</label>
                <input v-model="form.address_line_2" type="text" class="form-input" />
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
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.permanent_same_as_current" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                Permanent address same as current
            </label>
            <template v-if="!form.permanent_same_as_current">
                <p class="form-label !mb-2">Permanent Address</p>
                <div>
                    <label class="form-label">Address Line 1</label>
                    <input v-model="form.permanent_address_line_1" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Address Line 2</label>
                    <input v-model="form.permanent_address_line_2" type="text" class="form-input" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="form-label">City</label>
                        <input v-model="form.permanent_city" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">State</label>
                        <input v-model="form.permanent_state" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Pincode</label>
                        <input v-model="form.permanent_pincode" type="text" class="form-input" />
                    </div>
                </div>
            </template>

            <h4 class="form-section-heading">Father</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="form.father_name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.father_email" type="email" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Occupation</label>
                    <input v-model="form.father_occupation" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Qualification</label>
                    <input v-model="form.father_qualification" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Phone</label>
                    <input v-model="form.father_phone" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Annual Income</label>
                    <input v-model.number="form.father_annual_income" type="number" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Aadhaar Number</label>
                    <input v-model="form.father_aadhaar_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">PAN Number</label>
                    <input v-model="form.father_pan_no" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <DocumentField v-for="doc in fatherDocumentTypes" :key="doc.key" :doc="doc" :editing="editing" :file-name="documentFileNames[doc.key]" :deleting="deletingDoc === doc.key" @pick="onDocumentFileChange" @clear="clearPendingFile" @download="downloadDoc" @remove="removeExistingDoc" />
            </div>

            <h4 class="form-section-heading">Mother</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="form.mother_name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input v-model="form.mother_phone" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.mother_email" type="email" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Occupation</label>
                    <input v-model="form.mother_occupation" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Qualification</label>
                    <input v-model="form.mother_qualification" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Annual Income</label>
                    <input v-model.number="form.mother_annual_income" type="number" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Aadhaar Number</label>
                    <input v-model="form.mother_aadhaar_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">PAN Number</label>
                    <input v-model="form.mother_pan_no" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <DocumentField v-for="doc in motherDocumentTypes" :key="doc.key" :doc="doc" :editing="editing" :file-name="documentFileNames[doc.key]" :deleting="deletingDoc === doc.key" @pick="onDocumentFileChange" @clear="clearPendingFile" @download="downloadDoc" @remove="removeExistingDoc" />
            </div>

            <h4 class="form-section-heading">Guardian</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="form.guardian_name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.guardian_email" type="email" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Phone</label>
                    <input v-model="form.guardian_phone" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Occupation</label>
                    <input v-model="form.guardian_occupation" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Relationship</label>
                <input v-model="form.guardian_relationship" type="text" class="form-input" placeholder="e.g. Uncle, Grandfather" />
            </div>

            <h4 class="form-section-heading">Medical</h4>
            <div>
                <label class="form-label">Conditions</label>
                <textarea v-model="form.medical_conditions" rows="2" class="form-input" />
            </div>
            <div>
                <label class="form-label">Allergies</label>
                <textarea v-model="form.allergies" rows="2" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Emergency Contact Name</label>
                    <input v-model="form.emergency_contact_name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Emergency Contact Phone</label>
                    <input v-model="form.emergency_contact_phone" type="text" class="form-input" />
                </div>
            </div>

            <h4 class="form-section-heading">Previous School</h4>
            <div>
                <label class="form-label">School Name</label>
                <input v-model="form.last_school_name" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Percentage / Grade</label>
                    <input v-model="form.last_exam_marks" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <input v-model="form.previous_class" type="text" class="form-input" />
                </div>
            </div>

            <template v-if="customFields.length">
                <h4 class="form-section-heading">More Fields</h4>
                <div v-for="field in customFields" :key="field.id" :class="field.type === 'textarea' ? '' : 'grid grid-cols-1'">
                    <div>
                        <label class="form-label">{{ field.label }}</label>
                        <textarea
                            v-if="field.type === 'textarea'"
                            v-model="form.custom_field_values[field.id]"
                            rows="2"
                            class="form-input"
                            :placeholder="field.placeholder || ''"
                        />
                        <input
                            v-else
                            v-model="form.custom_field_values[field.id]"
                            :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
                            class="form-input"
                            :placeholder="field.placeholder || ''"
                        />
                    </div>
                </div>
            </template>

            <h4 class="form-section-heading">Hostel / Route</h4>
            <div>
                <label class="form-label">Route</label>
                <input v-model="form.route" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Hostel Room No.</label>
                    <input v-model="form.hostel_room_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Bed No.</label>
                    <input v-model="form.hostel_bed_no" type="text" class="form-input" />
                </div>
            </div>

            <h4 class="form-section-heading">Documents</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Aadhaar Number</label>
                    <input v-model="form.aadhar_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">PAN Number</label>
                    <input v-model="form.pan_no" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <DocumentField v-for="doc in mainDocumentTypes" :key="doc.key" :doc="doc" :editing="editing" :file-name="documentFileNames[doc.key]" :deleting="deletingDoc === doc.key" @pick="onDocumentFileChange" @clear="clearPendingFile" @download="downloadDoc" @remove="removeExistingDoc" />
            </div>

            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import StatCard from '../../components/common/StatCard.vue';
import DocumentField from '../../components/students/DocumentField.vue';
import AdmissionProfileView from '../../components/admissions/AdmissionProfileView.vue';
import {
    fetchAcademicsLookups,
    fetchAdmissionStudents,
    fetchAdmissionsLookups,
    fetchStudentFull,
    invalidateAdmissionsLookups,
} from '../../api/admissions';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';
import { downloadStudentDocument } from '../../utils/downloadExport';
import { STUDENT_DOCUMENT_TYPES, PARENT_PHOTO_DOCUMENT_TYPES } from '../../data/studentDocumentTypes';

const route = useRoute();
const router = useRouter();

const isAdmissionPage = computed(() => route.path === '/admissions/admission');
const pageTitle = computed(() => (isAdmissionPage.value ? 'Admission' : 'Registration'));
const addButtonLabel = computed(() => (isAdmissionPage.value ? '+ Create Admission' : '+ Create Registration'));
const formTitle = computed(() => (isAdmissionPage.value ? 'Create Admission' : 'Create Registration'));
const targetAdmissionStatus = computed(() => (isAdmissionPage.value ? 'Admitted' : 'Registered'));

const mainDocumentTypes = STUDENT_DOCUMENT_TYPES.filter((d) => !d.key.startsWith('father_') && !d.key.startsWith('mother_'));
const fatherDocumentTypes = [
    ...STUDENT_DOCUMENT_TYPES.filter((d) => d.key.startsWith('father_')),
    ...PARENT_PHOTO_DOCUMENT_TYPES.filter((d) => d.key === 'father_photo'),
];
const motherDocumentTypes = [
    ...STUDENT_DOCUMENT_TYPES.filter((d) => d.key.startsWith('mother_')),
    ...PARENT_PHOTO_DOCUMENT_TYPES.filter((d) => d.key === 'mother_photo'),
];

const filters = computed(() => [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'branch', label: 'Branch', type: 'select', options: branches.value.map((b) => b.name) },
    { key: 'class', label: 'Class', type: 'select', options: classes.value.map((c) => c.name) },
    { key: 'section', label: 'Section', type: 'select', options: sections.value.map((s) => s.name) },
    { key: 'status', label: 'Status', type: 'select', options: ['New', 'Registered', 'Admitted'] },
]);

const loading = ref(true);
const saving = ref(false);
const promotingId = ref(null);
const students = ref([]);
const classes = ref([]);
const sections = ref([]);
const branches = ref([]);
const academicSessions = ref([]);
const customFields = ref([]);
const filterValues = reactive({});
const viewStudent = ref(null);
const enquiryId = ref(null);
const pipelineCounts = ref({ New: 0, Registered: 0, Admitted: 0 });

async function load() {
    loading.value = true;
    const statusFilter = isAdmissionPage.value ? 'Admitted' : 'New,Registered';
    const [studentRows, academics, admissionsBoot, sessionsRes] = await Promise.all([
        fetchAdmissionStudents(statusFilter),
        fetchAcademicsLookups(),
        fetchAdmissionsLookups(),
        client.get('/settings/academic-sessions'),
    ]);
    students.value = studentRows;
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    branches.value = academics.branches || [];
    academicSessions.value = sessionsRes.data;
    customFields.value = admissionsBoot.custom_fields_active || [];
    pipelineCounts.value = admissionsBoot.pipeline_counts || { New: 0, Registered: 0, Admitted: 0 };
    loading.value = false;
}

const filteredStudents = computed(() =>
    students.value.filter((s) => {
        const pipeline = s.admission_status || 'Admitted';
        // Registration page: Registered (and New if any); Admission page: Admitted
        if (isAdmissionPage.value) {
            if (pipeline !== 'Admitted') return false;
        } else if (pipeline === 'Admitted') {
            return false;
        }
        if (filterValues.search && !`${s.name} ${s.admission_no} ${s.mobile || ''}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.branch && s.branch?.name !== filterValues.branch) return false;
        if (filterValues.class && s.school_class?.name !== filterValues.class) return false;
        if (filterValues.section && s.section?.name !== filterValues.section) return false;
        if (filterValues.status && pipeline !== filterValues.status) return false;
        return true;
    }),
);

const drawerOpen = ref(false);
const editing = ref(null);
let documentFiles = {};
const documentFileNames = reactive({});
const deletingDoc = ref(null);

function blankCustomValues() {
    const values = {};
    customFields.value.forEach((f) => { values[f.id] = ''; });
    return values;
}

function blankForm() {
    return {
        first_name: '', middle_name: '', last_name: '',
        email: '', mobile: '', dob: '', admission_date: '',
        gender: null, blood_group: '', category: '', religion: '',
        pen_no: '', nationality: '', mother_tongue: '', re_admission: false,

        academic_session_id: null, admission_no: '', roll_no: null,
        branch_id: null, school_class_id: null, section_id: null,

        uses_transport: false,

        address: '', address_line_2: '', city: '', state: '', pincode: '',
        permanent_same_as_current: true,
        permanent_address_line_1: '', permanent_address_line_2: '', permanent_city: '', permanent_state: '', permanent_pincode: '',

        father_name: '', father_email: '', father_occupation: '', father_qualification: '',
        father_phone: '', father_annual_income: null, father_aadhaar_no: '', father_pan_no: '',

        mother_name: '', mother_email: '', mother_occupation: '', mother_qualification: '',
        mother_phone: '', mother_annual_income: null, mother_aadhaar_no: '', mother_pan_no: '',

        guardian_name: '', guardian_email: '', guardian_phone: '', guardian_occupation: '', guardian_relationship: '',

        medical_conditions: '', allergies: '', emergency_contact_name: '', emergency_contact_phone: '',

        last_school_name: '', last_exam_marks: '', previous_class: '',

        route: '', hostel_room_no: '', hostel_bed_no: '',

        aadhar_no: '', pan_no: '',

        status: 'Active',
        admission_status: targetAdmissionStatus.value,
        custom_field_values: blankCustomValues(),
    };
}

const form = reactive(blankForm());

const sectionsForSelectedClass = computed(() => sections.value.filter((s) => s.school_class_id === form.school_class_id));

function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

function resetDocumentPicks() {
    documentFiles = {};
    Object.keys(documentFileNames).forEach((k) => delete documentFileNames[k]);
}

function onDocumentFileChange(key, file) {
    documentFiles[key] = file;
    if (file) {
        documentFileNames[key] = file.name;
    } else {
        delete documentFileNames[key];
    }
}

function clearPendingFile(key) {
    documentFiles[key] = null;
    delete documentFileNames[key];
}

function downloadDoc(doc) {
    if (!editing.value) return;
    downloadStudentDocument(editing.value.id, doc.key, `${editing.value.admission_no} ${editing.value.name} ${doc.label}`);
}

async function removeExistingDoc(doc) {
    if (!editing.value) return;
    deletingDoc.value = doc.key;
    try {
        await client.delete(`/people/students/${editing.value.id}/documents/${doc.key}`);
        editing.value.documents[doc.column] = null;
        pushToast(`${doc.label} removed.`, 'success');
    } finally {
        deletingDoc.value = null;
    }
}

function openAdd() {
    editing.value = null;
    enquiryId.value = null;
    sessionStorage.removeItem('admission_promote_source');
    resetDocumentPicks();
    Object.assign(form, blankForm());
    drawerOpen.value = true;
}

async function openView(student) {
    try {
        viewStudent.value = await fetchStudentFull(student.id);
    } catch {
        viewStudent.value = student;
        pushToast('Could not load full profile; showing summary.', 'error');
    }
}

function onPayFee() {
    if (!viewStudent.value) return;
    router.push({ path: '/people/students', query: { student: viewStudent.value.id, tab: 'Fees' } });
}

function onMarkAttendance() {
    if (!viewStudent.value) return;
    router.push({ path: '/people/students', query: { student: viewStudent.value.id, tab: 'Attendance' } });
}

function goNewAdmission(student) {
    sessionStorage.setItem('admission_student_prefill', String(student.id));
    router.push('/admissions/admission');
}

async function promoteToAdmitted(student) {
    promotingId.value = student.id;
    try {
        await client.patch(`/people/students/${student.id}/admission-status`, { admission_status: 'Admitted' });
        pushToast(`${student.name} promoted to Admitted.`, 'success');
        invalidateAdmissionsLookups();
        await load();
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Promote failed.', 'error');
    } finally {
        promotingId.value = null;
    }
}

function splitStudentName(fullName) {
    const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return { first_name: '', middle_name: '', last_name: '' };
    if (parts.length === 1) return { first_name: parts[0], middle_name: '', last_name: '' };
    if (parts.length === 2) return { first_name: parts[0], middle_name: '', last_name: parts[1] };
    return { first_name: parts[0], middle_name: parts.slice(1, -1).join(' '), last_name: parts[parts.length - 1] };
}

function nextAdmissionNoSuggestion() {
    const year = new Date().getFullYear();
    const prefix = `ADM-${year}-`;
    let max = 0;
    students.value.forEach((s) => {
        const m = String(s.admission_no || '').match(new RegExp(`^ADM-${year}-(\\d+)$`));
        if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return `${prefix}${String(max + 1).padStart(4, '0')}`;
}

function applyStudentToForm(student) {
    editing.value = null;
    resetDocumentPicks();
    const detail = student.additional_detail || {};
    const udise = student.udise_detail || {};
    const father = student.father || {};
    const mother = student.mother || {};
    const guardian = student.guardian || {};
    const names = {
        first_name: student.first_name || '',
        middle_name: student.middle_name || '',
        last_name: student.last_name || '',
    };
    if (!names.first_name && student.name) {
        Object.assign(names, splitStudentName(student.name));
    }

    Object.assign(form, blankForm(), {
        ...names,
        email: student.email || '',
        mobile: student.mobile || '',
        dob: toDateInput(student.dob),
        admission_date: toDateInput(student.admission_date) || new Date().toISOString().slice(0, 10),
        gender: student.gender,
        blood_group: student.blood_group || '',
        category: student.category || '',
        religion: student.religion || '',
        pen_no: detail.pen_no || '',
        nationality: student.nationality || '',
        mother_tongue: udise.mother_tongue || '',
        re_admission: !!student.re_admission,

        academic_session_id: null,
        admission_no: student.admission_no || nextAdmissionNoSuggestion(),
        roll_no: student.roll_no,
        branch_id: student.branch_id,
        school_class_id: student.school_class_id,
        section_id: student.section_id,

        uses_transport: !!udise.uses_transport,

        address: student.address || '',
        address_line_2: student.address_line_2 || '',
        city: student.city || '',
        state: student.state || '',
        pincode: student.pincode || '',
        permanent_same_as_current: student.permanent_same_as_current ?? true,
        permanent_address_line_1: student.permanent_address_line_1 || '',
        permanent_address_line_2: student.permanent_address_line_2 || '',
        permanent_city: student.permanent_city || '',
        permanent_state: student.permanent_state || '',
        permanent_pincode: student.permanent_pincode || '',

        father_name: father.name || '',
        father_email: father.email || '',
        father_occupation: father.occupation || '',
        father_qualification: father.qualification || '',
        father_phone: father.phone || '',
        father_annual_income: father.annual_income ?? null,
        father_aadhaar_no: father.aadhaar_no || '',
        father_pan_no: father.pan_no || '',

        mother_name: mother.name || '',
        mother_email: mother.email || '',
        mother_occupation: mother.occupation || '',
        mother_qualification: mother.qualification || '',
        mother_phone: mother.phone || '',
        mother_annual_income: mother.annual_income ?? null,
        mother_aadhaar_no: mother.aadhaar_no || '',
        mother_pan_no: mother.pan_no || '',

        guardian_name: guardian.name || '',
        guardian_email: guardian.email || '',
        guardian_phone: guardian.phone || '',
        guardian_occupation: guardian.occupation || '',
        guardian_relationship: guardian.relationship || '',

        medical_conditions: detail.medical_conditions || '',
        allergies: detail.allergies || '',
        emergency_contact_name: detail.emergency_contact_name || '',
        emergency_contact_phone: detail.emergency_contact_phone || '',

        last_school_name: detail.last_school_name || '',
        last_exam_marks: detail.last_exam_marks || '',
        previous_class: detail.previous_class || '',

        route: udise.route || '',
        hostel_room_no: udise.hostel_room_no || '',
        hostel_bed_no: udise.hostel_bed_no || '',

        aadhar_no: student.aadhar_no || '',
        pan_no: student.pan_no || '',

        status: 'Active',
        admission_status: targetAdmissionStatus.value,
        custom_field_values: { ...blankCustomValues(), ...(student.custom_field_values || {}) },
    });
    drawerOpen.value = true;
}

async function prefillFromStudent(id) {
    try {
        const student = await fetchStudentFull(id);
        if (!student) {
            pushToast('Registration not found.', 'error');
            return;
        }
        applyStudentToForm(student);
        sessionStorage.setItem('admission_promote_source', String(student.id));
    } catch {
        pushToast('Could not load registration details.', 'error');
    }
}

async function prefillFromEnquiry(id) {
    try {
        const { data: list } = await client.get('/admissions/enquiries');
        const enquiry = list.find((e) => String(e.id) === String(id));
        if (!enquiry) {
            pushToast('Enquiry not found.', 'error');
            return;
        }
        enquiryId.value = enquiry.id;
        editing.value = null;
        resetDocumentPicks();
        const names = splitStudentName(enquiry.student_name);
        Object.assign(form, blankForm(), {
            ...names,
            dob: toDateInput(enquiry.dob),
            gender: enquiry.gender,
            mobile: enquiry.phone || '',
            email: enquiry.email || '',
            branch_id: enquiry.branch_id,
            school_class_id: enquiry.class_applying_for_id,
            address: enquiry.address_line_1 || '',
            city: enquiry.city || '',
            state: enquiry.state || '',
            pincode: enquiry.pincode || '',
            father_name: enquiry.parent_name || '',
            father_phone: enquiry.phone || '',
            last_school_name: enquiry.present_school || '',
            uses_transport: !!enquiry.transport_required,
            admission_no: nextAdmissionNoSuggestion(),
            admission_date: new Date().toISOString().slice(0, 10),
            admission_status: targetAdmissionStatus.value,
            status: 'Active',
        });
        drawerOpen.value = true;
    } catch {
        pushToast('Could not load enquiry details.', 'error');
    }
}

function buildFormData() {
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
        if (key === 'custom_field_values') {
            formData.append(key, JSON.stringify(value || {}));
        } else if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0');
        } else {
            formData.append(key, value === null || value === undefined ? '' : value);
        }
    });
    Object.entries(documentFiles).forEach(([key, file]) => {
        if (file) formData.append(`${key}_file`, file);
    });
    return formData;
}

async function save() {
    saving.value = true;
    try {
        form.admission_status = targetAdmissionStatus.value;
        const formData = buildFormData();
        const promoteSource = sessionStorage.getItem('admission_promote_source');

        if (editing.value) {
            formData.append('_method', 'PUT');
            await client.post(`/people/students/${editing.value.id}`, formData);
            pushToast(`${pageTitle.value} updated.`, 'success');
        } else if (promoteSource && isAdmissionPage.value) {
            // New Admission from a Registration row — update that student to Admitted with form data.
            sessionStorage.removeItem('admission_promote_source');
            formData.append('_method', 'PUT');
            formData.set('admission_status', 'Admitted');
            await client.post(`/people/students/${promoteSource}`, formData);
            pushToast('Admission created.', 'success');
        } else {
            await client.post('/people/students', formData);
            if (enquiryId.value) {
                await client.patch(`/admissions/enquiries/${enquiryId.value}/stage`, {
                    stage: isAdmissionPage.value ? 'admitted' : 'registered',
                });
            }
            pushToast(`${pageTitle.value} created.`, 'success');
        }
        drawerOpen.value = false;
        enquiryId.value = null;
        invalidateAdmissionsLookups();
        await load();
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        saving.value = false;
    }
}

async function initFromRoute() {
    await load();
    const enquiryPrefill = route.query.enquiry_id || sessionStorage.getItem('admission_enquiry_prefill');
    if (enquiryPrefill) {
        sessionStorage.removeItem('admission_enquiry_prefill');
        await prefillFromEnquiry(enquiryPrefill);
        return;
    }
    const studentPrefill = sessionStorage.getItem('admission_student_prefill');
    if (studentPrefill && isAdmissionPage.value) {
        sessionStorage.removeItem('admission_student_prefill');
        await prefillFromStudent(studentPrefill);
    }
}

onMounted(initFromRoute);
watch(() => route.path, async () => {
    filterValues.status = undefined;
    await initFromRoute();
});
</script>

