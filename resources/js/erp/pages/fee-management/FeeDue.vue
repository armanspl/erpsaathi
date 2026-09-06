<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Due</h1>
                <p class="mt-1 text-sm text-slate-500">Outstanding fee balances by student.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="filters.school_class_id" class="form-input" :disabled="!!branches.length && !filters.branch_id">
                        <option :value="null">{{ filters.branch_id || !branches.length ? 'All classes' : 'Select branch first' }}</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select v-model="filters.section_id" class="form-input" :disabled="!filters.school_class_id">
                        <option :value="null">{{ filters.school_class_id ? 'All sections' : 'Select class first' }}</option>
                        <option v-for="s in filterSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission (auto)" />
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600'"
                @click="switchTab(tab.id)"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ summary.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Charged</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.charged) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Paid</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.paid) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total Due</p>
                <p class="mt-1 text-2xl font-bold text-teal-600 dark:text-teal-400">₹{{ money(summary.due) }}</p>
            </div>
        </div>

        <!-- AUTOMATIC -->
        <template v-if="activeTab === 'automatic'">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Scope</p>
                <p class="mt-0.5 text-xs text-slate-400">Choose which months to include in due totals.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="scope === 'till_current' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" @click="setScope('till_current')">Till current month</button>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="scope === 'till_next_month' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" @click="setScope('till_next_month')">Till next month</button>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="scope === 'current_month' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" @click="setScope('current_month')">Current month only</button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Automatic Due Students</h2>
                        <p class="text-xs text-slate-400">From fee structures and receipts for the selected session.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline !py-1.5 !text-xs" @click="pushToast('Reminders coming soon.', 'info')">Send Reminder</button>
                        <Dropdown align="right">
                            <template #trigger>
                                <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting">{{ exporting ? 'Exporting...' : 'Export –¾' }}</button>
                            </template>
                            <template #panel="{ close }">
                                <div class="w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('automatic', 'pdf'); close()">ðŸ“• PDF</button>
                                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('automatic', 'xlsx'); close()">ðŸ“Š Excel (.xlsx)</button>
                                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('automatic', 'csv'); close()">ðŸ“„ CSV</button>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
                <div v-if="loading" class="px-4 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Admission ID</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Student</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Roll No</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Father</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Mother</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Charge</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Paid</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Concession</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Due</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!autoRows.length">
                                <td colspan="10" class="px-4 py-12 text-center text-slate-400">No automatic dues match these filters.</td>
                            </tr>
                            <tr v-for="r in pagedAutoRows" :key="r.student_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-3 font-mono text-xs text-slate-500">{{ r.admission_no }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                                    <p class="text-xs text-slate-400">{{ [r.branch, r.school_class, r.section].filter(Boolean).join(' · ') }}</p>
                                    <p v-if="r.scope_label" class="text-[11px] text-slate-400">{{ r.scope_label }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-500">{{ r.roll_no || '—' }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ r.father || '—' }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ r.mother || '—' }}</td>
                                <td class="px-3 py-3 text-slate-600">₹{{ money(r.total_fee) }}</td>
                                <td class="px-3 py-3 text-slate-600">₹{{ money(r.total_paid) }}</td>
                                <td class="px-3 py-3 text-slate-600">₹{{ money(r.total_discount) }}</td>
                                <td class="px-3 py-3 font-semibold text-teal-600">₹{{ money(r.due) }}</td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="btn-outline !py-1 !text-xs" @click="openAutoDetail(r.student_id)">Details</button>
                                        <button type="button" class="btn-primary !py-1 !text-xs" @click="openDueReceipt(r.student_id, 'automatic')">Receipt</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!loading && autoRows.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                    <span>Showing {{ (autoPage - 1) * perPage + 1 }}–{{ autoRangeEnd }} of {{ autoRows.length }}, sorted by highest due</span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="autoPage <= 1" @click="autoPage--">Prev</button>
                        <span class="px-2">Page {{ autoPage }} / {{ autoTotalPages }}</span>
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="autoPage >= autoTotalPages" @click="autoPage++">Next</button>
                    </div>
                </div>
            </div>
        </template>

        <!-- MANUAL -->
        <template v-else>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Fee Due
                </button>
                <button type="button" class="btn-outline !text-xs" @click="pushToast('Reminders coming soon.', 'info')">Send Reminder</button>
                <Dropdown align="left">
                    <template #trigger>
                        <button type="button" class="btn-outline !text-xs" :disabled="exporting">{{ exporting ? 'Exporting...' : 'Export –¾' }}</button>
                    </template>
                    <template #panel="{ close }">
                        <div class="w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('manual', 'pdf'); close()">ðŸ“• PDF</button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('manual', 'xlsx'); close()">ðŸ“Š Excel (.xlsx)</button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="exportDues('manual', 'csv'); close()">ðŸ“„ CSV</button>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div v-if="loading" class="px-4 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Student</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Fee Types</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Total</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Balance</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Latest Due Date</th>
                                <th class="px-3 py-3 text-xs font-semibold uppercase text-slate-500">Status</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!manualRows.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-400">No manual fee dues yet. Click Create Fee Due to add some.</td>
                            </tr>
                            <template v-for="r in pagedManualRows" :key="r.student_id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-3 py-3">
                                        <button type="button" class="text-left" @click="toggleExpand(r.student_id)">
                                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                                            <p class="text-xs text-slate-400">{{ r.admission_no }} · {{ r.school_class || '—' }} / {{ r.section || '—' }}</p>
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 text-slate-500">{{ r.fee_types || '—' }}</td>
                                    <td class="px-3 py-3">₹{{ money(r.total) }}</td>
                                    <td class="px-3 py-3 font-semibold text-teal-600">₹{{ money(r.balance) }}</td>
                                    <td class="px-3 py-3 text-slate-500">{{ r.latest_due_date || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="r.status === 'Pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700'">{{ r.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" class="btn-outline !py-1 !text-xs" @click="toggleExpand(r.student_id)">{{ expandedId === r.student_id ? 'Hide' : 'Details' }}</button>
                                            <button type="button" class="btn-primary !py-1 !text-xs" @click="openDueReceipt(r.student_id, 'manual')">Receipt</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedId === r.student_id">
                                    <td colspan="7" class="bg-slate-50/80 px-4 py-4 dark:bg-slate-800/30">
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Manual outstanding dues</h3>
                                                <p class="text-xs text-slate-400">Due date {{ r.latest_due_date || '—' }} · {{ r.months_label }} · {{ r.session_name }}</p>
                                                <ul class="mt-3 space-y-2">
                                                    <li v-for="line in r.lines" :key="line.id" class="flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase text-slate-400">{{ line.month_label }}</p>
                                                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ line.fee_head_name }}</p>
                                                            <p class="text-xs text-slate-400">Total ₹{{ money(line.amount) }} · Paid ₹{{ money(line.paid_amount) }} · Balance ₹{{ money(line.balance) }}</p>
                                                        </div>
                                                        <div class="flex gap-1">
                                                            <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Edit" @click="editLine(line)">✎</button>
                                                            <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Delete" @click="deleteLine(line)">ðŸ—‘</button>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Collect Payment</h3>
                                                <div class="mt-3 space-y-3">
                                                    <div>
                                                        <label class="form-label">Amount</label>
                                                        <input v-model.number="collect.amount" type="number" min="0" step="0.01" class="form-input" />
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Payment date</label>
                                                        <input v-model="collect.payment_date" type="date" class="form-input" />
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Payment mode</label>
                                                        <select v-model="collect.payment_mode" class="form-input">
                                                            <option>Cash</option>
                                                            <option>UPI</option>
                                                            <option>Card</option>
                                                            <option>Bank Transfer</option>
                                                            <option>Cheque</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Description</label>
                                                        <textarea v-model="collect.remarks" rows="2" class="form-input" />
                                                    </div>
                                                    <button type="button" class="btn-primary w-full" :disabled="collecting" @click="recordPayment(r)">
                                                        {{ collecting ? 'Recording...' : 'Record Payment' }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-if="!loading && manualRows.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                    <span>Showing {{ (manualPage - 1) * perPage + 1 }}–{{ manualRangeEnd }} of {{ manualRows.length }}, sorted by highest balance</span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="manualPage <= 1" @click="manualPage--">Prev</button>
                        <span class="px-2">Page {{ manualPage }} / {{ manualTotalPages }}</span>
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="manualPage >= manualTotalPages" @click="manualPage++">Next</button>
                    </div>
                </div>
            </div>
        </template>

        <!-- CREATE FEE DUE MODAL -->
        <div v-if="createOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 pt-10 print:hidden">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Create Fee Due</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="createOpen = false">×</button>
                </div>

                <div class="max-h-[75vh] space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="form-label">Branch</label>
                            <select v-model="form.branch_id" class="form-input" @change="form.school_class_id = null; form.section_id = null; form.student_id = null">
                                <option :value="null">Select branch</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Class</label>
                            <select v-model="form.school_class_id" class="form-input" :disabled="!form.branch_id && branches.length > 0" @change="form.section_id = null; form.student_id = null; loadFeeTypes()">
                                <option :value="null">Select class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <select v-model="form.section_id" class="form-input" :disabled="!form.school_class_id" @change="form.student_id = null">
                                <option :value="null">All sections</option>
                                <option v-for="s in formSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Search student</label>
                            <input v-model="form.search" type="search" class="form-input" placeholder="Name / admission" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                        <p v-if="!form.school_class_id" class="text-sm text-slate-400">Select a class to list students.</p>
                        <p v-else-if="!formStudents.length" class="text-sm text-slate-400">No students found.</p>
                        <div v-else class="max-h-36 space-y-1 overflow-y-auto">
                            <label v-for="s in formStudents" :key="s.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                <input v-model="form.student_id" type="radio" :value="s.id" class="text-primary-600" />
                                <span>{{ s.name }} <span class="text-slate-400">({{ s.admission_no }})</span></span>
                            </label>
                        </div>
                        <p v-if="selectedStudent" class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ selectedStudent.name }} · {{ selectedStudent.admission_no }}</p>
                    </div>

                    <div>
                        <label class="form-label">Session Year</label>
                        <input :value="meta?.current_session?.name || '—'" type="text" class="form-input bg-slate-50" readonly />
                    </div>

                    <div>
                        <label class="form-label">Select Month-Year(s) *</label>
                        <div class="mt-2 space-y-3">
                            <div v-for="block in monthBlocks" :key="block.key" class="rounded-xl border border-primary-100 bg-primary-50/40 p-3 dark:border-primary-500/20 dark:bg-primary-500/5">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ block.label }}</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                    <label v-for="m in block.months" :key="m.key" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900">
                                        <input v-model="form.months" type="checkbox" :value="m.key" class="rounded text-primary-600" @change="onMonthsChange" />
                                        {{ m.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="form.apply_all_months" type="checkbox" class="mt-0.5 rounded text-primary-600" @change="onApplyAllToggle" />
                        <span>
                            <span class="font-medium">Apply selected fee types to all applicable months.</span>
                            <span class="mt-1 block text-xs text-slate-400">When checked, enter one Due Amount applied to every selected month (e.g. ₹400 Ã— 2 months = ₹800). Uncheck to see each month's amount from the base fee.</span>
                        </span>
                    </label>

                    <div>
                        <label class="form-label">Due date</label>
                        <input v-model="form.due_date" type="date" class="form-input" />
                    </div>

                    <div v-if="!feeTypes.length" class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                        Select a class to load fee types from Fee Structure (or ensure fee heads exist).
                    </div>
                    <div v-for="ft in feeTypes" :key="ft.key" class="rounded-xl border border-primary-100 p-4 dark:border-primary-500/30">
                        <label class="flex flex-wrap items-center gap-2">
                            <input v-model="form.selected_fee_keys" type="checkbox" :value="ft.key" class="rounded text-primary-600" @change="onFeeTypeToggle(ft)" />
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ ft.label }}</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800">{{ frequencyBadge(ft) }}</span>
                        </label>
                        <p class="mt-1 text-xs text-slate-400">Base: ₹{{ money(ft.amount) }}</p>

                        <div v-if="form.selected_fee_keys.includes(ft.key) && form.months.length" class="mt-3 space-y-2">
                            <template v-if="form.apply_all_months">
                                <p class="text-xs text-slate-500">Applies to all {{ monthsForFee(ft).length }} selected month(s)</p>
                                <div>
                                    <label class="form-label">Due Amount</label>
                                    <input
                                        v-model.number="form.shared[ft.key]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-input max-w-xs"
                                        @input="propagateShared(ft)"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Due by month</p>
                                <div v-for="mk in monthsForFee(ft)" :key="mk" class="flex items-center gap-3">
                                    <span class="w-28 text-xs text-slate-500">{{ monthLabel(mk) }}</span>
                                    <input
                                        :value="form.amounts[amountKey(ft.key, mk)] ?? ft.amount"
                                        type="number"
                                        class="form-input max-w-xs bg-slate-50 dark:bg-slate-800/50"
                                        readonly
                                        tabindex="-1"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Remarks (optional)</label>
                        <textarea v-model="form.remarks" rows="2" class="form-input" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <p class="text-sm text-slate-500">Total due <strong class="text-lg text-primary-600">₹{{ money(formTotal) }}</strong></p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" @click="createOpen = false">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="saving" @click="saveCreate">{{ saving ? 'Saving...' : 'Save' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- AUTOMATIC DUE DETAIL MODAL -->
        <div v-if="autoDetailOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 pt-8">
            <div class="w-full max-w-5xl rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Automatic Due Detail</h2>
                        <p v-if="autoDetail" class="mt-0.5 text-sm text-slate-500">
                            {{ autoDetail.student.name }} · {{ autoDetail.student.admission_no }} · {{ autoDetail.student.school_class || '—' }} · {{ autoDetail.student.section || '—' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="closeAutoDetail">×</button>
                </div>

                <div v-if="autoDetailLoading" class="px-5 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="autoDetail" class="max-h-[75vh] space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Total Charge</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">₹{{ money(autoDetail.summary.total_charge) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Paid</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">₹{{ money(autoDetail.summary.paid) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Concession</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">₹{{ money(autoDetail.summary.concession) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Remaining Due</p>
                            <p class="mt-1 text-xl font-bold text-teal-600">₹{{ money(autoDetail.summary.remaining_due) }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 dark:bg-teal-500/10 dark:text-teal-300">
                        {{ autoDetail.banner }}
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Month-wise Due vs Paid</h3>
                                <p class="text-xs text-slate-400">Current session month summaries.</p>
                            </div>
                            <div class="max-h-72 overflow-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                                        <tr>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Month</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Charge</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Paid</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Concession</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Due</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Categories</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="m in autoDetail.months" :key="m.month_key">
                                            <td class="px-2 py-2 font-medium text-slate-700 dark:text-slate-200">{{ m.month }}</td>
                                            <td class="px-2 py-2">₹{{ money(m.charge) }}</td>
                                            <td class="px-2 py-2">₹{{ money(m.paid) }}</td>
                                            <td class="px-2 py-2">₹{{ money(m.concession) }}</td>
                                            <td class="px-2 py-2 font-semibold text-teal-600">₹{{ money(m.due) }}</td>
                                            <td class="px-2 py-2 text-slate-500">{{ m.categories }}</td>
                                        </tr>
                                        <tr v-if="!autoDetail.months.length">
                                            <td colspan="6" class="px-2 py-6 text-center text-slate-400">No month rows for this scope.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Category-wise Breakdown</h3>
                                <p class="text-xs text-slate-400">Fee head due, paid, and concession totals.</p>
                            </div>
                            <div class="max-h-72 overflow-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                                        <tr>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Fee Head</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Charge</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Paid</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Concession</th>
                                            <th class="px-2 py-2 font-semibold uppercase text-slate-500">Due</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="c in autoDetail.categories" :key="c.fee_head">
                                            <td class="px-2 py-2 font-medium text-slate-700 dark:text-slate-200">{{ c.fee_head }}</td>
                                            <td class="px-2 py-2">₹{{ money(c.charge) }}</td>
                                            <td class="px-2 py-2">₹{{ money(c.paid) }}</td>
                                            <td class="px-2 py-2">₹{{ money(c.concession) }}</td>
                                            <td class="px-2 py-2 font-semibold text-teal-600">₹{{ money(c.due) }}</td>
                                        </tr>
                                        <tr v-if="!autoDetail.categories.length">
                                            <td colspan="5" class="px-2 py-6 text-center text-slate-400">No fee heads for this student.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Per Payment History</h3>
                            <p class="text-xs text-slate-400">Approved payments counted in automatic due computation.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Date</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Source</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Label</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Months</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Mode</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-slate-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-if="!autoDetail.payments.length">
                                        <td colspan="6" class="px-3 py-8 text-center text-slate-400">No records.</td>
                                    </tr>
                                    <tr v-for="(p, i) in autoDetail.payments" :key="i">
                                        <td class="px-3 py-2">{{ p.date }}</td>
                                        <td class="px-3 py-2">{{ p.source }}</td>
                                        <td class="px-3 py-2">{{ p.label }}</td>
                                        <td class="px-3 py-2">{{ p.months }}</td>
                                        <td class="px-3 py-2">{{ p.mode }}</td>
                                        <td class="px-3 py-2 font-medium">₹{{ money(p.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline inline-flex items-center gap-1.5" :disabled="!autoDetailStudentId" @click="openDueReceipt(autoDetailStudentId, 'automatic')">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Receipt
                    </button>
                    <button type="button" class="btn-primary" @click="closeAutoDetail">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Dropdown from '../../components/common/Dropdown.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { fetchFeeLookups, fetchFeeStudentsLite } from '../../api/feeManagement';
import { erpStore } from '../../store';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const tabs = [
    { id: 'automatic', label: 'Automatic' },
    { id: 'manual', label: 'Manual' },
];
const activeTab = ref('automatic');
const loading = ref(false);
const scope = ref('till_current');

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const students = ref([]);
const feeHeads = ref([]);
const meta = ref(null);

const filters = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
});

const autoRows = ref([]);
const manualRows = ref([]);
const summary = reactive({ students: 0, charged: 0, paid: 0, due: 0 });
const expandedId = ref(null);

const perPage = 20;
const autoPage = ref(1);
const manualPage = ref(1);

// Highest outstanding first, then paginate on the client (dues are computed, not a DB column).
const sortedAutoRows = computed(() =>
    [...autoRows.value].sort((a, b) => (Number(b.due) || 0) - (Number(a.due) || 0)),
);
const sortedManualRows = computed(() =>
    [...manualRows.value].sort((a, b) => (Number(b.balance) || 0) - (Number(a.balance) || 0)),
);

const autoTotalPages = computed(() => Math.max(1, Math.ceil(sortedAutoRows.value.length / perPage)));
const manualTotalPages = computed(() => Math.max(1, Math.ceil(sortedManualRows.value.length / perPage)));

const pagedAutoRows = computed(() => {
    const start = (autoPage.value - 1) * perPage;
    return sortedAutoRows.value.slice(start, start + perPage);
});
const pagedManualRows = computed(() => {
    const start = (manualPage.value - 1) * perPage;
    return sortedManualRows.value.slice(start, start + perPage);
});

const autoRangeEnd = computed(() => Math.min(autoPage.value * perPage, sortedAutoRows.value.length));
const manualRangeEnd = computed(() => Math.min(manualPage.value * perPage, sortedManualRows.value.length));

watch(autoTotalPages, (n) => {
    if (autoPage.value > n) autoPage.value = n;
});
watch(manualTotalPages, (n) => {
    if (manualPage.value > n) manualPage.value = n;
});

const collect = reactive({
    amount: 0,
    payment_date: new Date().toISOString().slice(0, 10),
    payment_mode: 'Cash',
    remarks: '',
});
const collecting = ref(false);

const createOpen = ref(false);
const saving = ref(false);
const feeTypes = ref([]);
const autoDetailOpen = ref(false);
const autoDetailLoading = ref(false);
const autoDetail = ref(null);
const autoDetailStudentId = ref(null);
const form = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
    student_id: null,
    months: [],
    apply_all_months: true,
    due_date: new Date().toISOString().slice(0, 10),
    selected_fee_keys: [],
    amounts: {},
    shared: {},
    remarks: '',
});

const filterSections = computed(() => sections.value.filter((s) => s.school_class_id === filters.school_class_id));
const formSections = computed(() => sections.value.filter((s) => s.school_class_id === form.school_class_id));

const formStudents = computed(() => {
    if (!form.school_class_id) return [];
    let rows = students.value.filter((s) => s.status !== 'Inactive' && s.school_class_id === form.school_class_id);
    if (form.branch_id) rows = rows.filter((s) => s.branch_id === form.branch_id);
    if (form.section_id) rows = rows.filter((s) => s.section_id === form.section_id);
    if (form.search.trim()) {
        const q = form.search.trim().toLowerCase();
        rows = rows.filter((s) => `${s.name} ${s.admission_no}`.toLowerCase().includes(q));
    }
    return rows.slice(0, 80);
});

const selectedStudent = computed(() => students.value.find((s) => s.id === form.student_id) || null);

const monthBlocks = computed(() => {
    const blocks = [];
    if (meta.value?.previous_session?.months?.length) {
        blocks.push({ key: 'prev', label: 'Previous session', months: meta.value.previous_session.months, session_id: meta.value.previous_session.id });
    }
    if (meta.value?.current_session?.months?.length) {
        blocks.push({ key: 'curr', label: 'Current session', months: meta.value.current_session.months, session_id: meta.value.current_session.id });
    }
    if (meta.value?.next_session?.months?.length) {
        blocks.push({ key: 'next', label: 'Next session', months: meta.value.next_session.months, session_id: meta.value.next_session.id });
    }
    return blocks;
});

const monthSessionMap = computed(() => {
    const map = {};
    for (const block of monthBlocks.value) {
        for (const m of block.months) map[m.key] = block.session_id;
    }
    return map;
});

const formTotal = computed(() => {
    let total = 0;
    for (const ft of feeTypes.value) {
        if (!form.selected_fee_keys.includes(ft.key)) continue;
        const months = monthsForFee(ft);
        if (!months.length) continue;
        if (form.apply_all_months) {
            const unit = Number(form.shared[ft.key] ?? ft.amount) || 0;
            total += unit * months.length;
        } else {
            for (const mk of months) {
                total += Number(form.amounts[amountKey(ft.key, mk)] ?? ft.amount) || 0;
            }
        }
    }
    return total;
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function frequencyBadge(ft) {
    const raw = (ft.frequency_raw || ft.frequency || 'monthly').toString().replace('_', ' ');
    return raw.charAt(0).toUpperCase() + raw.slice(1).toLowerCase();
}

function switchTab(id) {
    activeTab.value = id;
    expandedId.value = null;
    scheduleReload(0);
}

function setScope(value) {
    scope.value = value;
    if (activeTab.value === 'automatic') reload();
}

function filterParams() {
    const p = {};
    if (filters.branch_id) p.branch_id = filters.branch_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.search.trim()) p.search = filters.search.trim();
    return p;
}

let reloadTimer = null;
let filtersReady = false;

function scheduleReload(delayMs = 0) {
    if (reloadTimer) clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        reload();
    }, delayMs);
}

async function reload() {
    loading.value = true;
    try {
        if (activeTab.value === 'automatic') {
            const { data } = await client.get('/fee-management/due', { params: { ...filterParams(), scope: scope.value } });
            autoRows.value = data.rows || [];
            autoPage.value = 1;
            Object.assign(summary, data.summary || { students: 0, charged: 0, paid: 0, due: 0 });
        } else {
            const { data } = await client.get('/fee-management/due/manual', { params: filterParams() });
            manualRows.value = data.rows || [];
            manualPage.value = 1;
            Object.assign(summary, data.summary || { students: 0, charged: 0, paid: 0, due: 0 });
        }
    } finally {
        loading.value = false;
    }
}

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
    const row = manualRows.value.find((r) => r.student_id === id);
    if (row) collect.amount = row.balance;
}

function openDueReceipt(studentId, type = 'manual') {
    const path = type === 'automatic'
        ? `/fee-management/due/automatic/${studentId}/pdf`
        : `/fee-management/due/manual/${studentId}/pdf`;
    const params = type === 'automatic' ? { scope: scope.value } : {};
    downloadPdf(path, `fee-due-${studentId}.pdf`, params).catch(() => pushToast('Could not open due receipt PDF.', 'error'));
}

async function openAutoDetail(studentId) {
    autoDetailStudentId.value = studentId;
    autoDetailOpen.value = true;
    autoDetailLoading.value = true;
    autoDetail.value = null;
    try {
        const { data } = await client.get(`/fee-management/due/automatic/${studentId}/detail`, {
            params: { scope: scope.value },
        });
        autoDetail.value = data;
    } catch {
        autoDetailOpen.value = false;
    } finally {
        autoDetailLoading.value = false;
    }
}

function closeAutoDetail() {
    autoDetailOpen.value = false;
    autoDetail.value = null;
    autoDetailStudentId.value = null;
}

async function recordPayment(row) {
    if (!collect.amount || collect.amount <= 0) {
        pushToast('Enter a payment amount.', 'error');
        return;
    }
    collecting.value = true;
    try {
        await client.post('/fee-management/due/manual/collect', {
            student_id: row.student_id,
            amount: collect.amount,
            payment_date: collect.payment_date,
            payment_mode: collect.payment_mode,
            remarks: collect.remarks || null,
        });
        pushToast('Payment recorded.', 'success');
        await reload();
        expandedId.value = row.student_id;
        const idx = sortedManualRows.value.findIndex((r) => r.student_id === row.student_id);
        if (idx >= 0) manualPage.value = Math.floor(idx / perPage) + 1;
        const updated = manualRows.value.find((r) => r.student_id === row.student_id);
        collect.amount = updated?.balance || 0;
    } finally {
        collecting.value = false;
    }
}

async function editLine(line) {
    const amount = Number(window.prompt('New amount', String(line.amount)));
    if (!amount || amount <= 0) return;
    await client.put(`/fee-management/due/manual/${line.id}`, { amount, due_date: line.due_date, remarks: line.remarks });
    pushToast('Updated.', 'success');
    await reload();
}

async function deleteLine(line) {
    if (!window.confirm('Delete this due line?')) return;
    await client.delete(`/fee-management/due/manual/${line.id}`);
    pushToast('Deleted.', 'success');
    await reload();
}

async function openCreate() {
    form.branch_id = filters.branch_id;
    form.school_class_id = filters.school_class_id;
    form.section_id = filters.section_id;
    form.search = '';
    form.student_id = null;
    form.months = [];
    form.apply_all_months = true;
    form.due_date = new Date().toISOString().slice(0, 10);
    form.selected_fee_keys = [];
    form.amounts = {};
    form.shared = {};
    form.remarks = '';
    createOpen.value = true;
    const { data } = await client.get('/fee-management/due/meta');
    meta.value = data;
    await loadFeeTypes();
}

async function loadFeeTypes() {
    feeTypes.value = [];
    if (!form.school_class_id) return;

    const params = { school_class_id: form.school_class_id };
    if (form.branch_id) params.branch_id = form.branch_id;
    const { data: plans } = await client.get('/fee-management/structures', { params });
    const list = [];
    const seen = new Set();

    const planRows = Array.isArray(plans) ? plans : plans?.data || [];
    for (const plan of planRows) {
        if (plan.status && plan.status !== 'active') continue;
        for (const item of plan.items || []) {
            const label = item.label || item.fee_head_name || 'Fee';
            const key = `p-${plan.id}-${item.id || label}`;
            if (seen.has(label)) continue;
            seen.add(label);
            // Resolve / ensure fee head id by name
            let headId = item.fee_head_id;
            if (!headId) {
                const head = feeHeads.value.find((h) => h.name === label);
                headId = head?.id || null;
            }
            list.push({
                key,
                fee_head_id: headId,
                label,
                amount: Number(item.amount) || 0,
                frequency: (item.frequency || 'monthly').replace('_', ' '),
                frequency_raw: item.frequency || 'monthly',
            });
        }
    }

    if (!list.length) {
        for (const h of feeHeads.value) {
            list.push({
                key: `h-${h.id}`,
                fee_head_id: h.id,
                label: h.name,
                amount: 0,
                frequency: 'monthly',
                frequency_raw: 'monthly',
            });
        }
    }
    feeTypes.value = list;
    // Auto-select fee types so totals update as soon as months are chosen.
    form.selected_fee_keys = list.map((f) => f.key);
    for (const ft of list) {
        if (form.shared[ft.key] == null) form.shared[ft.key] = Number(ft.amount) || 0;
    }
    refreshFeeAmounts();
}

function amountKey(feeKey, monthKey) {
    return `${feeKey}__${monthKey}`;
}

function monthLabel(key) {
    for (const block of monthBlocks.value) {
        const m = block.months.find((x) => x.key === key);
        if (m) return m.label;
    }
    return key;
}

function monthsForFee(ft) {
    const months = [...form.months].sort();
    if (!months.length) return [];
    const freq = String(ft.frequency_raw || 'monthly').toLowerCase().replace(' ', '_');
    if (freq === 'one_time' || freq === 'annual') {
        return [months[0]];
    }
    return months;
}

function propagateShared(ft) {
    const unit = Number(form.shared[ft.key] ?? ft.amount) || 0;
    form.shared[ft.key] = unit;
    for (const mk of monthsForFee(ft)) {
        form.amounts[amountKey(ft.key, mk)] = unit;
    }
}

function refreshFeeAmounts() {
    for (const ft of feeTypes.value) {
        if (!form.selected_fee_keys.includes(ft.key)) continue;
        if (form.shared[ft.key] == null) form.shared[ft.key] = Number(ft.amount) || 0;
        const unit = form.apply_all_months
            ? (Number(form.shared[ft.key]) || 0)
            : (Number(ft.amount) || 0);
        if (!form.apply_all_months) form.shared[ft.key] = Number(ft.amount) || 0;
        for (const mk of monthsForFee(ft)) {
            form.amounts[amountKey(ft.key, mk)] = unit;
        }
    }
}

function onFeeTypeToggle(ft) {
    if (form.selected_fee_keys.includes(ft.key)) {
        if (form.shared[ft.key] == null) form.shared[ft.key] = Number(ft.amount) || 0;
        refreshFeeAmounts();
    }
}

function onMonthsChange() {
    refreshFeeAmounts();
}

function onApplyAllToggle() {
    refreshFeeAmounts();
}

async function saveCreate() {
    if (!form.student_id) {
        pushToast('Select a student.', 'error');
        return;
    }
    if (!form.months.length) {
        pushToast('Select at least one month.', 'error');
        return;
    }
    if (!form.selected_fee_keys.length) {
        pushToast('Select at least one fee type.', 'error');
        return;
    }
    if (!form.due_date) {
        pushToast('Due date is required.', 'error');
        return;
    }

    refreshFeeAmounts();

    const lines = [];
    for (const ft of feeTypes.value) {
        if (!form.selected_fee_keys.includes(ft.key)) continue;
        for (const mk of monthsForFee(ft)) {
            const amount = Number(form.amounts[amountKey(ft.key, mk)] ?? form.shared[ft.key] ?? ft.amount) || 0;
            if (amount <= 0) continue;
            const sessionId = monthSessionMap.value[mk];
            if (!sessionId) continue;
            lines.push({
                fee_head_id: ft.fee_head_id || null,
                fee_head_name: ft.label,
                month: mk,
                academic_session_id: sessionId,
                amount,
            });
        }
    }
    if (!lines.length) {
        pushToast('Enter amounts greater than zero.', 'error');
        return;
    }

    saving.value = true;
    try {
        const { data } = await client.post('/fee-management/due/manual', {
            student_ids: [form.student_id],
            due_date: form.due_date,
            remarks: form.remarks || null,
            apply_all_months: form.apply_all_months,
            lines,
        });
        pushToast(data.message || 'Fee due saved.', 'success');
        createOpen.value = false;
        activeTab.value = 'manual';
        await reload();
    } finally {
        saving.value = false;
    }
}

const exporting = ref(false);

async function exportDues(kind, format) {
    exporting.value = true;
    try {
        const path = kind === 'automatic' ? '/fee-management/due/automatic/export' : '/fee-management/due/manual/export';
        const params = { ...filterParams(), format };
        if (kind === 'automatic') params.scope = scope.value;

        const response = await client.get(path, { params, responseType: 'blob' });
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : `fee-due-${kind}.${format}`;

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

async function boot() {
    const [lookups, st, fee] = await Promise.all([
        fetchAcademicsLookups(),
        fetchFeeStudentsLite(),
        fetchFeeLookups(),
    ]);
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    students.value = st || [];
    feeHeads.value = fee.heads || [];
    filtersReady = true;
    await reload();
}

watch(
    () => [filters.branch_id, filters.school_class_id, filters.section_id],
    ([branchId, classId], [prevBranchId, prevClassId]) => {
        if (!filtersReady) return;
        if (branchId !== prevBranchId) {
            filters.school_class_id = null;
            filters.section_id = null;
        } else if (classId !== prevClassId) {
            filters.section_id = null;
        }
        scheduleReload(0);
    },
);

watch(
    () => filters.search,
    () => {
        if (!filtersReady) return;
        scheduleReload(300);
    },
);

onMounted(boot);
watch(() => erpStore.currentSession, () => scheduleReload(0));
</script>
