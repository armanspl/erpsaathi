<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Certificates</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate bonafide, transfer, and custom certificates by role.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreateType">
                    <span class="text-base leading-none">+</span> Create certificate type
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!canDownload || zipScope === 'all'" @click="downloadZip('all')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'all' ? 'Zipping...' : 'Download all ZIP' }}
            </button>
            <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!selectedIds.size || zipScope === 'selected'" @click="downloadZip('selected')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ zipScope === 'selected' ? 'Zipping...' : 'Download selected' }}
            </button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model.trim="filters.search" type="text" class="form-input" placeholder="Name, admission ID, or roll no." />
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select v-model="filters.role" class="form-input" @change="onRoleChange">
                            <option :value="null">Select role</option>
                            <option v-for="r in ROLES" :key="r.key" :value="r.key">{{ r.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Certificate type</label>
                        <div class="flex items-center gap-1.5">
                            <select v-model="filters.certificate_type_id" class="form-input" :disabled="!filters.role" @change="load">
                                <option :value="null">{{ filters.role ? 'Select type' : 'Select role first' }}</option>
                                <option v-for="t in typesForRole" :key="t.id" :value="t.id">{{ t.label }}</option>
                            </select>
                            <button
                                v-if="filters.certificate_type_id"
                                type="button"
                                class="shrink-0 rounded-md p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                title="Delete this certificate type"
                                :disabled="deletingType"
                                @click="deleteSelectedType"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input" @change="load">
                            <option :value="null">Status — All</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Transferred">Transferred</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!filters.role || !filters.certificate_type_id" class="text-sm text-slate-500 dark:text-slate-400">
            Select role and certificate type. Student recipients are supported in this release.
        </div>
        <div v-else-if="filters.role !== 'student'" class="text-sm text-slate-500 dark:text-slate-400">
            Recipients for the "{{ roleLabel(filters.role) }}" role aren't wired up yet — student recipients are supported in this release.
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!rows.length" class="px-6 py-16 text-center text-sm text-slate-400">No students found.</div>
            <div v-else-if="!filteredRows.length" class="px-6 py-16 text-center text-sm text-slate-400">No students match "{{ filters.search }}".</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Roll No</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Admission ID</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="r in filteredRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :checked="selectedIds.has(r.student_id)" @change="toggleOne(r.student_id, $event.target.checked)" /></td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.roll_no ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ r.admission_no }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.school_class_name }}<span v-if="r.section_name"> ({{ r.section_name }})</span></td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="{
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400': r.status === 'Active' || !r.status,
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300': r.status === 'Inactive',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': r.status === 'Transferred',
                                    }"
                                >
                                    {{ r.status || 'Active' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Edit person details" @click="openEditPerson(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.5-9.5a2.121 2.121 0 013 3L12 16l-4 1 1-4 8.5-8.5z"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Prepare" @click="openPrepare(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h3M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                    </button>
                                    <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download" :disabled="downloadingId === r.student_id" @click="downloadStudentPdf(r)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create certificate type -->
        <div v-if="createTypeOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="createTypeOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Create certificate type</h2>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Manual types can target system or custom roles. Default student certificates are seeded automatically.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="createTypeOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4">
                    <label class="form-label">Label</label>
                    <input v-model="typeForm.label" type="text" class="form-input" placeholder="e.g. Character certificate" />
                </div>

                <div class="mt-4">
                    <label class="form-label">Roles</label>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">System roles</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <label v-for="r in ROLES" :key="r.key" class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input v-model="typeForm.roles" type="checkbox" :value="r.key" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                                {{ r.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <label class="form-label !mb-0">Custom fields (optional)</label>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="addCustomField">+ Add field</button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <div v-for="(f, i) in typeForm.custom_fields" :key="i" class="flex items-center gap-2">
                            <input v-model="f.key" type="text" class="form-input" placeholder="Key" />
                            <input v-model="f.label" type="text" class="form-input" placeholder="Label" />
                            <select v-model="f.type" class="form-input !w-28">
                                <option value="text">text</option>
                                <option value="number">number</option>
                                <option value="date">date</option>
                            </select>
                            <button type="button" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="typeForm.custom_fields.splice(i, 1)">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="createTypeOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!typeForm.label || !typeForm.roles.length || savingType" @click="saveType">{{ savingType ? 'Creating...' : 'Create type' }}</button>
                </div>
            </div>
        </div>

        <!-- Edit Person Details (student only, in this release) -->
        <SlideOver :open="personDrawerOpen" title="Edit Person Details" @close="personDrawerOpen = false">
            <p class="text-sm text-slate-500 dark:text-slate-400">These are the fields printed on the certificate.</p>
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
            <template #footer>
                <button type="button" class="btn-outline" @click="personDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="savingPerson" @click="savePerson">{{ savingPerson ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>

        <!-- Prepare Certificate -->
        <SlideOver :open="prepareOpen" title="Prepare Certificate" wide @close="prepareOpen = false">
            <div v-if="preparing" class="py-10 text-center text-sm text-slate-400">Loading...</div>
            <template v-else>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Every field shown here is printed on the {{ prepareTypeLabel }} PDF for {{ prepareRow?.name }}. Edit anything below, then download —
                    changes apply only to this download and aren't saved to the student's record.
                </p>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Student &amp; family</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="form-label">Student name</label>
                            <input v-model="prepareForm.recipient_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Father's name</label>
                            <input v-model="prepareForm.father_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Mother's name</label>
                            <input v-model="prepareForm.mother_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Date of birth</label>
                            <input v-model="prepareForm.dob" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Nationality</label>
                            <input v-model="prepareForm.nationality" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Category (SC/ST/OBC)</label>
                            <input v-model="prepareForm.category" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Academic</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Admission ID</label>
                            <input v-model="prepareForm.admission_id" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Roll number</label>
                            <input v-model="prepareForm.roll_number" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Class</label>
                            <input v-model="prepareForm.class" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <input v-model="prepareForm.section" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Branch</label>
                            <input v-model="prepareForm.branch" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Session year</label>
                            <input v-model="prepareForm.session_year" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">PEN No.</label>
                            <input v-model="prepareForm.pen_no" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Aadhar No.</label>
                            <input v-model="prepareForm.aadhar_no" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Admission date</label>
                            <input v-model="prepareForm.admission_date" type="date" class="form-input" />
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Certificate details</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Book No.</label>
                            <input v-model="prepareForm.book_no" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Issue date</label>
                            <input v-model="prepareForm.issue_date" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Application date</label>
                            <input v-model="prepareForm.application_date" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Reason for leaving / purpose</label>
                            <input v-model="prepareForm.purpose" type="text" class="form-input" />
                        </div>
                        <div class="col-span-2">
                            <label class="form-label">Remarks</label>
                            <input v-model="prepareForm.remarks" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Academic history</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Last exam taken, with result</label>
                            <input v-model="prepareForm.last_exam_result" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Failed, if so once/twice</label>
                            <input v-model="prepareForm.failed_status" type="text" class="form-input" />
                        </div>
                        <div class="col-span-2">
                            <label class="form-label">Subjects studied</label>
                            <input v-model="prepareForm.subjects_studied" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Qualified for promotion</label>
                            <input v-model="prepareForm.promotion_status" type="text" class="form-input" placeholder="Yes / No" />
                        </div>
                        <div>
                            <label class="form-label">Promoted to class</label>
                            <input v-model="prepareForm.promoted_class" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Working days</label>
                            <input v-model="prepareForm.working_days" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Days present</label>
                            <input v-model="prepareForm.presence_days" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Fees paid upto</label>
                            <input v-model="prepareForm.fee_paid_upto" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Fee concession</label>
                            <input v-model="prepareForm.fee_concession" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">NCC / Scout / Guide</label>
                            <input v-model="prepareForm.ncc_activities" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Games / extra-curricular</label>
                            <input v-model="prepareForm.games_activities" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">General conduct</label>
                            <input v-model="prepareForm.general_conduct" type="text" class="form-input" />
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn-outline" @click="prepareOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="preparing || downloadingPrepare" @click="downloadFromPrepare">
                    {{ downloadingPrepare ? 'Downloading...' : 'Download PDF' }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const ROLES = [
    { key: 'student', label: 'Student' },
    { key: 'teacher', label: 'Teacher' },
    { key: 'parent', label: 'Parent' },
    { key: 'driver', label: 'Driver' },
    { key: 'staff', label: 'Staff' },
    { key: 'accountant', label: 'Accountant' },
    { key: 'manager', label: 'Manager' },
];

function roleLabel(key) {
    return ROLES.find((r) => r.key === key)?.label || key;
}

const filtersOpen = ref(true);
const filters = reactive({ search: '', role: null, certificate_type_id: null, status: null });
const types = ref([]);
const rows = ref([]);
const loading = ref(false);
const selectedIds = ref(new Set());
const zipScope = ref(null);
const downloadingId = ref(null);

const typesForRole = computed(() => types.value.filter((t) => (t.roles || []).includes(filters.role)));
const canDownload = computed(() => filters.role === 'student' && !!filters.certificate_type_id && filteredRows.value.length > 0);

const filteredRows = computed(() => {
    const term = filters.search.trim().toLowerCase();
    if (!term) return rows.value;
    return rows.value.filter((r) => `${r.name} ${r.admission_no} ${r.roll_no ?? ''}`.toLowerCase().includes(term));
});

async function loadTypes() {
    const { data } = await client.get('/documents/certificate-types');
    types.value = data;
}
loadTypes();

const deletingType = ref(false);
async function deleteSelectedType() {
    const type = types.value.find((t) => t.id === filters.certificate_type_id);
    if (!type) return;
    if (!window.confirm(`Delete certificate type "${type.label}"? This cannot be undone.`)) return;

    deletingType.value = true;
    try {
        await client.delete(`/documents/certificate-types/${type.id}`);
        pushToast(`Certificate type "${type.label}" deleted.`, 'success');
        filters.certificate_type_id = null;
        rows.value = [];
        await loadTypes();
    } catch (e) {
        pushToast(e.response?.data?.message || 'Could not delete this certificate type.', 'error');
    } finally {
        deletingType.value = false;
    }
}

function onRoleChange() {
    filters.certificate_type_id = null;
    rows.value = [];
    selectedIds.value.clear();
}

async function load() {
    selectedIds.value.clear();
    rows.value = [];
    if (filters.role !== 'student' || !filters.certificate_type_id) return;
    loading.value = true;
    try {
        const params = { role: filters.role };
        if (filters.status) params.status = filters.status;
        const { data } = await client.get('/documents/certificates/recipients', { params });
        rows.value = data;
    } finally {
        loading.value = false;
    }
}

const allSelected = computed(() => filteredRows.value.length > 0 && filteredRows.value.every((r) => selectedIds.value.has(r.student_id)));
function toggleAll(checked) {
    if (checked) filteredRows.value.forEach((r) => selectedIds.value.add(r.student_id));
    else filteredRows.value.forEach((r) => selectedIds.value.delete(r.student_id));
}
function toggleOne(id, checked) {
    if (checked) selectedIds.value.add(id);
    else selectedIds.value.delete(id);
}

async function downloadStudentPdf(row, overrides = {}) {
    downloadingId.value = row.student_id;
    try {
        await downloadPdf(
            `/documents/certificates/${filters.certificate_type_id}/${row.student_id}/pdf`,
            `certificate-${row.admission_no}.pdf`,
            overrides,
        );
    } finally {
        downloadingId.value = null;
    }
}

async function downloadZip(scope) {
    if (!filters.certificate_type_id) return;
    zipScope.value = scope;
    try {
        const params = { role: filters.role };
        if (scope === 'selected') params.student_ids = [...selectedIds.value];
        else if (filters.status) params.status = filters.status;
        const response = await client.get(`/documents/certificates/${filters.certificate_type_id}/zip`, { params, responseType: 'blob' });
        triggerBlobDownload(new Blob([response.data], { type: 'application/zip' }), `certificates-${filters.certificate_type_id}.zip`);
    } catch (e) {
        pushToast('Could not generate ZIP for the selected scope.', 'error');
    } finally {
        zipScope.value = null;
    }
}

// --- Create certificate type ---
const createTypeOpen = ref(false);
const savingType = ref(false);
const typeForm = reactive({ label: '', roles: [], custom_fields: [] });

function openCreateType() {
    Object.assign(typeForm, { label: '', roles: [], custom_fields: [] });
    createTypeOpen.value = true;
}
function addCustomField() {
    typeForm.custom_fields.push({ key: '', label: '', type: 'text' });
}
async function saveType() {
    savingType.value = true;
    try {
        await client.post('/documents/certificate-types', typeForm);
        pushToast('Certificate type created.', 'success');
        createTypeOpen.value = false;
        await loadTypes();
    } finally {
        savingType.value = false;
    }
}

// --- Edit Person Details ---
const personDrawerOpen = ref(false);
const savingPerson = ref(false);
const editingStudentId = ref(null);
const personForm = reactive({});

function openEditPerson(row) {
    editingStudentId.value = row.student_id;
    Object.keys(personForm).forEach((k) => delete personForm[k]);
    Object.assign(personForm, {
        name: row.name,
        admission_no: row.admission_no,
        roll_no: row.roll_no,
        gender: null,
        dob: '',
        blood_group: '',
        mobile: '',
    });
    // Recipients only carry the fields printed on the certificate — fetch the full record so
    // saving doesn't clobber fields (address, parents, etc.) that this drawer never shows.
    client.get(`/people/students/${row.student_id}`).then(({ data }) => {
        Object.assign(personForm, { ...data, dob: data.dob ? data.dob.slice(0, 10) : '' });
    });
    personDrawerOpen.value = true;
}

async function savePerson() {
    if (!editingStudentId.value) return;
    savingPerson.value = true;
    try {
        await client.put(`/people/students/${editingStudentId.value}`, personForm);
        pushToast('Person details updated.', 'success');
        personDrawerOpen.value = false;
        await load();
    } finally {
        savingPerson.value = false;
    }
}

// --- Prepare Certificate ---
const PREPARE_FIELDS = [
    'recipient_name', 'father_name', 'mother_name', 'admission_id', 'roll_number', 'class', 'section',
    'branch', 'session_year', 'purpose', 'conduct', 'character', 'nationality', 'category', 'pen_no',
    'aadhar_no', 'remarks', 'book_no', 'last_exam_result', 'failed_status', 'subjects_studied', 'promotion_status',
    'promoted_class', 'working_days', 'presence_days', 'fee_paid_upto', 'fee_concession', 'ncc_activities',
    'games_activities', 'general_conduct',
];

const prepareOpen = ref(false);
const preparing = ref(false);
const downloadingPrepare = ref(false);
const prepareRow = ref(null);
const prepareForm = reactive({});
const prepareTypeLabel = computed(() => types.value.find((t) => t.id === filters.certificate_type_id)?.label || 'certificate');

async function openPrepare(row) {
    prepareRow.value = row;
    Object.keys(prepareForm).forEach((k) => delete prepareForm[k]);
    prepareOpen.value = true;
    preparing.value = true;
    try {
        const { data } = await client.get(`/documents/certificates/${filters.certificate_type_id}/${row.student_id}/prepare`);
        PREPARE_FIELDS.forEach((key) => { prepareForm[key] = data[key] ?? ''; });
        prepareForm.dob = data.dob_iso || '';
        prepareForm.admission_date = data.admission_date_iso || '';
        prepareForm.issue_date = data.issue_date_iso || '';
        prepareForm.application_date = data.application_date_iso || '';
    } finally {
        preparing.value = false;
    }
}

async function downloadFromPrepare() {
    if (!prepareRow.value) return;
    downloadingPrepare.value = true;
    try {
        await downloadStudentPdf(prepareRow.value, { ...prepareForm });
        prepareOpen.value = false;
    } finally {
        downloadingPrepare.value = false;
    }
}
</script>
