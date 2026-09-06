<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Teachers</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage teacher profiles, class assign, and custom fields.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="pushToast('Export columns coming soon.', 'info')">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h14"/></svg>
                    Export columns
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!filtered.length" @click="exportCsv">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openAdd">
                    <span class="text-lg leading-none">+</span> Create Teacher
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Name, phone, email..." />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <button
                    v-for="mode in viewModes"
                    :key="mode.id"
                    type="button"
                    class="rounded-md p-1.5 transition"
                    :class="viewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    :title="mode.label"
                    @click="viewMode = mode.id"
                >
                    <span v-html="mode.icon" />
                </button>
            </div>

            <div v-if="loading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No teachers match your filters.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Name {{ sortArrow('name') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('phone')">Phone {{ sortArrow('phone') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('email')">Email {{ sortArrow('email') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('salary')">Salary {{ sortArrow('salary') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class head</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Assistance teacher</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('customFieldCount')">Custom fields {{ sortArrow('customFieldCount') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="t in paged" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">{{ initials(t.name) }}</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ t.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.email || '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ t.salary != null ? Number(t.salary).toLocaleString('en-IN') : '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ (t.class_assignments || []).length }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ assignmentLabel(t, 'head') }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ assignmentLabel(t, 'assistant') }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ (t.custom_field_values || []).length }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(t.status)">{{ t.status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="openAssign(t)">Assign</button>
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="openSubjects(t)">Subjects</button>
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="openFields(t)">Fields</button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(t)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(t)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- List view -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="t in paged" :key="t.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ t.name }}</div>
                            <div class="text-xs text-slate-400">{{ t.phone || '—' }} · {{ t.email || '—' }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(t.status)">{{ t.status }}</span>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="t in paged" :key="t.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ t.name }}</div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(t.status)">{{ t.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ t.phone || '—' }} · {{ t.email || '—' }}</p>
                        <p class="text-xs text-slate-400">Salary: {{ t.salary != null ? Number(t.salary).toLocaleString('en-IN') : '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <p class="text-xs text-slate-400">Showing {{ showingFrom }}“{{ showingTo }} of {{ filtered.length }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model.number="perPage" class="form-input !w-auto !py-1.5 !text-xs">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page <= 1" @click="page -= 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span>Page {{ page }} of {{ totalPages }}</span>
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page >= totalPages" @click="page += 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Teacher modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit Teacher' : 'Create Teacher' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ editing ? "Updates this teacher's profile." : 'Creates a Teacher profile and linked User account.' }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" class="form-input" required />
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input v-model="form.phone" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input v-model="form.email" type="email" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Salary</label>
                        <input v-model="form.salary" type="number" min="0" step="0.01" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Class (optional)</label>
                        <select v-model="form.school_class_id" class="form-input">
                            <option :value="null">—</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Signature image</label>
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <div class="flex h-16 w-36 items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900">
                                <img v-if="signaturePreview" :src="signaturePreview" alt="Signature preview" class="max-h-full max-w-full object-contain" />
                                <span v-else class="px-2 text-center text-[11px] text-slate-400">No signature</span>
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <input
                                    ref="signatureInput"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                    class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-500/20 dark:file:text-primary-300"
                                    @change="onSignatureSelected"
                                />
                                <p class="text-[11px] text-slate-400">PNG or JPG, max 4 MB. Used on certificates and documents.</p>
                                <button
                                    v-if="signaturePreview"
                                    type="button"
                                    class="text-xs font-medium text-rose-600 hover:underline"
                                    @click="clearSignature"
                                >
                                    Remove signature
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : editing ? 'Save' : 'Create' }}</button>
                </div>
            </div>
        </div>

        <!-- Assign classes modal -->
        <div v-if="assignOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="assignOpen = false" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Assign classes — {{ assignTarget?.name }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="assignOpen = false">×</button>
                </div>
                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    <div v-for="(row, i) in assignRows" :key="i" class="grid grid-cols-12 gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <select v-model="row.school_class_id" class="form-input col-span-4" @change="row.section_id = null">
                            <option :value="null">Class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <select v-model="row.section_id" class="form-input col-span-4">
                            <option :value="null">All sections</option>
                            <option v-for="s in sectionsForClass(row.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <select v-model="row.role" class="form-input col-span-3">
                            <option value="head">Class head</option>
                            <option value="assistant">Assistance teacher</option>
                        </select>
                        <button type="button" class="col-span-1 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove" @click="assignRows.splice(i, 1)">×</button>
                    </div>
                    <button type="button" class="btn-outline !text-xs" @click="assignRows.push({ school_class_id: null, section_id: null, role: 'head' })">+ Add assignment</button>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="assignOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="saveAssignments">{{ saving ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>

        <!-- Subjects modal -->
        <div v-if="subjectsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="subjectsOpen = false" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Subjects — {{ subjectsTarget?.name }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="subjectsOpen = false">×</button>
                </div>
                <div class="flex-1 space-y-2 overflow-y-auto px-5 py-4">
                    <label v-for="s in subjects" :key="s.id" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :value="s.id" v-model="selectedSubjectIds" />
                        {{ s.name }} <span v-if="s.code" class="text-xs text-slate-400">({{ s.code }})</span>
                    </label>
                    <p v-if="!subjects.length" class="text-sm text-slate-400">No subjects defined yet.</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="subjectsOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="saveSubjects">{{ saving ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>

        <!-- Custom fields modal -->
        <div v-if="fieldsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="fieldsOpen = false" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Custom fields — {{ fieldsTarget?.name }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="fieldsOpen = false">×</button>
                </div>
                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    <div v-for="(row, i) in fieldRows" :key="i" class="grid grid-cols-12 gap-2">
                        <input v-model="row.label" type="text" class="form-input col-span-5" placeholder="Field label" />
                        <input v-model="row.value" type="text" class="form-input col-span-6" placeholder="Value" />
                        <button type="button" class="col-span-1 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove" @click="fieldRows.splice(i, 1)">×</button>
                    </div>
                    <button type="button" class="btn-outline !text-xs" @click="fieldRows.push({ label: '', value: '' })">+ Add field</button>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="fieldsOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="saveFields">{{ saving ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import { fetchPeopleLookups, invalidatePeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const filtersOpen = ref(true);
const viewMode = ref('table');
const loading = ref(true);
const saving = ref(false);
const teachers = ref([]);
const classes = ref([]);
const sections = ref([]);
const subjects = ref([]);
const filters = reactive({ search: '', status: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('name');
const sortDir = ref('asc');

const formOpen = ref(false);
const editing = ref(null);
const form = reactive({ name: '', phone: '', email: '', salary: '', status: 'active', school_class_id: null });
const signatureInput = ref(null);
const signatureFile = ref(null);
const signaturePreview = ref('');
const removeExistingSignature = ref(false);

const assignOpen = ref(false);
const assignTarget = ref(null);
const assignRows = ref([]);

const subjectsOpen = ref(false);
const subjectsTarget = ref(null);
const selectedSubjectIds = ref([]);

const fieldsOpen = ref(false);
const fieldsTarget = ref(null);
const fieldRows = ref([]);

function initials(name) {
    return String(name || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('') || '—';
}

function assignmentLabel(teacher, role) {
    const rows = (teacher.class_assignments || []).filter((a) => a.role === role);
    if (!rows.length) return '—';
    return rows.map((a) => a.school_class?.name + (a.section ? `-${a.section.name}` : '')).join(', ');
}

function sectionsForClass(classId) {
    return sections.value.filter((s) => s.school_class_id === classId);
}

const filtered = computed(() => {
    let rows = teachers.value;
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((t) => `${t.name} ${t.phone || ''} ${t.email || ''}`.toLowerCase().includes(term));
    }
    if (filters.status) {
        rows = rows.filter((t) => t.status === filters.status);
    }
    return [...rows].sort((a, b) => {
        let av = sortKey.value === 'customFieldCount' ? (a.custom_field_values || []).length : a[sortKey.value];
        let bv = sortKey.value === 'customFieldCount' ? (b.custom_field_values || []).length : b[sortKey.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const paged = computed(() => {
    const start = (page.value - 1) * perPage.value;
    return filtered.value.slice(start, start + perPage.value);
});
const showingFrom = computed(() => (filtered.value.length ? (page.value - 1) * perPage.value + 1 : 0));
const showingTo = computed(() => Math.min(page.value * perPage.value, filtered.value.length));

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

async function load() {
    loading.value = true;
    try {
        const [lookups, academics] = await Promise.all([
            fetchPeopleLookups({ force: true }),
            fetchAcademicsLookups(),
        ]);
        teachers.value = lookups.teachers || [];
        classes.value = academics.classes || [];
        sections.value = academics.sections || [];
        subjects.value = academics.subjects || [];
    } finally {
        loading.value = false;
    }
}
load();

function resetSignatureState(existingUrl = '') {
    if (signaturePreview.value && signaturePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(signaturePreview.value);
    }
    signatureFile.value = null;
    removeExistingSignature.value = false;
    signaturePreview.value = existingUrl || '';
    if (signatureInput.value) signatureInput.value.value = '';
}

function onSignatureSelected(event) {
    const file = event.target.files?.[0] || null;
    if (!file) return;
    if (signaturePreview.value && signaturePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(signaturePreview.value);
    }
    signatureFile.value = file;
    removeExistingSignature.value = false;
    signaturePreview.value = URL.createObjectURL(file);
}

function clearSignature() {
    if (signaturePreview.value && signaturePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(signaturePreview.value);
    }
    signatureFile.value = null;
    signaturePreview.value = '';
    removeExistingSignature.value = !!editing.value?.signature_url;
    if (signatureInput.value) signatureInput.value.value = '';
}

async function syncSignature(teacherId) {
    if (signatureFile.value) {
        const body = new FormData();
        body.append('signature', signatureFile.value);
        await client.post(`/people/teachers/${teacherId}/signature`, body);
        return;
    }
    if (removeExistingSignature.value) {
        await client.delete(`/people/teachers/${teacherId}/signature`);
    }
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', phone: '', email: '', salary: '', status: 'active', school_class_id: null });
    resetSignatureState();
    formOpen.value = true;
}

function openEdit(teacher) {
    editing.value = teacher;
    Object.assign(form, {
        name: teacher.name,
        phone: teacher.phone || '',
        email: teacher.email || '',
        salary: teacher.salary ?? '',
        status: teacher.status,
        school_class_id: teacher.school_class_id,
    });
    resetSignatureState(teacher.signature_url || '');
    formOpen.value = true;
}

async function save() {
    if (!form.name.trim()) {
        pushToast('Name is required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = { ...form, salary: form.salary === '' ? null : form.salary };
        let response;
        if (editing.value) {
            response = await client.put(`/people/teachers/${editing.value.id}`, payload);
            await syncSignature(editing.value.id);
            pushToast('Teacher updated.', 'success');
        } else {
            response = await client.post('/people/teachers', payload);
            const newId = response.data?.id;
            if (newId) await syncSignature(newId);
            pushToast('Teacher created.', 'success');
        }
        const status = response.data?.account_status;
        if (status === 'created') {
            pushToast('Login account created — ask the teacher to use "Forgot password" with their email to set a password.', 'info');
        } else if (status === 'no_email' && form.email === '') {
            pushToast('No email provided — no login account was created.', 'info');
        }
        formOpen.value = false;
        resetSignatureState();
        invalidatePeopleLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(teacher) {
    if (!window.confirm(`Delete teacher "${teacher.name}"?`)) return;
    teachers.value = teachers.value.filter((t) => t.id !== teacher.id);
    await client.delete(`/people/teachers/${teacher.id}`);
    invalidatePeopleLookups();
    pushToast(`Teacher "${teacher.name}" deleted.`, 'success');
}

function openAssign(teacher) {
    assignTarget.value = teacher;
    assignRows.value = (teacher.class_assignments || []).map((a) => ({
        school_class_id: a.school_class_id,
        section_id: a.section_id,
        role: a.role,
    }));
    assignOpen.value = true;
}

async function saveAssignments() {
    const rows = assignRows.value.filter((r) => r.school_class_id);
    saving.value = true;
    try {
        await client.put(`/people/teachers/${assignTarget.value.id}/assignments`, { assignments: rows });
        pushToast('Class assignments saved.', 'success');
        assignOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

function openSubjects(teacher) {
    subjectsTarget.value = teacher;
    selectedSubjectIds.value = (teacher.subjects || []).map((s) => s.id);
    subjectsOpen.value = true;
}

async function saveSubjects() {
    saving.value = true;
    try {
        await client.put(`/people/teachers/${subjectsTarget.value.id}/subjects`, { subject_ids: selectedSubjectIds.value });
        pushToast('Subjects saved.', 'success');
        subjectsOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

function openFields(teacher) {
    fieldsTarget.value = teacher;
    fieldRows.value = (teacher.custom_field_values || []).map((f) => ({ label: f.label, value: f.value }));
    fieldsOpen.value = true;
}

async function saveFields() {
    const rows = fieldRows.value.filter((r) => r.label.trim());
    saving.value = true;
    try {
        await client.put(`/people/teachers/${fieldsTarget.value.id}/custom-fields`, { fields: rows });
        pushToast('Custom fields saved.', 'success');
        fieldsOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

function exportCsv() {
    const lines = [['Name', 'Phone', 'Email', 'Salary', 'Assigned', 'Class head', 'Assistance teacher', 'Custom fields', 'Status']];
    filtered.value.forEach((t) => {
        lines.push([
            t.name,
            t.phone || '',
            t.email || '',
            t.salary ?? '',
            (t.class_assignments || []).length,
            assignmentLabel(t, 'head'),
            assignmentLabel(t, 'assistant'),
            (t.custom_field_values || []).length,
            t.status,
        ]);
    });
    const csv = lines.map((row) => row.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([`ï»¿${csv}`], { type: 'text/csv;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'teachers.csv';
    a.click();
    URL.revokeObjectURL(url);
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.search, filters.status], () => { page.value = 1; });
</script>
