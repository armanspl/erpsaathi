<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Salary Slips</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate monthly salary slips for teaching and non-teaching staff.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openAdvance()">
                    <span class="text-lg leading-none">₹</span> Pay Advance
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Salary Slip
                </button>
            </div>
        </div>

        <!-- Salary Slips / Advance Payments -->
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in [{ id: 'slips', label: 'Salary Slips' }, { id: 'advances', label: 'Advance Payments' }]"
                :key="t.id"
                type="button"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="tab === t.id ? 'border-primary-600 text-primary-700 dark:text-primary-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                @click="tab = t.id"
            >
                {{ t.label }}<span v-if="t.id === 'advances' && advances.length" class="ml-1.5 rounded-full bg-slate-100 px-1.5 text-xs text-slate-500 dark:bg-slate-800">{{ advances.length }}</span>
            </button>
        </div>

        <!-- Advance payments -->
        <div v-if="tab === 'advances'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-4 dark:border-slate-800">
                <input v-model="advFilters.search" type="search" class="form-input" placeholder="Receipt #, staff name" />
                <select v-model="advFilters.month" class="form-input">
                    <option value="">All months</option>
                    <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                </select>
                <select v-model="advFilters.year" class="form-input">
                    <option value="">All years</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <select v-model="advFilters.employee_type" class="form-input">
                    <option value="">All staff types</option>
                    <option value="teacher">Teaching</option>
                    <option value="staff">Non-Teaching</option>
                    <option value="driver">Driver</option>
                </select>
            </div>
            <div v-if="loadingAdvances" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!filteredAdvances.length" class="px-6 py-16 text-center text-sm text-slate-400">
                No advance payments yet. Use <b>Pay Advance</b> to pay a part (or all) of a month's salary in advance.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt #</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Against salary of</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Basic</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Advance</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="a in filteredAdvances" :key="a.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ a.advance_no }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ a.employee_name }}</div>
                                <div class="text-xs text-slate-400">{{ TYPE_LABEL[a.employee_type] || a.employee_type }}<span v-if="a.employee_code"> · {{ a.employee_code }}</span></div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ periodLabel(a.period) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ inr(a.basic_salary) }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(a.amount) }}</div>
                                <div class="text-[11px] text-slate-400">{{ shareLabel(a.amount, a.basic_salary) }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ String(a.paid_on || '').slice(0, 10) }}
                                <div class="text-[11px] text-slate-400">{{ a.payment_mode === 'Bank' ? (a.bank_account_label || 'Bank') : a.payment_mode }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="downloadReceipt(a)">Receipt</button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openAdvance(a)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeAdvance(a)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50 text-sm dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <td colspan="4" class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total advance</td>
                            <td colspan="3" class="px-4 py-2.5 font-bold text-slate-800 dark:text-slate-100">{{ inr(filteredAdvances.reduce((s, a) => s + Number(a.amount || 0), 0)) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <template v-if="tab === 'slips'">
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
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Slip #, staff name" />
                    </div>
                    <div>
                        <label class="form-label">Month</label>
                        <select v-model="filters.month" class="form-input">
                            <option value="">All months</option>
                            <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Year</label>
                        <select v-model="filters.year" class="form-input">
                            <option value="">All years</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Staff type</label>
                        <select v-model="filters.employee_type" class="form-input">
                            <option value="">All staff types</option>
                            <option value="teacher">Teaching</option>
                            <option value="staff">Non-Teaching</option>
                            <option value="driver">Driver</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All statuses</option>
                            <option>Draft</option>
                            <option>Pending</option>
                            <option>Paid</option>
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
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No salary slips found.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('slip_no')">Slip # {{ sortArrow('slip_no') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('employee_name')">Staff {{ sortArrow('employee_name') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Period</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('net_salary')">Net pay {{ sortArrow('net_salary') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status {{ sortArrow('status') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in paged" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ s.slip_no || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ s.employee_name }}</div>
                                <div class="text-xs text-slate-400">{{ TYPE_LABEL[s.employee_type] || s.employee_type }}<span v-if="s.employee_code"> · {{ s.employee_code }}</span></div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ periodLabel(s.period) }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</div>
                                <div v-if="Number(s.advance) > 0" class="text-[11px] text-amber-600 dark:text-amber-400">ADV − {{ inr(s.advance) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                                <div v-if="s.status === 'Paid'" class="mt-1 text-[11px] text-slate-400">
                                    {{ s.payment_mode === 'Bank' ? (s.bank_account_label || 'Bank') : (s.payment_mode || '') }}<span v-if="s.paid_on"> · {{ String(s.paid_on).slice(0, 10) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button v-if="s.status === 'Pending'" type="button" class="btn-outline !py-1 !text-xs" @click="openPay(s)">Mark Paid</button>
                                    <button type="button" class="btn-outline !py-1 !text-xs" title="Changes to this slip, with rollback" @click="historyFor = s">History</button>
                                    <button type="button" title="Download PDF" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="downloadPdf(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- List view -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="s in paged" :key="s.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ s.employee_name }} <span class="text-xs font-normal text-slate-400">· {{ periodLabel(s.period) }}</span></div>
                            <div class="text-xs text-slate-400">{{ s.slip_no }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="s in paged" :key="s.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ s.employee_name }}</div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">{{ s.slip_no }} · {{ periodLabel(s.period) }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <p class="text-xs text-slate-400">Showing {{ showingFrom }}–{{ showingTo }} of {{ filtered.length }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model="perPage" class="form-input !w-auto !py-1.5 !text-xs">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                        <option value="all">All</option>
                    </select>
                    <div v-if="perPage !== 'all'" class="flex items-center gap-2 text-xs text-slate-500">
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
        </template>

        <!-- Create / Edit Salary Slip modal — same columns as a row of the school's salary sheet -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? `Edit Salary Slip ${editing.slip_no || ''}` : 'Create Salary Slip' }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Worked out like the salary sheet: Basic ÷ Days in month × (Present + CL) − ADV. ADV is filled from advances paid for the month.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <!-- Who / which month -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Staff type</label>
                            <select v-model="form.employee_type" class="form-input" @change="onStaffTypeChange">
                                <option value="teacher">Teaching</option>
                                <option value="staff">Non-Teaching</option>
                                <option value="driver">Driver</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Staff member</label>
                            <select v-model="form.employee_id" class="form-input" :disabled="loadingPeople" @change="onMemberChange">
                                <option :value="null">{{ loadingPeople ? 'Loading...' : staffOptions.length ? 'Select staff member' : `No ${TYPE_LABEL[form.employee_type]} staff found` }}</option>
                                <option v-for="p in staffOptions" :key="p.id" :value="p.id">
                                    {{ p.name }}{{ p.code ? ` — ${p.code}` : '' }}{{ p.designation ? ` (${p.designation})` : '' }}{{ p.status === 'inactive' ? ' · inactive' : '' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Month</label>
                            <select v-model="form.month" class="form-input" @change="onMonthChange">
                                <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Year</label>
                            <select v-model.number="form.year" class="form-input" @change="onMonthChange">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- The salary sheet row -->
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Salary sheet row</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <label class="form-label">Basic salary</label>
                                <input v-model.number="form.basic_salary" type="number" min="0" step="1" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Days in month</label>
                                <input v-model.number="form.days_in_month" type="number" min="1" max="31" class="form-input" @input="onAttendanceInput('days')" />
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ MONTHS[Number(form.month) - 1] }} {{ form.year }} has {{ calendarDays }} days</p>
                            </div>
                            <div>
                                <label class="form-label">Absent</label>
                                <input v-model.number="form.absent" type="number" min="0" step="0.5" class="form-input" @input="onAttendanceInput('absent')" />
                            </div>
                            <div>
                                <label class="form-label">CL</label>
                                <input v-model.number="form.cl" type="number" min="0" step="0.5" class="form-input" @input="onAttendanceInput('cl')" />
                            </div>
                            <div>
                                <label class="form-label">Present</label>
                                <input v-model.number="form.present" type="number" min="0" step="0.5" class="form-input" @input="presentTouched = true" />
                                <p class="mt-0.5 text-[11px] text-slate-400">Auto = Days − Absent − CL</p>
                            </div>
                            <div>
                                <label class="form-label">ADV (advance)</label>
                                <input v-model.number="form.advance" type="number" min="0" step="1" class="form-input" />
                                <p v-if="slipAdvances.length" class="mt-0.5 text-[11px] text-amber-600 dark:text-amber-400">Advance paid {{ inr(slipAdvanceTotal) }}</p>
                            </div>
                        </div>
                        <div v-if="slipAdvances.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                            <div class="font-semibold">Advance already paid for {{ MONTHS[Number(form.month) - 1] }} {{ form.year }} — deducted as ADV</div>
                            <ul class="mt-1 space-y-0.5">
                                <li v-for="a in slipAdvances" :key="a.id" class="flex flex-wrap items-center justify-between gap-2">
                                    <span>{{ a.advance_no }} · paid {{ String(a.paid_on || '').slice(0, 10) }} · {{ a.payment_mode }}</span>
                                    <span class="font-semibold">{{ inr(a.amount) }}</span>
                                </li>
                            </ul>
                            <p v-if="Math.abs((Number(form.advance) || 0) - slipAdvanceTotal) > 0.005" class="mt-1 text-amber-700 dark:text-amber-300">
                                ADV ({{ inr(form.advance || 0) }}) is not the same as the advance paid.
                                <button type="button" class="font-semibold underline" @click="form.advance = slipAdvanceTotal">Use {{ inr(slipAdvanceTotal) }}</button>
                            </p>
                        </div>
                        <p v-if="daysOver" class="mt-2 text-xs text-rose-600 dark:text-rose-400">Present + Absent + CL is more than {{ form.days_in_month }} days.</p>

                        <dl class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-xs sm:grid-cols-5 dark:bg-slate-800/60">
                            <div><dt class="text-slate-400">Total days</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ calc.totalDays }}</dd></div>
                            <div><dt class="text-slate-400">Per day</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(calc.perDay) }}</dd></div>
                            <div><dt class="text-slate-400">This month salary</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(calc.thisMonth) }}</dd></div>
                            <div><dt class="text-slate-400">ADV</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">− {{ inr(form.advance || 0) }}</dd></div>
                            <div><dt class="text-slate-400">G. Salary (net)</dt><dd class="text-base font-bold text-emerald-700 dark:text-emerald-400">{{ inr(calc.net) }}</dd></div>
                        </dl>
                    </div>

                    <!-- Optional extras -->
                    <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" :open="!!(form.earnings.length || form.deduction_items.length)">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-100">Extra earnings / deductions <span class="font-normal text-slate-400">(optional)</span></summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Earnings</h4>
                                    <button type="button" class="btn-outline !py-0.5 !text-xs" @click="form.earnings.push({ label: '', amount: 0 })">+ Add</button>
                                </div>
                                <div v-for="(row, i) in form.earnings" :key="`e${i}`" class="mt-2 flex items-center gap-2">
                                    <input v-model="row.label" type="text" class="form-input flex-1" placeholder="e.g. Bonus" />
                                    <input v-model.number="row.amount" type="number" min="0" step="1" class="form-input w-24" />
                                    <button type="button" class="text-slate-400 hover:text-rose-600" @click="form.earnings.splice(i, 1)">×</button>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Deductions</h4>
                                    <button type="button" class="btn-outline !py-0.5 !text-xs" @click="form.deduction_items.push({ label: '', amount: 0 })">+ Add</button>
                                </div>
                                <div v-for="(row, i) in form.deduction_items" :key="`d${i}`" class="mt-2 flex items-center gap-2">
                                    <input v-model="row.label" type="text" class="form-input flex-1" placeholder="e.g. Fine" />
                                    <input v-model.number="row.amount" type="number" min="0" step="1" class="form-input w-24" />
                                    <button type="button" class="text-slate-400 hover:text-rose-600" @click="form.deduction_items.splice(i, 1)">×</button>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="form.status" class="form-input">
                                <option>Draft</option>
                                <option>Pending</option>
                                <option>Paid</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Payment mode</label>
                            <select v-model="form.payment_mode" class="form-input">
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div v-if="form.status === 'Paid'">
                            <label class="form-label">Paid on</label>
                            <input v-model="form.paid_on" type="date" class="form-input" />
                        </div>
                        <div v-if="form.payment_mode === 'Bank'" class="sm:col-span-3">
                            <label class="form-label">Bank account</label>
                            <select v-model="form.bank_account_id" class="form-input">
                                <option :value="null">{{ bankAccounts.length ? 'Select bank account' : 'No bank account added yet' }}</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ bankLabel(b) }}</option>
                            </select>
                            <p class="mt-1 text-xs" :class="bankWarning(form.bank_account_id, calc.net) ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                                {{ bankWarning(form.bank_account_id, calc.net) || (form.status === 'Paid' ? `${inr(calc.net)} will be debited from this account.` : 'The salary is debited from this account when the slip is marked Paid.') }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Remarks</label>
                        <input v-model="form.remarks" type="text" maxlength="255" class="form-input" />
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Net pay: <span class="text-emerald-700 dark:text-emerald-400">{{ inr(calc.net) }}</span></p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="saving || daysOver" @click="save">{{ saving ? 'Saving...' : editing ? 'Save' : 'Create salary slip' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pay / Edit Advance modal -->
        <div v-if="advOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="advOpen = false" />
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ advEditing ? `Edit Advance ${advEditing.advance_no}` : 'Pay Advance Salary' }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Paid now, deducted as ADV from the salary of the month you pick.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="advOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Staff type</label>
                            <select v-model="advForm.employee_type" class="form-input" @change="onAdvTypeChange">
                                <option value="teacher">Teaching</option>
                                <option value="staff">Non-Teaching</option>
                                <option value="driver">Driver</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Staff member</label>
                            <select v-model="advForm.employee_id" class="form-input" :disabled="loadingPeople" @change="onAdvMemberChange">
                                <option :value="null">{{ loadingPeople ? 'Loading...' : advStaffOptions.length ? 'Select staff member' : `No ${TYPE_LABEL[advForm.employee_type]} staff found` }}</option>
                                <option v-for="p in advStaffOptions" :key="p.id" :value="p.id">
                                    {{ p.name }}{{ p.code ? ` — ${p.code}` : '' }}{{ p.designation ? ` (${p.designation})` : '' }}{{ p.status === 'inactive' ? ' · inactive' : '' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Advance for salary month</label>
                            <select v-model="advForm.month" class="form-input">
                                <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Year</label>
                            <select v-model.number="advForm.year" class="form-input">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Basic salary</label>
                            <input v-model.number="advForm.basic_salary" type="number" min="0" step="1" class="form-input" @input="applyAdvShare" />
                            <p class="mt-0.5 text-[11px] text-slate-400">Filled from the staff profile — change it if needed.</p>
                        </div>
                        <div>
                            <label class="form-label">Paid on</label>
                            <input v-model="advForm.paid_on" type="date" class="form-input" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <label class="form-label">How much advance?</label>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <button
                                v-for="opt in ADV_SHARES"
                                :key="opt.id"
                                type="button"
                                class="rounded-lg border px-3 py-1.5 text-sm font-medium transition"
                                :class="advForm.share === opt.id ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                                @click="advForm.share = opt.id; applyAdvShare()"
                            >
                                {{ opt.label }}<span v-if="opt.ratio" class="ml-1 text-xs text-slate-400">{{ inr(advShareAmount(opt.ratio)) }}</span>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="form-label">Advance amount</label>
                                <input v-model.number="advForm.amount" type="number" min="1" step="1" class="form-input" @input="advForm.share = 'custom'" />
                            </div>
                            <dl class="grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                                <div><dt class="text-slate-400">Already advanced</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(advAlready) }}</dd></div>
                                <div><dt class="text-slate-400">Left after this</dt><dd class="font-semibold" :class="advLeft < 0 ? 'text-rose-600' : 'text-emerald-700 dark:text-emerald-400'">{{ inr(advLeft) }}</dd></div>
                            </dl>
                        </div>
                        <p v-if="advLeft < 0" class="mt-2 text-xs text-rose-600 dark:text-rose-400">Total advance for this month is more than the basic salary.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Payment mode</label>
                            <select v-model="advForm.payment_mode" class="form-input">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div v-if="advForm.payment_mode === 'Bank'">
                            <label class="form-label">Bank account</label>
                            <select v-model="advForm.bank_account_id" class="form-input">
                                <option :value="null">{{ bankAccounts.length ? 'Select bank account' : 'No bank account added yet' }}</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ bankLabel(b) }}</option>
                            </select>
                            <p class="mt-1 text-xs" :class="bankWarning(advForm.bank_account_id, advForm.amount) ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                                {{ bankWarning(advForm.bank_account_id, advForm.amount) || `${inr(advForm.amount || 0)} will be debited from this account.` }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Remarks</label>
                            <input v-model="advForm.remarks" type="text" maxlength="255" class="form-input" placeholder="e.g. Medical emergency" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Advance: <span class="text-amber-700 dark:text-amber-400">{{ inr(advForm.amount || 0) }}</span></p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" @click="advOpen = false">Cancel</button>
                        <button type="button" class="btn-primary" :disabled="saving || advLeft < 0" @click="saveAdvance">{{ saving ? 'Saving...' : advEditing ? 'Save' : 'Pay advance & get receipt' }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slip history modal -->
        <div v-if="historyFor" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="historyFor = null" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">History — {{ historyFor.employee_name }} · {{ periodLabel(historyFor.period) }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="historyFor = null">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <SalaryHistoryPanel compact :filters="{ employee_type: historyFor.employee_type, employee_id: historyFor.employee_id, period: historyFor.period }" @rolled-back="load" />
                </div>
            </div>
        </div>

        <!-- Mark paid modal -->
        <div v-if="payOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="payOpen = false" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Mark Paid — {{ paying?.employee_name }}</h2>
                <div class="mt-4 space-y-4">
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                        Net pay <b>{{ inr(paying?.net_salary) }}</b> · {{ periodLabel(paying?.period) }} · {{ paying?.slip_no }}
                    </p>
                    <div>
                        <label class="form-label">Payment Mode</label>
                        <select v-model="payForm.payment_mode" class="form-input">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div v-if="payForm.payment_mode === 'Bank'">
                        <label class="form-label">Bank account</label>
                        <select v-model="payForm.bank_account_id" class="form-input">
                            <option :value="null">{{ bankAccounts.length ? 'Select bank account' : 'No bank account added yet' }}</option>
                            <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ bankLabel(b) }}</option>
                        </select>
                        <p class="mt-1 text-xs" :class="bankWarning(payForm.bank_account_id, paying?.net_salary) ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                            {{ bankWarning(payForm.bank_account_id, paying?.net_salary) || `${inr(paying?.net_salary)} will be debited from this account.` }}
                        </p>
                    </div>
                    <div>
                        <label class="form-label">Paid On</label>
                        <input v-model="payForm.paid_on" type="date" class="form-input" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="payOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="markPaid">{{ saving ? 'Saving...' : 'Confirm Payment' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import client from '../../api/client';
import SalaryHistoryPanel from '../../components/finance/SalaryHistoryPanel.vue';
import { downloadPdf as fetchAndOpenPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import { inr } from '../../utils/money';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const TYPE_LABEL = { teacher: 'Teaching', staff: 'Non-Teaching', driver: 'Driver' };

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const currentYear = new Date().getFullYear();

const filtersOpen = ref(true);
const viewMode = ref('table');
const loading = ref(true);
const saving = ref(false);
const slips = ref([]);
const filters = reactive({ search: '', month: '', year: '', employee_type: '', status: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('period');
const sortDir = ref('desc');

// Years from imported history (e.g. 2024) up to next year.
const years = computed(() => {
    const fromSlips = slips.value.map((s) => Number(String(s.period || '').slice(0, 4))).filter(Boolean);
    const first = Math.min(currentYear - 1, ...fromSlips);
    return Array.from({ length: currentYear + 2 - first }, (_, i) => first + i);
});

function daysIn(year, month) {
    return new Date(Number(year), Number(month), 0).getDate();
}

function blankForm() {
    const month = String(new Date().getMonth() + 1).padStart(2, '0');
    return {
        employee_type: 'teacher',
        employee_id: null,
        month,
        year: currentYear,
        basic_salary: 0,
        days_in_month: daysIn(currentYear, month),
        absent: 0,
        cl: 0,
        present: daysIn(currentYear, month),
        advance: 0,
        earnings: [],
        deduction_items: [],
        payment_mode: 'Bank',
        bank_account_id: defaultBankId(),
        status: 'Pending',
        paid_on: new Date().toISOString().slice(0, 10),
        remarks: '',
    };
}

// Bank accounts (Finance & Payroll › Bank Accounts) a Bank salary is paid from — with balance.
const bankAccounts = ref([]);

function defaultBankId() {
    return bankAccounts.value.length === 1 ? bankAccounts.value[0].id : null;
}

function bankLabel(b) {
    const tail = b.account_number ? ` (A/c ••${String(b.account_number).slice(-4)})` : '';
    return `${b.bank_name} — ${b.account_name}${tail} · Balance ${inr(b.current_balance)}`;
}

/** Warning text when the chosen account can't cover the payment (it's still allowed). */
function bankWarning(bankId, amount) {
    const b = bankAccounts.value.find((x) => x.id === bankId);
    if (!b) return '';
    const net = Number(amount) || 0;
    return Number(b.current_balance) < net ? `Balance ${inr(b.current_balance)} is less than ${inr(net)} — the account will go negative.` : '';
}

async function loadBanks() {
    try {
        const { data } = await client.get('/finance-payroll/bank-accounts');
        bankAccounts.value = Array.isArray(data) ? data : [];
    } catch {
        bankAccounts.value = [];
    }
}

const formOpen = ref(false);
const editing = ref(null);
const form = reactive(blankForm());
const presentTouched = ref(false);
const people = reactive({ teacher: null, staff: null, driver: null });
const loadingPeople = ref(false);

const payOpen = ref(false);
const paying = ref(null);
const payForm = reactive({ payment_mode: 'Bank', bank_account_id: null, paid_on: new Date().toISOString().slice(0, 10) });

const historyFor = ref(null);

const staffOptions = computed(() => people[form.employee_type] || []);
const calendarDays = computed(() => daysIn(form.year, form.month));
const daysOver = computed(() => (Number(form.present) || 0) + (Number(form.absent) || 0) + (Number(form.cl) || 0) > (Number(form.days_in_month) || 0) + 0.001);

/** Same formulas as the sheet (and SalaryMonthlyImportController::computeSlip). */
const calc = computed(() => {
    const days = Math.max(1, Number(form.days_in_month) || 1);
    const basic = Number(form.basic_salary) || 0;
    const totalDays = (Number(form.present) || 0) + (Number(form.cl) || 0);
    const thisMonth = Math.round((basic / days) * totalDays * 100) / 100;
    const extra = form.earnings.reduce((s, r) => s + (Number(r.amount) || 0), 0) - form.deduction_items.reduce((s, r) => s + (Number(r.amount) || 0), 0);
    return {
        totalDays,
        perDay: Math.round((basic / days) * 100) / 100,
        thisMonth,
        net: Math.round((thisMonth + extra - (Number(form.advance) || 0)) * 100) / 100,
    };
});

function periodLabel(period) {
    if (!period) return '—';
    const [y, m] = period.split('-');
    return `${MONTHS[Number(m) - 1] || m} ${y}`;
}

function statusTone(status) {
    if (status === 'Paid') return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30';
    if (status === 'Pending') return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30';
    return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-700/40 dark:text-slate-300 dark:ring-slate-600';
}

const filtered = computed(() => {
    let rows = slips.value;
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((s) => `${s.slip_no || ''} ${s.employee_name || ''} ${s.employee_code || ''}`.toLowerCase().includes(term));
    }
    if (filters.month) rows = rows.filter((s) => String(s.period || '').slice(5, 7) === filters.month);
    if (filters.year) rows = rows.filter((s) => String(s.period || '').slice(0, 4) === String(filters.year));
    if (filters.employee_type) rows = rows.filter((s) => s.employee_type === filters.employee_type);
    if (filters.status) rows = rows.filter((s) => s.status === filters.status);

    return [...rows].sort((a, b) => {
        let av = a[sortKey.value] ?? '';
        let bv = b[sortKey.value] ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const totalPages = computed(() => (perPage.value === 'all' ? 1 : Math.max(1, Math.ceil(filtered.value.length / perPage.value))));
const paged = computed(() => {
    if (perPage.value === 'all') return filtered.value;
    const start = (page.value - 1) * perPage.value;
    return filtered.value.slice(start, start + perPage.value);
});
const showingFrom = computed(() => (filtered.value.length ? (perPage.value === 'all' ? 1 : (page.value - 1) * perPage.value + 1) : 0));
const showingTo = computed(() => (perPage.value === 'all' ? filtered.value.length : Math.min(page.value * perPage.value, filtered.value.length)));

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
        const { data } = await client.get('/finance-payroll/salary-slips');
        slips.value = data;
    } finally {
        loading.value = false;
    }
}
load();
loadBanks();

/** People of one type, fresh from the database (so a just-imported person is listed). */
async function loadPeople(type, force = false) {
    if (people[type] && !force) return;
    loadingPeople.value = true;
    try {
        const { data } = await client.get('/finance-payroll/salary-slips/employees', { params: { type } });
        people[type] = data;
    } catch {
        people[type] = [];
        pushToast('Could not load staff list.', 'error');
    } finally {
        loadingPeople.value = false;
    }
}

function onStaffTypeChange() {
    form.employee_id = null;
    loadPeople(form.employee_type);
}

/** Picking a person fills in their present basic salary from the profile. */
function onMemberChange() {
    const person = staffOptions.value.find((p) => p.id === form.employee_id);
    if (person && person.salary != null && !editing.value) {
        form.basic_salary = person.salary;
    }
}

/** Changing the month sets its number of days (and re-derives Present unless typed by hand). */
function onMonthChange() {
    form.days_in_month = calendarDays.value;
    onAttendanceInput('days');
}

function onAttendanceInput() {
    if (!presentTouched.value) {
        form.present = Math.max(0, (Number(form.days_in_month) || 0) - (Number(form.absent) || 0) - (Number(form.cl) || 0));
    }
}

function openCreate() {
    editing.value = null;
    presentTouched.value = false;
    Object.assign(form, blankForm());
    formOpen.value = true;
    loadPeople(form.employee_type, true);
    loadAdvances();
    loadBanks().then(() => { if (!form.bank_account_id) form.bank_account_id = defaultBankId(); });
}

function openEdit(slip) {
    editing.value = slip;
    const [y, m] = String(slip.period).split('-');
    const extraEarnings = (slip.earnings || []).filter((r) => String(r.label).trim().toLowerCase() !== 'basic');
    Object.assign(form, {
        employee_type: slip.employee_type,
        employee_id: slip.employee_id,
        month: m,
        year: Number(y),
        basic_salary: Number(slip.basic_salary) || 0,
        days_in_month: Number(slip.days_in_month) || daysIn(y, m),
        absent: Number(slip.absent) || 0,
        cl: Number(slip.cl) || 0,
        present: slip.present != null ? Number(slip.present) : (Number(slip.days_in_month) || daysIn(y, m)),
        advance: Number(slip.advance) || 0,
        earnings: extraEarnings.map((r) => ({ ...r })),
        deduction_items: (slip.deduction_items || []).map((r) => ({ ...r })),
        payment_mode: slip.payment_mode || 'Bank',
        bank_account_id: slip.bank_account_id || defaultBankId(),
        status: slip.status,
        paid_on: slip.paid_on ? String(slip.paid_on).slice(0, 10) : new Date().toISOString().slice(0, 10),
        remarks: slip.remarks || '',
    });
    // Keep the stored Present as-is when editing an imported/old slip.
    presentTouched.value = true;
    formOpen.value = true;
    loadPeople(form.employee_type, true);
}

async function save() {
    if (!form.employee_id) {
        pushToast('Select a staff member.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            employee_type: form.employee_type,
            employee_id: Number(form.employee_id),
            period: `${form.year}-${form.month}`,
            basic_salary: Number(form.basic_salary) || 0,
            days_in_month: Number(form.days_in_month) || calendarDays.value,
            absent: Number(form.absent) || 0,
            cl: Number(form.cl) || 0,
            present: Number(form.present) || 0,
            advance: Number(form.advance) || 0,
            earnings: form.earnings.filter((r) => String(r.label).trim()),
            deduction_items: form.deduction_items.filter((r) => String(r.label).trim()),
            payment_mode: form.payment_mode,
            bank_account_id: form.payment_mode === 'Bank' ? form.bank_account_id : null,
            paid_on: form.status === 'Paid' ? form.paid_on : null,
            status: form.status,
            remarks: form.remarks || null,
        };
        if (editing.value) {
            await client.put(`/finance-payroll/salary-slips/${editing.value.id}`, payload);
            pushToast('Salary slip updated.', 'success');
        } else {
            await client.post('/finance-payroll/salary-slips', payload);
            pushToast('Salary slip created.', 'success');
        }
        formOpen.value = false;
        await load();
    } catch (e) {
        const msg = (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
            || e?.response?.data?.message
            || 'Could not save salary slip.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(slip) {
    if (!window.confirm(`Delete salary slip ${slip.slip_no}?\n\nYou can bring it back from its History.`)) return;
    try {
        await client.delete(`/finance-payroll/salary-slips/${slip.id}`);
        slips.value = slips.value.filter((s) => s.id !== slip.id);
        pushToast('Salary slip deleted.', 'success');
    } catch {
        pushToast('Could not delete salary slip.', 'error');
    }
}

function openPay(slip) {
    paying.value = slip;
    Object.assign(payForm, { payment_mode: 'Bank', bank_account_id: slip.bank_account_id || defaultBankId(), paid_on: new Date().toISOString().slice(0, 10) });
    payOpen.value = true;
    loadBanks().then(() => { if (!payForm.bank_account_id) payForm.bank_account_id = defaultBankId(); });
}

async function markPaid() {
    saving.value = true;
    try {
        await client.patch(`/finance-payroll/salary-slips/${paying.value.id}/pay`, {
            ...payForm,
            bank_account_id: payForm.payment_mode === 'Bank' ? payForm.bank_account_id : null,
        });
        pushToast(payForm.payment_mode === 'Bank'
            ? `Salary paid for ${paying.value.employee_name} — ${inr(paying.value.net_salary)} debited from the bank account.`
            : `Salary marked paid for ${paying.value.employee_name}.`, 'success');
        payOpen.value = false;
        await Promise.all([load(), loadBanks()]);
    } catch (e) {
        const msg = (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0]) || e?.response?.data?.message || 'Could not mark paid.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

// ---- Advance payments (paid before salary, deducted as ADV on that month's slip) ----
const ADV_SHARES = [
    { id: 'half', label: 'Half salary', ratio: 0.5 },
    { id: 'full', label: 'Full salary', ratio: 1 },
    { id: 'custom', label: 'Custom amount', ratio: null },
];

const tab = ref('slips');
const advances = ref([]);
const loadingAdvances = ref(true);
const advFilters = reactive({ search: '', month: '', year: '', employee_type: '' });
const advOpen = ref(false);
const advEditing = ref(null);
const advForm = reactive(blankAdvance());

function blankAdvance() {
    return {
        employee_type: 'teacher',
        employee_id: null,
        month: String(new Date().getMonth() + 1).padStart(2, '0'),
        year: currentYear,
        basic_salary: 0,
        share: 'half',
        amount: 0,
        paid_on: new Date().toISOString().slice(0, 10),
        payment_mode: 'Cash',
        bank_account_id: defaultBankId(),
        remarks: '',
    };
}

const filteredAdvances = computed(() => {
    let rows = advances.value;
    if (advFilters.search.trim()) {
        const term = advFilters.search.trim().toLowerCase();
        rows = rows.filter((a) => `${a.advance_no || ''} ${a.employee_name || ''} ${a.employee_code || ''}`.toLowerCase().includes(term));
    }
    if (advFilters.month) rows = rows.filter((a) => String(a.period).slice(5, 7) === advFilters.month);
    if (advFilters.year) rows = rows.filter((a) => String(a.period).slice(0, 4) === String(advFilters.year));
    if (advFilters.employee_type) rows = rows.filter((a) => a.employee_type === advFilters.employee_type);
    return rows;
});

function advancesFor(type, id, period, ignoreId = null) {
    return advances.value.filter((a) => a.employee_type === type && Number(a.employee_id) === Number(id) && a.period === period && a.id !== ignoreId);
}

const advStaffOptions = computed(() => people[advForm.employee_type] || []);
const advAlready = computed(() => (advForm.employee_id
    ? advancesFor(advForm.employee_type, advForm.employee_id, `${advForm.year}-${advForm.month}`, advEditing.value?.id).reduce((s, a) => s + Number(a.amount || 0), 0)
    : 0));
const advLeft = computed(() => {
    const basic = Number(advForm.basic_salary) || 0;
    return basic > 0 ? Math.round((basic - advAlready.value - (Number(advForm.amount) || 0)) * 100) / 100 : 0;
});

// Advances already paid for the person + month on the Create Salary Slip form.
const slipAdvances = computed(() => (form.employee_id ? advancesFor(form.employee_type, form.employee_id, `${form.year}-${form.month}`) : []));
const slipAdvanceTotal = computed(() => Math.round(slipAdvances.value.reduce((s, a) => s + Number(a.amount || 0), 0) * 100) / 100);

// A new slip starts with ADV = the advance paid for that person and month.
watch(() => [form.employee_type, form.employee_id, form.month, form.year, slipAdvanceTotal.value], () => {
    if (formOpen.value && !editing.value) form.advance = slipAdvanceTotal.value;
});

function advShareAmount(ratio) {
    return Math.round((Number(advForm.basic_salary) || 0) * ratio);
}

function applyAdvShare() {
    const opt = ADV_SHARES.find((o) => o.id === advForm.share);
    if (opt?.ratio) advForm.amount = advShareAmount(opt.ratio);
}

function shareLabel(amount, basic) {
    const a = Number(amount) || 0;
    const b = Number(basic) || 0;
    if (!b) return '';
    if (Math.abs(a - b) < 0.5) return 'Full salary';
    if (Math.abs(a - b / 2) < 0.5) return 'Half salary';
    return `${Math.round((a / b) * 100)}% of basic`;
}

async function loadAdvances() {
    loadingAdvances.value = true;
    try {
        const { data } = await client.get('/finance-payroll/salary-advances');
        advances.value = Array.isArray(data) ? data : [];
    } catch {
        advances.value = [];
    } finally {
        loadingAdvances.value = false;
    }
}
loadAdvances();

function onAdvTypeChange() {
    advForm.employee_id = null;
    loadPeople(advForm.employee_type);
}

function onAdvMemberChange() {
    const person = advStaffOptions.value.find((p) => p.id === advForm.employee_id);
    if (person && person.salary != null) {
        advForm.basic_salary = person.salary;
        applyAdvShare();
    }
}

function openAdvance(advance = null) {
    advEditing.value = advance;
    if (advance) {
        const [y, m] = String(advance.period).split('-');
        Object.assign(advForm, {
            employee_type: advance.employee_type,
            employee_id: advance.employee_id,
            month: m,
            year: Number(y),
            basic_salary: Number(advance.basic_salary) || 0,
            share: 'custom',
            amount: Number(advance.amount) || 0,
            paid_on: String(advance.paid_on || '').slice(0, 10),
            payment_mode: advance.payment_mode || 'Cash',
            bank_account_id: advance.bank_account_id || defaultBankId(),
            remarks: advance.remarks || '',
        });
        advForm.share = shareLabel(advance.amount, advance.basic_salary) === 'Full salary' ? 'full' : shareLabel(advance.amount, advance.basic_salary) === 'Half salary' ? 'half' : 'custom';
    } else {
        Object.assign(advForm, blankAdvance());
    }
    advOpen.value = true;
    loadPeople(advForm.employee_type, true);
    loadAdvances();
    loadBanks().then(() => { if (!advForm.bank_account_id) advForm.bank_account_id = defaultBankId(); });
}

async function saveAdvance() {
    if (!advForm.employee_id) {
        pushToast('Select a staff member.', 'error');
        return;
    }
    if (!(Number(advForm.amount) > 0)) {
        pushToast('Enter the advance amount.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            employee_type: advForm.employee_type,
            employee_id: Number(advForm.employee_id),
            period: `${advForm.year}-${advForm.month}`,
            basic_salary: Number(advForm.basic_salary) || 0,
            amount: Number(advForm.amount) || 0,
            paid_on: advForm.paid_on,
            payment_mode: advForm.payment_mode,
            bank_account_id: advForm.payment_mode === 'Bank' ? advForm.bank_account_id : null,
            remarks: advForm.remarks || null,
        };
        let saved;
        if (advEditing.value) {
            ({ data: saved } = await client.put(`/finance-payroll/salary-advances/${advEditing.value.id}`, payload));
            pushToast('Advance updated.', 'success');
        } else {
            ({ data: saved } = await client.post('/finance-payroll/salary-advances', payload));
            pushToast(`Advance of ${inr(saved.amount)} paid to ${saved.employee_name}.`, 'success');
        }
        advOpen.value = false;
        tab.value = 'advances';
        await Promise.all([loadAdvances(), load(), loadBanks()]);
        if (!advEditing.value) downloadReceipt(saved);
    } catch (e) {
        const msg = (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
            || e?.response?.data?.message
            || 'Could not save advance.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function removeAdvance(advance) {
    if (!window.confirm(`Delete advance ${advance.advance_no} (${inr(advance.amount)}) for ${advance.employee_name}?`)) return;
    try {
        await client.delete(`/finance-payroll/salary-advances/${advance.id}`);
        pushToast('Advance deleted.', 'success');
        await Promise.all([loadAdvances(), load(), loadBanks()]);
    } catch (e) {
        const msg = (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0]) || e?.response?.data?.message || 'Could not delete advance.';
        pushToast(msg, 'error');
    }
}

async function downloadReceipt(advance) {
    try {
        await fetchAndOpenPdf(`/finance-payroll/salary-advances/${advance.id}/receipt`, `${advance.advance_no || 'advance-receipt'}.pdf`);
    } catch {
        pushToast('Could not open the advance receipt.', 'error');
    }
}

async function downloadPdf(slip) {
    try {
        await fetchAndOpenPdf(
            `/finance-payroll/salary-slips/${slip.id}/pdf`,
            `${slip.slip_no || 'salary-slip'}.pdf`,
        );
    } catch {
        pushToast('Could not open salary slip PDF.', 'error');
    }
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.search, filters.month, filters.year, filters.employee_type, filters.status], () => { page.value = 1; });
</script>
