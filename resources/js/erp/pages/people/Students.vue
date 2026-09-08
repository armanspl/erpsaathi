<template>
    <div v-if="!selectedStudent" class="space-y-5">
        <!-- Title + Breadcrumb + Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Students</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Students']" class="mt-1" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline" @click="goImport"><IconGlyph name="upload" /> Import</button>
                <button type="button" class="btn-outline" :disabled="exporting" @click="exportModalOpen = true">
                    <IconGlyph name="download" /> {{ exporting ? 'Exporting...' : 'Export' }}
                </button>
                <button type="button" class="btn-primary" @click="openAdd"><IconGlyph name="plus" /> Add Student</button>
            </div>
        </div>

        <!-- Filter Bar -->
        <FilterBar :filters="filters" v-model="filterValues" label="students" @reset="resetFilters" />

        <!-- Stat cards -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard label="Total" :value="counts.total" color="indigo" icon="🎓" />
            <StatCard label="Active" :value="counts.active" color="emerald" icon="✅" />
            <StatCard label="Inactive" :value="counts.inactive" color="rose" icon="⛔" />
            <StatCard label="New" :value="counts.new" color="amber" icon="✨" />
            <StatCard label="Transferred" :value="counts.transferred" color="sky" icon="↪️" />
        </div>

        <!-- Table -->
        <DataTable :columns="columns" :rows="pagedRows" :loading="loading" :actions="['view', 'edit', 'delete', 'print', 'idcard', 'certificate']" @action="onRowAction" />

        <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <Pagination v-model="page" :per-page="perPage" :total="filteredRows.length" />
        </div>

        <div v-if="exportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="exportModalOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Student Export</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Select sessions and columns, then export. Only checked fields are included.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="exportModalOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <StudentExportPanel :exporting="exporting" @export="onStudentExport" />
                </div>
            </div>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Student' : 'Add Student'" @close="drawerOpen = false">
            <div v-if="Object.keys(formErrors).length" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/30 dark:bg-rose-900/20 dark:text-rose-200">
                <div class="font-semibold">Please fix the following:</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(msg, field) in firstErrors" :key="field">
                        <span class="font-medium">{{ field }}:</span> {{ msg }}
                    </li>
                </ul>
            </div>

            <!-- Identity & Academic -->
            <div>
                <label class="form-label">Admission No</label>
                <input v-model="form.admission_no" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Roll No</label>
                <input v-model.number="form.roll_no" type="number" class="form-input" />
            </div>
            <div>
                <label class="form-label">Student Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Branch</label>
                <SearchableSelect
                    v-model="form.branch_id"
                    :options="branchOptions"
                    placeholder="Search branch"
                    empty-label="Select branch"
                    :clearable="false"
                    @update:modelValue="onBranchPicked"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="form.school_class_id" class="form-input" @change="form.section_id = null">
                        <option :value="null">Select class</option>
                        <option v-for="c in classesForSelectedBranch" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="form.section_id" class="form-input">
                        <option :value="null">Select section</option>
                        <option v-for="s in sectionsForSelectedClass" :key="s.id" :value="s.id">{{ s.name }}</option>
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
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-input">
                        <option>Active</option>
                        <option>Inactive</option>
                        <option>Transferred</option>
                    </select>
                </div>
            </div>

            <!-- Personal & Contact (matches the Personal detail tab) -->
            <h4 class="form-section-heading">Personal &amp; Contact</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Date of Birth</label>
                    <input v-model="form.dob" type="date" class="form-input" />
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
                    <label class="form-label">Nationality</label>
                    <input v-model="form.nationality" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Aadhar No</label>
                    <input v-model="form.aadhar_no" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Student PEN</label>
                    <input v-model="form.student_pen" type="text" class="form-input" placeholder="Permanent Education Number" />
                </div>
                <div>
                    <label class="form-label">Name As per Aadhaar</label>
                    <input v-model="form.name_as_per_aadhaar" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Adm Type</label>
                    <select v-model="form.admission_type" class="form-input">
                        <option value="New">New</option>
                        <option value="Old">Old</option>
                    </select>
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input v-model="form.is_in_udise" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                        In UDISE
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Mobile</label>
                    <input v-model="form.mobile" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Address</label>
                <input v-model="form.address" type="text" class="form-input" />
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
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Admission Date</label>
                    <input v-model="form.admission_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Fee Start Month</label>
                    <input v-model="form.fee_start_month" type="month" class="form-input" />
                    <p class="mt-1 text-xs text-slate-400">Fees are charged only from this month onward.</p>
                </div>
            </div>

            <!-- Family -->
            <h4 class="form-section-heading">Family</h4>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">Father</label>
                    <SearchableSelect
                        v-model="form.father_id"
                        :options="parentOptions"
                        placeholder="Search father"
                        empty-label="Select father"
                    />
                </div>
                <div>
                    <label class="form-label">Mother</label>
                    <SearchableSelect
                        v-model="form.mother_id"
                        :options="parentOptions"
                        placeholder="Search mother"
                        empty-label="Select mother"
                    />
                </div>
                <div>
                    <label class="form-label">Guardian</label>
                    <SearchableSelect
                        v-model="form.guardian_id"
                        :options="parentOptions"
                        placeholder="Search guardian"
                        empty-label="Select guardian"
                    />
                </div>
            </div>
            <p class="text-xs text-slate-400">Need to add a new parent first? Go to People &gt; Parents.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">House</label>
                    <input v-model="form.house" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Family</label>
                    <input v-model="form.family" type="text" class="form-input" />
                </div>
            </div>

            <!-- Transport -->
            <h4 class="form-section-heading">Transport</h4>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input v-model="transportForm.apply" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                Assign school transport
            </label>
            <div v-if="transportForm.apply" class="space-y-3">
                <div>
                    <label class="form-label">Route</label>
                    <select v-model.number="transportForm.route_id" class="form-input" @change="onTransportRouteChange">
                        <option :value="null">Select route</option>
                        <option v-for="r in transportRoutes" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Stop / Address</label>
                    <select v-model.number="transportForm.route_stop_id" class="form-input" :disabled="!transportForm.route_id">
                        <option :value="null">Select stop</option>
                        <option v-for="s in transportStopsForRoute" :key="s.id" :value="s.id">
                            {{ s.stop_name }}{{ s.fare != null ? ` — ₹${Number(s.fare).toLocaleString('en-IN')}` : '' }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input v-model="transportForm.start_date" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Transport Fee Start Month</label>
                        <input v-model="transportForm.fee_start_month" type="month" class="form-input" />
                        <p class="mt-1 text-xs text-slate-400">Transport fare charged only from this month.</p>
                    </div>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="transportForm.status" class="form-input">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Health -->
            <h4 class="form-section-heading">Health</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Height (cm)</label>
                    <input v-model.number="form.height" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Weight (kg)</label>
                    <input v-model.number="form.weight" type="number" step="0.01" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Vision Left</label>
                    <input v-model="form.vision_left" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Vision Right</label>
                    <input v-model="form.vision_right" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Dental Hygiene</label>
                <input v-model="form.dental_hygiene" type="text" class="form-input" />
            </div>

            <!-- Previous School -->
            <h4 class="form-section-heading">Previous School</h4>
            <div>
                <label class="form-label">Last School Name</label>
                <input v-model="form.last_school_name" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Last Exam</label>
                    <input v-model="form.last_exam" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Year</label>
                    <input v-model="form.last_exam_year" type="text" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Result Status</label>
                    <input v-model="form.last_exam_status" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Marks</label>
                    <input v-model="form.last_exam_marks" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Board</label>
                <input v-model="form.last_exam_board" type="text" class="form-input" />
            </div>

            <template v-if="customFields.length">
                <h4 class="form-section-heading">More Fields</h4>
                <div v-for="field in customFields" :key="field.id">
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
            </template>

            <!-- Admission & Documents -->
            <h4 class="form-section-heading">Admission &amp; Documents</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Form No.</label>
                    <input v-model="form.form_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Scholarship No.</label>
                    <input v-model="form.scholarship_no" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Discontinue Date</label>
                <input v-model="form.discontinue_date" type="date" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.report_card_received" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" /> Report Card Received
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.cc_received" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" /> Character Certificate Received
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.tc_received" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" /> Transfer Certificate Received
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.dob_certificate_received" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" /> DOB Certificate Received
                </label>
            </div>
            <div>
                <label class="form-label">Remarks 1</label>
                <input v-model="form.remarks_1" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Remarks 2</label>
                <input v-model="form.remarks_2" type="text" class="form-input" />
            </div>

            <!-- IDs & Financial -->
            <h4 class="form-section-heading">IDs &amp; Financial</h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Student ID</label>
                    <input v-model="form.student_ref_id" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Parents' Anniversary Date</label>
                    <input v-model="form.parents_anniversary_date" type="date" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Biometric Card No.</label>
                    <input v-model="form.biometric_card_no" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Child UID</label>
                    <input v-model="form.child_uid" type="text" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">GR No.</label>
                <input v-model="form.gr_no" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Opening Balance</label>
                <input v-model.number="form.opening_balance" type="number" step="0.01" class="form-input" />
                <p class="mt-1 text-xs text-slate-400">Historical reference only — the ERP computes the student's real fee balance live from Fee Structure and Payments.</p>
            </div>

            <!-- Documents -->
            <details class="rounded-lg border border-slate-100 dark:border-slate-800" open>
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300">Documents</summary>
                <div class="grid grid-cols-1 gap-3 p-3 pt-0 sm:grid-cols-2">
                    <div v-for="doc in STUDENT_DOCUMENT_TYPES" :key="doc.key">
                        <label class="form-label">{{ doc.label }}</label>

                        <!-- Just picked, not saved yet -->
                        <div v-if="documentFileNames[doc.key]" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <span class="truncate text-slate-600 dark:text-slate-300">📎 {{ documentFileNames[doc.key] }}</span>
                            <button type="button" class="shrink-0 rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Remove" @click="clearPendingFile(doc.key)">×</button>
                        </div>

                        <!-- Already uploaded (Edit only) -->
                        <div v-else-if="editing && editing.documents?.[doc.column]" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <button type="button" class="truncate font-medium text-primary-600 hover:underline dark:text-primary-400" @click="downloadDoc(doc)">📄 Uploaded — Download</button>
                            <button type="button" class="shrink-0 rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Delete" :disabled="deletingDoc === doc.key" @click="removeExistingDoc(doc)">{{ deletingDoc === doc.key ? '...' : '×' }}</button>
                        </div>

                        <!-- Nothing chosen or uploaded yet -->
                        <input v-else type="file" accept=".jpg,.jpeg,.png,.pdf" class="form-input" @change="onDocumentFileChange(doc.key, $event)" />
                    </div>
                </div>
            </details>

            <!-- Additional Fields -->
            <details class="rounded-lg border border-slate-100 dark:border-slate-800">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300">Additional Fields (1–10)</summary>
                <div class="grid grid-cols-2 gap-3 p-3 pt-0">
                    <div v-for="n in 10" :key="n">
                        <label class="form-label">Field {{ n }}</label>
                        <input v-model="form[`additional_field_${n}`]" type="text" class="form-input" />
                    </div>
                </div>
            </details>

            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>

    <!-- Detail view -->
    <div v-else class="space-y-5">
        <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400" @click="selectedStudent = null">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
            Back to Students
        </button>

        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                    {{ initials(selectedStudent.name) }}
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ selectedStudent.name }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ selectedStudent.admission_no }} • Class {{ selectedStudent.school_class?.name || '—' }}<span v-if="selectedStudent.section">-{{ selectedStudent.section.name }}</span> • Roll {{ selectedStudent.roll_no || '—' }}
                        <span v-if="selectedStudent.udise_detail?.student_pen || selectedStudent.additional_detail?.pen_no">
                            • PEN {{ selectedStudent.udise_detail?.student_pen || selectedStudent.additional_detail?.pen_no }}
                        </span>
                        <span v-if="selectedStudent.udise_detail?.is_in_udise"> • UDISE</span>
                    </p>
                </div>
            </div>
            <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(selectedStudent.status)">{{ selectedStudent.status }}</span>
        </div>

        <!-- Tabs -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-2 dark:border-slate-800">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    type="button"
                    class="whitespace-nowrap rounded-t-lg px-3.5 py-2.5 text-sm font-medium transition"
                    :class="activeTab === tab ? 'border-b-2 border-primary-600 text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                </button>
            </div>
            <div class="p-5">
                <StudentTabPersonal v-if="activeTab === 'Personal'" :student="selectedStudent" />
                <StudentTabParents v-else-if="activeTab === 'Parents'" :student="selectedStudent" />
                <StudentTabFees v-else-if="activeTab === 'Fees'" :student="selectedStudent" />
                <StudentTabAttendance v-else-if="activeTab === 'Attendance'" :student="selectedStudent" />
                <StudentTabExam v-else-if="activeTab === 'Exam'" :student="selectedStudent" />
                <StudentTabTransport v-else-if="activeTab === 'Transport'" :student="selectedStudent" />
                <StudentTabLibrary v-else-if="activeTab === 'Library'" :student="selectedStudent" />
                <StudentTabDocuments v-else-if="activeTab === 'Documents'" :student="selectedStudent" />
                <StudentTabHealth v-else-if="activeTab === 'Health'" :student="selectedStudent" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import DataTable from '../../components/common/DataTable.vue';
import Pagination from '../../components/common/Pagination.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import IconGlyph from '../../components/common/IconGlyph.vue';
import SearchableSelect from '../../components/common/SearchableSelect.vue';
import StudentExportPanel from '../../components/export/StudentExportPanel.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import { fetchAdmissionsLookups } from '../../api/admissions';
import {
    fetchParentsLite,
    fetchStudentFull,
    fetchStudentsLite,
    invalidatePeopleLookups,
} from '../../api/people';
import client from '../../api/client';
import { initials } from '../../utils/mock';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';
import { erpStore, isAllSessions } from '../../store';
import { downloadExport, downloadStudentDocument } from '../../utils/downloadExport';
import { STUDENT_DOCUMENT_TYPES } from '../../data/studentDocumentTypes';

import StudentTabPersonal from '../../components/students/StudentTabPersonal.vue';
import StudentTabParents from '../../components/students/StudentTabParents.vue';
import StudentTabFees from '../../components/students/StudentTabFees.vue';
import StudentTabAttendance from '../../components/students/StudentTabAttendance.vue';
import StudentTabExam from '../../components/students/StudentTabExam.vue';
import StudentTabTransport from '../../components/students/StudentTabTransport.vue';
import StudentTabLibrary from '../../components/students/StudentTabLibrary.vue';
import StudentTabDocuments from '../../components/students/StudentTabDocuments.vue';
import StudentTabHealth from '../../components/students/StudentTabHealth.vue';

const route = useRoute();
const router = useRouter();
const exporting = ref(false);
const exportModalOpen = ref(false);

function goImport() {
    router.push({ path: '/import-export', query: { type: 'global-workbook-import' } });
}

async function onStudentExport({ format, status, sessions, columns }) {
    exporting.value = true;
    try {
        await downloadExport('student', format, { status, sessions, columns });
        pushToast(`Students exported as ${format.toUpperCase()}.`, 'success');
        exportModalOpen.value = false;
    } finally {
        exporting.value = false;
    }
}

const columns = [
    { key: 'photo', label: 'Photo', type: 'photo' },
    { key: 'admissionNo', label: 'Admission No', type: 'code', prefix: 'ADM' },
    { key: 'roll', label: 'Roll', type: 'number' },
    { key: 'name', label: 'Student Name', type: 'name' },
    { key: 'father', label: 'Father', type: 'name' },
    { key: 'class', label: 'Class', type: 'class' },
    { key: 'section', label: 'Section', type: 'section' },
    { key: 'mobile', label: 'Mobile', type: 'phone' },
    { key: 'status', label: 'Status', type: 'status' },
];

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'class', label: 'Class', type: 'select', options: ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'] },
    { key: 'section', label: 'Section', type: 'select', options: ['A', 'B', 'C', 'D'] },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
    { key: 'gender', label: 'Gender', type: 'select', options: ['Male', 'Female'] },
    { key: 'transport', label: 'Transport', type: 'select', options: ['Yes', 'No'] },
];

const loading = ref(true);
const saving = ref(false);
const students = ref([]);
const classes = ref([]);
const sections = ref([]);
const parents = ref([]);
const transportedStudentIds = ref(new Set());
const customFields = ref([]);
const transportRoutes = ref([]);
const transportStops = ref([]);
const transportAssignment = ref(null);
/** @type {Promise<void> | null} */
let transportCatalogPromise = null;
const transportForm = reactive({
    apply: false,
    route_id: null,
    route_stop_id: null,
    start_date: new Date().toISOString().slice(0, 10),
    fee_start_month: new Date().toISOString().slice(0, 7),
    status: 'Active',
});
const transportStopsForRoute = computed(() =>
    transportStops.value.filter((s) => s.route_id === transportForm.route_id),
);
const parentOptions = computed(() => parents.value.map((parent) => ({
    value: parent.id,
    label: parent.name,
})));
const branches = ref([]);
const branchOptions = computed(() => branches.value.map((b) => ({ value: b.id, label: b.name })));

const formErrors = ref({});
const firstErrors = computed(() => {
    const out = {};
    Object.entries(formErrors.value || {}).forEach(([field, messages]) => {
        if (Array.isArray(messages) && messages.length) out[field] = messages[0];
        else out[field] = typeof messages === 'string' ? messages : 'Invalid value';
    });
    return out;
});

async function load() {
    loading.value = true;
    const [studentRows, lookups, parentRows, transportIds, admissionsBoot] = await Promise.all([
        fetchStudentsLite(),
        fetchAcademicsLookups(),
        fetchParentsLite(),
        client.get('/transport/student-transport', { params: { active_ids: 1 } }),
        fetchAdmissionsLookups(),
    ]);
    students.value = studentRows;
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    parents.value = parentRows;
    transportedStudentIds.value = new Set(Array.isArray(transportIds.data) ? transportIds.data : []);
    customFields.value = admissionsBoot.custom_fields_active || [];
    loading.value = false;
    // Warm routes+stops in one request so Edit doesn't wait on first open.
    ensureTransportCatalog();
}
load();

watch(() => erpStore.currentSession, () => {
    load();
});

/** Prefer the session-history snapshot when a concrete session is selected. */
function sessionSnapshot(s) {
    if (isAllSessions()) return null;
    return s.session_histories?.[0] ?? null;
}

const tableRows = computed(() =>
    students.value.map((s) => {
        const snap = sessionSnapshot(s);
        return {
            id: s.id,
            photo: null,
            admissionNo: s.admission_no,
            roll: snap?.roll_no ?? s.roll_no,
            name: s.name,
            father: s.father?.name || '—',
            class: snap?.class_name || s.school_class?.name || '—',
            section: snap?.section_name || s.section?.name || '—',
            mobile: s.mobile || '—',
            status: snap?.status || s.status,
            gender: s.gender,
            transport: transportedStudentIds.value.has(s.id) ? 'Yes' : 'No',
            __raw: s,
        };
    }),
);

const filterValues = reactive({});
const page = ref(1);
const perPage = 10;
const drawerOpen = ref(route.query.add === '1');
const editing = ref(null);

function blankForm() {
    const additionalFields = {};
    for (let n = 1; n <= 10; n++) additionalFields[`additional_field_${n}`] = '';
    const customValues = {};
    customFields.value.forEach((f) => { customValues[f.id] = ''; });

    return {
        admission_no: '', roll_no: null, name: '', branch_id: null, school_class_id: null, section_id: null,
        gender: null, mobile: '', status: 'Active', father_id: null, mother_id: null, guardian_id: null,
        dob: '', blood_group: '', category: '', religion: '', nationality: '', aadhar_no: '', email: '',
        address: '', city: '', state: '', pincode: '', admission_date: '', fee_start_month: '',
        student_pen: '', name_as_per_aadhaar: '', admission_type: 'New', is_in_udise: false,
        house: '', family: '',
        height: null, weight: null, vision_left: '', vision_right: '', dental_hygiene: '',
        last_school_name: '', last_exam: '', last_exam_year: '', last_exam_status: '', last_exam_marks: '', last_exam_board: '',
        form_no: '', scholarship_no: '', discontinue_date: '',
        report_card_received: false, cc_received: false, tc_received: false, dob_certificate_received: false,
        remarks_1: '', remarks_2: '',
        student_ref_id: '', parents_anniversary_date: '', biometric_card_no: '', child_uid: '', gr_no: '', pen_no: '',
        opening_balance: null,
        custom_field_values: customValues,
        ...additionalFields,
    };
}

const form = reactive(blankForm());

// Held outside `form`/reactive() on purpose — Vue's reactive() deep-proxies nested objects,
// and a Proxy-wrapped File fails native browser checks (e.g. FormData.append expects a real
// File/Blob instance). `documentFileNames` is a small reactive companion holding just the
// filename strings, purely so the template can react to a pick/clear without the File itself
// ever passing through reactive().
let documentFiles = {};
const documentFileNames = reactive({});
const deletingDoc = ref(null);

function onDocumentFileChange(key, event) {
    const file = event.target.files[0] || null;
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

const sectionsForSelectedClass = computed(() => sections.value.filter((s) => s.school_class_id === form.school_class_id));
const classesForSelectedBranch = computed(() => {
    // If `branch_id` exists on class objects, filter; otherwise show all to avoid empty dropdowns.
    const hasBranchMeta = classes.value.some((c) => c && c.branch_id != null);
    if (!hasBranchMeta || !form.branch_id) return classes.value;
    return classes.value.filter((c) => c.branch_id === form.branch_id);
});

function resetFilters() {
    Object.keys(filterValues).forEach((k) => delete filterValues[k]);
}

const filteredRows = computed(() =>
    tableRows.value.filter((row) => {
        if (filterValues.search) {
            const q = filterValues.search.toLowerCase();
            if (!Object.values(row).join(' ').toLowerCase().includes(q)) return false;
        }
        if (filterValues.class && row.class !== filterValues.class) return false;
        if (filterValues.section && row.section !== filterValues.section) return false;
        if (filterValues.status && row.status !== filterValues.status) return false;
        if (filterValues.gender && row.gender !== filterValues.gender) return false;
        if (filterValues.transport && row.transport !== filterValues.transport) return false;
        return true;
    }),
);
watch(filteredRows, () => (page.value = 1));

const pagedRows = computed(() => filteredRows.value.slice((page.value - 1) * perPage, page.value * perPage));

const counts = computed(() => ({
    total: students.value.length,
    active: students.value.filter((s) => s.status === 'Active').length,
    inactive: students.value.filter((s) => s.status === 'Inactive').length,
    new: students.value.filter((s) => s.udise_detail?.admission_type === 'New').length,
    transferred: students.value.filter((s) => s.status === 'Transferred').length,
}));

function toDateInput(value) {
    return value ? value.slice(0, 10) : '';
}

function toMonthInput(value) {
    if (!value) return '';
    const s = String(value);
    if (/^\d{4}-\d{2}/.test(s)) return s.slice(0, 7);
    return '';
}

function resetDocumentPicks() {
    documentFiles = {};
    Object.keys(documentFileNames).forEach((k) => delete documentFileNames[k]);
}

function resetTransportForm() {
    transportAssignment.value = null;
    Object.assign(transportForm, {
        apply: false,
        route_id: null,
        route_stop_id: null,
        start_date: new Date().toISOString().slice(0, 10),
        fee_start_month: new Date().toISOString().slice(0, 7),
        status: 'Active',
    });
}

async function ensureTransportCatalog() {
    if (transportRoutes.value.length) return;
    if (transportCatalogPromise) return transportCatalogPromise;

    transportCatalogPromise = (async () => {
        try {
            // Single request with nested stops — avoids one /stops call per route (was ~N+1).
            const { data: routes } = await client.get('/transport/routes', { params: { with_stops: 1 } });
            const list = Array.isArray(routes) ? routes : [];
            transportRoutes.value = list;
            transportStops.value = list.flatMap((r) =>
                (Array.isArray(r.stops) ? r.stops : []).map((s) => ({
                    ...s,
                    route_id: s.route_id ?? r.id,
                })),
            );
        } catch {
            transportRoutes.value = [];
            transportStops.value = [];
        } finally {
            transportCatalogPromise = null;
        }
    })();

    return transportCatalogPromise;
}

function onTransportRouteChange() {
    transportForm.route_stop_id = null;
}

async function loadStudentTransport(studentId) {
    resetTransportForm();
    if (!studentId) return;
    try {
        const { data } = await client.get('/transport/student-transport', { params: { student_id: studentId } });
        const rows = Array.isArray(data) ? data : [];
        const assignment = rows.find((row) => row.status === 'Active') || rows[0] || null;
        transportAssignment.value = assignment;
        if (assignment) {
            transportForm.apply = true;
            transportForm.route_id = assignment.route_id || assignment.route?.id || null;
            transportForm.route_stop_id = assignment.route_stop_id || assignment.routeStop?.id || null;
            transportForm.start_date = (assignment.start_date || '').toString().slice(0, 10) || new Date().toISOString().slice(0, 10);
            transportForm.fee_start_month = toMonthInput(assignment.fee_start_month)
                || toMonthInput(assignment.start_date)
                || new Date().toISOString().slice(0, 7);
            transportForm.status = assignment.status || 'Active';
        }
    } catch {
        transportAssignment.value = null;
    }
}

async function syncStudentTransport(studentId) {
    if (!studentId) return;

    if (!transportForm.apply) {
        if (transportAssignment.value?.id) {
            await client.delete(`/transport/student-transport/${transportAssignment.value.id}`);
        }
        return;
    }

    if (!transportForm.route_id || !transportForm.route_stop_id) {
        pushToast('Select transport route and stop, or uncheck Assign school transport.', 'error');
        throw new Error('transport_incomplete');
    }

    const payload = {
        student_id: studentId,
        route_id: transportForm.route_id,
        route_stop_id: transportForm.route_stop_id,
        start_date: transportForm.start_date || new Date().toISOString().slice(0, 10),
        fee_start_month: transportForm.fee_start_month
            || (transportForm.start_date || '').toString().slice(0, 7)
            || new Date().toISOString().slice(0, 7),
        status: transportForm.status || 'Active',
    };

    if (transportAssignment.value?.id) {
        await client.put(`/transport/student-transport/${transportAssignment.value.id}`, payload);
    } else {
        await client.post('/transport/student-transport', payload);
    }
}

function openAdd() {
    editing.value = null;
    resetDocumentPicks();
    resetTransportForm();
    Object.assign(form, blankForm());
    form.fee_start_month = new Date().toISOString().slice(0, 7);
    formErrors.value = {};
    drawerOpen.value = true;
    ensureTransportCatalog();
}

function fillFormFromStudent(full) {
    const detail = full.additional_detail || {};
    Object.assign(form, blankForm(), {
        admission_no: full.admission_no,
        roll_no: full.roll_no,
        name: full.name,
        branch_id: full.branch_id ?? null,
        school_class_id: full.school_class_id,
        section_id: full.section_id,
        gender: full.gender,
        mobile: full.mobile,
        status: full.status,
        father_id: full.father_id,
        mother_id: full.mother_id,
        guardian_id: full.guardian_id,
        dob: toDateInput(full.dob),
        blood_group: full.blood_group || '',
        category: full.category || '',
        religion: full.religion || '',
        nationality: full.nationality || '',
        aadhar_no: full.aadhar_no || '',
        email: full.email || '',
        address: full.address || '',
        city: full.city || '',
        state: full.state || '',
        pincode: full.pincode || '',
        admission_date: toDateInput(full.admission_date),
        fee_start_month: toMonthInput(full.fee_start_month) || toMonthInput(full.admission_date),
        student_pen: full.udise_detail?.student_pen || detail.pen_no || '',
        name_as_per_aadhaar: full.udise_detail?.name_as_per_aadhaar || '',
        admission_type: full.udise_detail?.admission_type || 'New',
        is_in_udise: !!full.udise_detail?.is_in_udise,
        house: detail.house || '',
        family: detail.family || '',
        height: detail.height ?? null,
        weight: detail.weight ?? null,
        vision_left: detail.vision_left || '',
        vision_right: detail.vision_right || '',
        dental_hygiene: detail.dental_hygiene || '',
        last_school_name: detail.last_school_name || '',
        last_exam: detail.last_exam || '',
        last_exam_year: detail.last_exam_year || '',
        last_exam_status: detail.last_exam_status || '',
        last_exam_marks: detail.last_exam_marks || '',
        last_exam_board: detail.last_exam_board || '',
        form_no: detail.form_no || '',
        scholarship_no: detail.scholarship_no || '',
        discontinue_date: toDateInput(detail.discontinue_date),
        report_card_received: !!detail.report_card_received,
        cc_received: !!detail.cc_received,
        tc_received: !!detail.tc_received,
        dob_certificate_received: !!detail.dob_certificate_received,
        remarks_1: detail.remarks_1 || '',
        remarks_2: detail.remarks_2 || '',
        student_ref_id: detail.student_ref_id || '',
        parents_anniversary_date: toDateInput(detail.parents_anniversary_date),
        biometric_card_no: detail.biometric_card_no || '',
        child_uid: detail.child_uid || '',
        gr_no: detail.gr_no || '',
        pen_no: full.udise_detail?.student_pen || detail.pen_no || '',
        opening_balance: detail.opening_balance ?? null,
        custom_field_values: { ...blankForm().custom_field_values, ...(full.custom_field_values || {}) },
        ...Object.fromEntries(Array.from({ length: 10 }, (_, i) => [`additional_field_${i + 1}`, detail[`additional_field_${i + 1}`] || ''])),
    });
}

async function openEdit(student) {
    // Open immediately with list-row data; enrich in parallel so the drawer isn't blocked.
    editing.value = student;
    resetDocumentPicks();
    fillFormFromStudent(student);
    formErrors.value = {};
    drawerOpen.value = true;

    const studentId = student.id;
    const [full] = await Promise.all([
        fetchStudentFull(studentId).catch(() => {
            pushToast('Could not load full student details.', 'error');
            return null;
        }),
        ensureTransportCatalog(),
        loadStudentTransport(studentId),
    ]);
    if (full && editing.value?.id === studentId) {
        editing.value = full;
        fillFormFromStudent(full);
    }
}

function buildFormData() {
    const formData = new FormData();

    // Every field is always sent, blank ones as '' — Laravel's ConvertEmptyStringsToNull
    // middleware turns '' back into null server-side, so this preserves the same "blank
    // clears the field" semantics the previous plain-JSON submission had.
    //
    // Booleans need special handling: FormData stringifies values, and `false` becomes the
    // literal string "false" — which Laravel's `boolean` rule does NOT accept (it only
    // accepts true, false, 0, 1, '0', '1'). Sending '1'/'0' instead keeps this working.
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
    if (transportForm.apply && (!transportForm.route_id || !transportForm.route_stop_id)) {
        pushToast('Select transport route and stop, or uncheck Assign school transport.', 'error');
        return;
    }
    // Keep additional_detail.pen_no in sync with UDISE Student PEN for documents/Health.
    form.pen_no = form.student_pen || '';
    saving.value = true;
    formErrors.value = {};
    try {
        const formData = buildFormData();
        let studentId = editing.value?.id || null;
        if (editing.value) {
            // A real HTTP PUT with a multipart body doesn't reliably populate PHP's $_FILES —
            // POST + _method spoofing is Laravel's documented workaround for file uploads on update.
            formData.append('_method', 'PUT');
            await client.post(`/people/students/${editing.value.id}`, formData);
            pushToast('Student updated.', 'success');
        } else {
            const { data } = await client.post('/people/students', formData);
            studentId = data?.id || null;
            pushToast('Student added successfully.', 'success');
        }
        try {
            await syncStudentTransport(studentId);
        } catch (e) {
            if (e?.message !== 'transport_incomplete') {
                pushToast(e?.response?.data?.message || e?.response?.data?.errors?.student_id?.[0] || 'Student saved, but transport assignment failed.', 'error');
            }
        }
        drawerOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } catch (error) {
        const validationErrors = error?.response?.data?.errors;
        if (validationErrors && typeof validationErrors === 'object') {
            formErrors.value = validationErrors;
        }
    } finally {
        saving.value = false;
    }
}

function onBranchPicked() {
    // When branch changes, make sure class/section re-selection is done.
    form.school_class_id = null;
    form.section_id = null;
}

const selectedStudent = ref(null);
const tabs = ['Personal', 'Parents', 'Fees', 'Attendance', 'Exam', 'Transport', 'Library', 'Documents', 'Health'];
const activeTab = ref('Personal');

async function onRowAction({ type, row }) {
    if (type === 'view') {
        // Show list-row data immediately; upgrade with full profile in the background.
        selectedStudent.value = row.__raw;
        activeTab.value = 'Personal';
        try {
            selectedStudent.value = await fetchStudentFull(row.id);
        } catch {
            pushToast('Could not load full student profile.', 'error');
        }
        return;
    }
    if (type === 'edit') {
        await openEdit(row.__raw);
        return;
    }
    if (type === 'delete') {
        if (!window.confirm(`Delete student "${row.name}"? Related certificates, fees, and documents will also be removed.`)) {
            return;
        }
        try {
            await client.delete(`/people/students/${row.id}`);
            students.value = students.value.filter((s) => s.id !== row.id);
            invalidatePeopleLookups();
            pushToast('Student deleted.', 'success');
        } catch {
            // client interceptor already toasts the server message
        }
        return;
    }
    pushToast(`${type[0].toUpperCase()}${type.slice(1)} — demo action on "${row.name}".`, 'info');
}
</script>
