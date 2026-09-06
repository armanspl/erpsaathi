<template>
    <div class="space-y-5">
        <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="relative -mb-px pb-3 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span v-if="activeTab === tab.id" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary-600" />
            </button>
        </nav>

        <!-- RECEIPT TAB -->
        <template v-if="activeTab === 'receipt'">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Receipt</h1>
                    <p class="mt-1 text-sm text-slate-500">Collect fees and print receipts.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Student</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <input
                                v-model="quickSearch"
                                type="search"
                                class="form-input !w-56"
                                placeholder="Search name / admission ID"
                                @focus="showQuickResults = true; ensureStudentsLoaded()"
                                @blur="hideQuickResultsSoon"
                            />
                            <div v-if="showQuickResults && quickSearch && quickFilteredStudents.length" class="absolute z-10 mt-1 max-h-56 w-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <button
                                    v-for="s in quickFilteredStudents"
                                    :key="s.id"
                                    type="button"
                                    class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                                    @mousedown.prevent="quickSelectStudent(s)"
                                >
                                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                                    <span class="text-xs text-slate-400">{{ s.admission_no }} · {{ studentClassLabel(s) }}</span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openStudentModal(quickSearch)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8zM19 8v6M22 11h-6"/></svg>
                            Browse
                        </button>
                    </div>
                </div>

                <div v-if="!student" class="mt-4 rounded-xl border border-dashed border-slate-200 px-6 py-16 text-sm text-slate-400 dark:border-slate-700">
                    Select a student to collect fees.
                </div>

                <div v-else class="mt-4 space-y-4">
                    <div class="flex flex-col gap-2 rounded-xl border border-primary-100 bg-primary-50/50 px-4 py-3 text-sm dark:border-primary-500/20 dark:bg-primary-500/10 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ student.name }} <span class="font-normal text-slate-500">- {{ student.admission_no }}</span></p>
                        <p class="text-slate-600 dark:text-slate-300">{{ studentPlaceLabel }}</p>
                        <p class="text-slate-500">{{ transportStatusLabel }}</p>
                    </div>

                    <div v-if="loadingDue" class="py-10 text-center text-sm text-slate-400">Loading fee details...</div>
                    <template v-else>
                        <!-- Months -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-slate-100">Months</h3>
                            <div class="space-y-3">
                                <div
                                    v-for="block in monthBlocks"
                                    :key="block.key"
                                    class="rounded-xl border p-3"
                                    :class="block.key === 'curr' ? 'border-primary-200 bg-primary-50/40 dark:border-primary-500/30 dark:bg-primary-500/10' : 'border-slate-100 dark:border-slate-800'"
                                >
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ block.label }}</p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                                        <label
                                            v-for="m in block.months"
                                            :key="m.key"
                                            class="inline-flex items-center gap-1.5 text-sm"
                                            :class="monthLabelClass(m.key)"
                                        >
                                            <input
                                                type="checkbox"
                                                class="rounded border-slate-300 text-primary-600 disabled:opacity-60"
                                                :value="m.key"
                                                :checked="selectedMonths.includes(m.key)"
                                                :disabled="isMonthLocked(m.key)"
                                                @change="toggleMonth(m.key, $event.target.checked)"
                                            />
                                            <span>{{ m.label }}</span>
                                            <span v-if="isMonthPaid(m.key)" class="text-[10px] font-semibold uppercase tracking-wide text-emerald-600">(Paid)</span>
                                            <span v-else-if="isMonthBeforeFeeStart(m.key)" class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">(Before start)</span>
                                        </label>
                                    </div>
                                </div>
                                <p v-if="!monthBlocks.length" class="text-sm text-slate-400">No session months available. Set academic sessions in Settings.</p>
                            </div>
                        </div>

                        <!-- Transport -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-slate-100">Transport</h3>
                            <label class="mb-3 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input v-model="transport.apply" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                                Apply school transport for this receipt
                            </label>
                            <div v-if="transport.apply" class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="form-label">Route</label>
                                    <select v-model="transport.route_id" class="form-input" @change="onTransportRouteChange">
                                        <option :value="null">Select route</option>
                                        <option v-for="r in transportRoutes" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Address</label>
                                    <select v-model="transport.stop_id" class="form-input" :disabled="!transport.route_id" @change="onTransportStopChange">
                                        <option :value="null">Select address</option>
                                        <option v-for="s in transportStops" :key="s.id" :value="s.id">{{ s.stop_name }}{{ s.fare != null ? ` (₹${money(s.fare)})` : '' }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Amount</label>
                                    <input :value="money(transportDue)" type="text" class="form-input bg-slate-50 dark:bg-slate-800" readonly />
                                    <p v-if="transportFare" class="mt-1 text-xs text-slate-400">₹{{ money(transportFare) }}/month</p>
                                </div>
                                <div>
                                    <label class="form-label">Paid</label>
                                    <input v-model.number="transport.fee" type="number" min="0" step="0.01" class="form-input" placeholder="0" />
                                </div>
                            </div>
                        </div>

                        <!-- Fee entry -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Fee entry</h3>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input v-model="applyAllMonths" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                                    Apply one fee-type selection to all selected months
                                </label>
                            </div>

                            <div v-if="!due?.breakdown?.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                                No fee structure for this student/session. Set one up in Fee Structure first.
                            </div>
                            <div v-else-if="!chargeableBreakdown.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                                All fee types for the selected months are settled. Pick unpaid months to collect remaining dues.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in chargeableBreakdown"
                                    :key="item.fee_head_id"
                                    class="flex flex-col gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <label class="flex min-w-0 flex-1 items-start gap-3">
                                        <input v-model="selectedHeadIds" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" :value="item.fee_head_id" @change="syncFeePaid(item)" />
                                        <span>
                                            <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ item.fee_head_name }}</span>
                                            <span class="text-xs text-slate-400">{{ frequencyLabel(item.frequency) }} · ₹{{ money(item.amount) }}</span>
                                            <span class="mt-0.5 block text-xs text-amber-600">{{ feeStatusLabel(item) }}</span>
                                        </span>
                                    </label>
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="form-label !mb-1">Discount</label>
                                            <input
                                                v-model.number="discounts[item.fee_head_id]"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="form-input w-28"
                                                :disabled="!selectedHeadIds.includes(item.fee_head_id)"
                                            />
                                        </div>
                                        <div>
                                            <label class="form-label !mb-1">Paid</label>
                                            <input
                                                v-model.number="amounts[item.fee_head_id]"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="form-input w-28"
                                                :disabled="!selectedHeadIds.includes(item.fee_head_id)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="form-label">Payment date</label>
                                    <input v-model="paymentDate" type="date" class="form-input" />
                                </div>
                                <div>
                                    <label class="form-label">Payment mode</label>
                                    <select v-model="paymentMode" class="form-input">
                                        <option>Cash</option>
                                        <option>UPI</option>
                                        <option>Card</option>
                                        <option>Bank Transfer</option>
                                        <option>Cheque</option>
                                    </select>
                                </div>
                                <div v-if="paymentMode === 'Bank Transfer'">
                                    <label class="form-label">Bank account</label>
                                    <select v-model.number="bankAccountId" class="form-input">
                                        <option :value="null">Select bank account</option>
                                        <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.account_name }} — {{ a.bank_name }}</option>
                                    </select>
                                    <p v-if="!bankAccounts.length" class="mt-1 text-xs text-slate-400">
                                        No bank accounts yet — add one under Finance &amp; Payroll → Bank Accounts.
                                    </p>
                                </div>
                                <div>
                                    <label class="form-label">Reference / transaction no.</label>
                                    <input v-model="referenceNo" type="text" class="form-input" placeholder="Cheque / UTR / txn no." />
                                </div>
                                <div>
                                    <label class="form-label">Remarks</label>
                                    <input v-model="remarks" type="text" class="form-input" placeholder="Optional notes" />
                                </div>
                            </div>
                        </div>

                        <!-- Payment summary -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">Total payable (fee - discount)</p>
                                    <p class="text-3xl font-bold text-primary-600">₹{{ money(summary.payable) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                                        <span>Base fee: ₹{{ money(summary.base) }}</span>
                                        <span class="text-emerald-600">Discount: -₹{{ money(summary.discount) }}</span>
                                        <span class="text-primary-600">Applied now: ₹{{ money(summary.applied) }}</span>
                                        <span>Total received: ₹{{ money(summary.received) }}</span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-rose-600">Due after payment: ₹{{ money(summary.dueAfter) }}</p>
                                </div>
                                <button type="button" class="btn-primary shrink-0" :disabled="!canSubmit || collecting" @click="collect">
                                    {{ collecting ? 'Submitting...' : 'Submit fee' }}
                                </button>
                            </div>
                        </div>

                        <!-- Submitted fees for this student -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Submitted fees</h3>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <button type="button" class="btn-outline !py-1 !text-xs" :class="multiPrint === 2 ? '!border-primary-500 !text-primary-600' : ''" @click="multiPrint = 2">2 in 1</button>
                                    <button type="button" class="btn-outline !py-1 !text-xs" :class="multiPrint === 4 ? '!border-primary-500 !text-primary-600' : ''" @click="multiPrint = 4">4 in 1</button>
                                    <span>Select {{ multiPrint }} receipts for multi-up print</span>
                                    <button v-if="studentReceiptSelected.length === multiPrint" type="button" class="btn-primary !py-1 !text-xs" @click="printStudentSelected">Print selected</button>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-left text-sm">
                                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                                        <tr>
                                            <th class="px-3 py-2"><input type="checkbox" class="rounded" :checked="studentReceiptsAllSelected" @change="toggleStudentReceiptsAll" /></th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt</th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Date</th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Months</th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Advance</th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</th>
                                            <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Due</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-if="studentReceiptsLoading">
                                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                                        </tr>
                                        <tr v-else-if="!studentReceipts.length">
                                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">No receipts for this student yet.</td>
                                        </tr>
                                        <tr v-for="p in studentReceipts" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                            <td class="px-3 py-3"><input v-model="studentReceiptSelected" type="checkbox" class="rounded" :value="p.id" /></td>
                                            <td class="px-3 py-3 font-mono text-xs text-slate-600">{{ p.receipt_no }}</td>
                                            <td class="px-3 py-3 text-slate-500">{{ formatDateTime(p.payment_date || p.created_at) }}</td>
                                            <td class="px-3 py-3 text-slate-500">{{ p.months || '—' }}</td>
                                            <td class="px-3 py-3 text-amber-600">₹{{ money(p.advance) }}</td>
                                            <td class="px-3 py-3 font-semibold text-primary-600">₹{{ money(p.paid_net ?? p.amount) }}</td>
                                            <td class="px-3 py-3 text-rose-600">{{ formatStudentDue() }}</td>
                                            <td class="px-3 py-3 text-right">
                                                <div class="inline-flex gap-1">
                                                    <button type="button" class="btn-outline !px-2 !py-1" title="Print" @click="openDepositReceipt(p.id)">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                                                    </button>
                                                    <button type="button" class="btn-outline !px-2 !py-1" title="Download" @click="downloadDepositReceipt(p.id)">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- HISTORY / REPORT shared filters -->
        <template v-else>
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-3 lg:grid-cols-[1fr_auto_140px_160px]">
                    <div>
                        <label class="form-label">Single Student Search</label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                                <input v-model="filters.search" type="search" class="form-input !pl-9" placeholder="Search a student by name or admission ID" @keyup.enter="applyFilters" />
                            </div>
                            <button type="button" class="btn-outline" @click="applyFilters">Search</button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Report Period</label>
                        <select v-model="filters.period" class="form-input">
                            <option value="day">day</option>
                            <option value="month">month</option>
                            <option value="year">year</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Select Date</label>
                        <input v-model="filters.date" type="date" class="form-input" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input" @change="filters.school_class_id = null; filters.section_id = null">
                            <option :value="null">All branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id && branches.length > 0" @change="filters.section_id = null">
                            <option :value="null">{{ filters.branch_id || !branches.length ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input" :disabled="!filters.branch_id && branches.length > 0">
                            <option :value="null">All sections</option>
                            <option v-for="s in filterSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ activeTab === 'report' ? 'Sort By' : 'Order By' }}</label>
                        <select v-model="filters.order_by" class="form-input">
                            <option value="submitted">submitted</option>
                            <option value="payment_date">payment_date</option>
                            <option value="amount">amount</option>
                            <option value="receipt_no">receipt_no</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_120px_1.2fr_auto]">
                    <div>
                        <label class="form-label">Min Amount (₹)</label>
                        <input v-model.number="filters.min_amount" type="number" min="0" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Max Amount (₹)</label>
                        <input v-model="filters.max_amount" type="number" min="0" class="form-input" placeholder="No limit" />
                    </div>
                    <div>
                        <label class="form-label">Order</label>
                        <select v-model="filters.order" class="form-input">
                            <option value="desc">desc</option>
                            <option value="asc">asc</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="btn-primary inline-flex w-full items-center justify-center gap-1.5" :disabled="historyLoading" @click="applyFilters">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                            Apply
                        </button>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="btn-outline inline-flex w-full items-center justify-center gap-1.5" @click="exportCsv">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- HISTORY -->
            <template v-if="activeTab === 'history'">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <button type="button" class="btn-outline !py-1 !text-xs" :class="multiPrint === 2 ? '!border-primary-500 !text-primary-600' : ''" @click="multiPrint = 2">2 in 1</button>
                    <button type="button" class="btn-outline !py-1 !text-xs" :class="multiPrint === 4 ? '!border-primary-500 !text-primary-600' : ''" @click="multiPrint = 4">4 in 1</button>
                    <span>Select {{ multiPrint }} receipts for multi-up print</span>
                    <button v-if="selectedIds.length === multiPrint" type="button" class="btn-primary !py-1 !text-xs" @click="printSelected">Print selected</button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div v-if="historyLoading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-3 py-3"><input type="checkbox" class="rounded" :checked="allSelected" @change="toggleSelectAll" /></th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Date</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Months</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee types</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Advance</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Due</th>
                                    <th class="px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-if="!historyRows.length">
                                    <td colspan="11" class="px-4 py-12 text-center text-slate-400">No receipts match these filters.</td>
                                </tr>
                                <tr v-for="p in historyRows" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-3 py-3"><input v-model="selectedIds" type="checkbox" class="rounded" :value="p.id" /></td>
                                    <td class="px-3 py-3 font-mono text-xs text-slate-700">{{ p.receipt_no }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap text-slate-500">{{ formatDateTime(p.payment_date || p.created_at) }}</td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold uppercase text-slate-800 dark:text-slate-100">{{ p.student?.name }}</div>
                                        <div class="text-xs text-slate-400">{{ p.student?.admission_no }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-slate-500">{{ historyMonths(p) }}</td>
                                    <td class="max-w-[140px] truncate px-3 py-3 text-xs lowercase text-slate-500" :title="p.fee_types">{{ p.fee_types }}</td>
                                    <td class="px-3 py-3 font-medium text-amber-600">₹{{ money(p.advance) }}</td>
                                    <td class="px-3 py-3 font-semibold text-primary-600">₹{{ money(p.paid_net ?? p.amount) }}</td>
                                    <td class="px-3 py-3 font-medium text-rose-600">{{ p.due_amount != null ? `₹${money(p.due_amount)}` : '₹0' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold capitalize ring-1 ring-inset" :class="historyStatusClass(p)">{{ historyStatusLabel(p) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button type="button" class="btn-outline inline-flex items-center gap-1 !px-2.5 !py-1 !text-xs" title="Print" @click="openDepositReceipt(p.id)">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                                                Print
                                            </button>
                                            <template v-if="p.status !== 'Rolled Back'">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-600 transition hover:bg-sky-100 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-400"
                                                    title="Edit"
                                                    @click="openEdit(p)"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4z"/></svg>
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400"
                                                    :disabled="rollbackingId === p.id"
                                                    title="Rollback"
                                                    @click="openRollback(p)"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v5h5"/></svg>
                                                    {{ rollbackingId === p.id ? '...' : 'Rollback' }}
                                                </button>
                                                <button
                                                    v-if="p.edited_at"
                                                    type="button"
                                                    class="inline-flex items-center rounded-lg border border-slate-200 p-1.5 text-slate-500 transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                                    title="View edit history"
                                                    @click="openAudits(p)"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>
                                                </button>
                                            </template>
                                            <button
                                                v-else
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                title="View rollback details"
                                                @click="openAudits(p)"
                                            >
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01"/></svg>
                                                View rollback details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- REPORT -->
            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary-100 bg-primary-50/70 px-4 py-3 text-sm dark:border-primary-500/20 dark:bg-primary-500/10">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">
                        Total collected:
                        <span class="text-primary-700 dark:text-primary-300">₹{{ money(reportTotal) }}</span>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Grouped automatically by the selected academic scope.</p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div v-if="historyLoading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-center text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-slate-800 dark:text-slate-100">Group</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Receipts</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Payable</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Discount</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Extra</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Paid</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Due</th>
                                    <th class="px-4 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Rollback</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-if="!reportGroups.length">
                                    <td colspan="8" class="px-4 py-12 text-slate-400">No report data for these filters.</td>
                                </tr>
                                <tr v-for="(g, i) in reportGroups" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3 text-left font-medium text-slate-800 dark:text-slate-100">{{ g.group }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ g.receipts }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">₹{{ money(g.payable) }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">₹{{ money(g.discount) }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">₹{{ money(g.extra) }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">₹{{ money(g.paid) }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">₹{{ money(g.due) }}</td>
                                    <td class="px-4 py-3 font-medium text-rose-600">₹{{ money(g.rollback) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </template>

        <!-- Select student modal -->
        <div v-if="studentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="studentModalOpen = false" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Select student</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="studentModalOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div>
                            <label class="form-label">Branch</label>
                            <select v-model="modal.branch_id" class="form-input" @change="modal.school_class_id = null; modal.section_id = null">
                                <option :value="null">All branches</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Class</label>
                            <select v-model="modal.school_class_id" class="form-input" @change="modal.section_id = null">
                                <option :value="null">All classes</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <select v-model="modal.section_id" class="form-input">
                                <option :value="null">All sections</option>
                                <option v-for="s in modalSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Search</label>
                            <input v-model="modal.search" type="search" class="form-input" placeholder="Name / admission..." />
                        </div>
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <div v-if="studentsLoading" class="px-4 py-16 text-center text-sm text-slate-400">Loading students...</div>
                        <div v-else-if="!modalStudents.length" class="px-4 py-16 text-center text-sm text-slate-400">No students found.</div>
                        <button
                            v-for="s in modalStudents"
                            :key="s.id"
                            type="button"
                            class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-2.5 text-left text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50"
                            :class="modalPick?.id === s.id ? 'bg-primary-50 dark:bg-primary-500/10' : ''"
                            @click="modalPick = s"
                        >
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                            <span class="text-xs text-slate-400">{{ s.admission_no }} · {{ s.section?.name || '—' }}</span>
                        </button>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <button type="button" class="btn-outline" @click="studentModalOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!modalPick" @click="confirmStudent">Select</button>
                </div>
            </div>
        </div>

        <!-- Rollback modal -->
        <div v-if="rollbackOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeRollback" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Rollback receipt</h2>
                <p class="mt-1 text-sm text-slate-500">
                    This fully cancels <strong>{{ rollbackTarget?.receipt_no }}</strong>
                    (₹{{ money(rollbackMax) }}) for {{ rollbackTarget?.student?.name }} and restores their fee balance.
                    The receipt stays in history marked <strong>Rolled Back</strong> — it isn't deleted.
                </p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="form-label">Reason for rollback</label>
                        <input v-model="rollbackForm.reason" type="text" class="form-input" placeholder="e.g. Duplicate entry, wrong student" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="rollbackForm.hasRefund" type="checkbox" class="rounded" />
                        Money was actually refunded to the payer
                    </label>
                    <div v-if="rollbackForm.hasRefund" class="grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div>
                            <label class="form-label">Refund amount (₹)</label>
                            <input v-model.number="rollbackForm.refund_amount" type="number" min="0.01" :max="rollbackMax" step="0.01" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Refund reason / method</label>
                            <input v-model="rollbackForm.refund_reason" type="text" class="form-input" placeholder="e.g. ₹2,000 cash returned" />
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="closeRollback">Cancel</button>
                    <button type="button" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="rollbackingId" @click="confirmRollback">
                        {{ rollbackingId ? 'Rolling back...' : 'Confirm rollback' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit payment modal -->
        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeEdit" />
            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Edit receipt {{ editTarget?.receipt_no }}</h2>
                <p class="mt-1 text-sm text-slate-500">Correct amount, date, fee type, months, mode or reference — {{ editTarget?.student?.name }}.</p>

                <div class="mt-4 space-y-2">
                    <div v-for="(item, idx) in editForm.items" :key="idx" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="grid gap-2 sm:grid-cols-[1.5fr_1fr_1fr_auto]">
                            <div>
                                <label class="form-label">Fee head</label>
                                <select v-model.number="item.fee_head_id" class="form-input">
                                    <option v-for="h in feeHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Amount (₹)</label>
                                <input v-model.number="item.amount" type="number" min="0.01" step="0.01" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Discount (₹)</label>
                                <input v-model.number="item.discount" type="number" min="0" step="0.01" class="form-input" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="btn-outline !px-2.5 !py-2 !text-xs text-rose-600" title="Remove item" @click="removeEditItem(idx)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Months</label>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span v-for="m in item.months" :key="m" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ m }}
                                    <button type="button" class="text-slate-400 hover:text-rose-600" @click="removeEditItemMonth(idx, m)">×</button>
                                </span>
                                <input v-model="editNewMonth[idx]" type="month" class="form-input !w-auto !py-1 !text-xs" />
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="addEditItemMonth(idx)">Add</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="form-label">Payment date</label>
                        <input v-model="editForm.payment_date" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Payment mode</label>
                        <select v-model="editForm.payment_mode" class="form-input">
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Card</option>
                            <option>Bank Transfer</option>
                            <option>Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Fine (₹)</label>
                        <input v-model.number="editForm.fine_amount" type="number" min="0" step="0.01" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Reference / transaction no.</label>
                        <input v-model="editForm.reference_no" type="text" class="form-input" placeholder="Cheque / UTR / txn no." />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Remarks</label>
                        <input v-model="editForm.remarks" type="text" class="form-input" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Reason for this edit (required)</label>
                    <input v-model="editForm.reason" type="text" class="form-input" placeholder="e.g. Wrong amount entered at collection" />
                </div>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/60">
                    <span class="text-slate-500">New total</span>
                    <span class="text-lg font-bold text-primary-600">₹{{ money(editTotal) }}</span>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="closeEdit">Cancel</button>
                    <button type="button" class="btn-primary disabled:opacity-50" :disabled="editSaving" @click="submitEdit">
                        {{ editSaving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Audit trail / rollback details modal -->
        <div v-if="auditOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="closeAudits" />
            <div class="relative z-10 max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                    {{ auditTarget?.status === 'Rolled Back' ? 'Rollback details' : 'Edit history' }} — {{ auditTarget?.receipt_no }}
                </h2>
                <div v-if="auditLoading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!auditRows.length" class="py-10 text-center text-sm text-slate-400">No changes recorded for this receipt.</div>
                <div v-else class="mt-4 space-y-3">
                    <div v-for="a in auditRows" :key="a.id" class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                :class="a.action === 'Rollback' ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'"
                            >{{ a.action }}</span>
                            <span class="text-xs text-slate-400">{{ formatDateTime(a.created_at) }}</span>
                        </div>
                        <p class="mt-1.5 text-slate-700 dark:text-slate-200">{{ a.reason || '—' }}</p>
                        <p class="mt-1 text-xs text-slate-400">By {{ a.performed_by?.name || 'Unknown' }}</p>
                        <div v-if="a.action === 'Edit'" class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-800/60">
                            <div>
                                <p class="font-semibold text-slate-500">Before</p>
                                <p>₹{{ money(a.before?.amount) }} · {{ a.before?.payment_mode }} · {{ formatDate(a.before?.payment_date) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-500">After</p>
                                <p>₹{{ money(a.after?.amount) }} · {{ a.after?.payment_mode }} · {{ formatDate(a.after?.payment_date) }}</p>
                            </div>
                        </div>
                        <p v-if="a.action === 'Rollback' && a.refund_amount" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            Refunded ₹{{ money(a.refund_amount) }} — {{ a.refund_reason }}
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" class="btn-outline" @click="closeAudits">Close</button>
                </div>
            </div>
        </div>

        <!-- Receipt view / print -->
        <div v-if="receipt" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 print:static print:bg-transparent print:p-0">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-slate-900 print:max-w-none print:shadow-none">
                <div id="receipt-print-area">
                    <div class="mb-4 text-center">
                        <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Fee Receipt</p>
                        <p class="font-mono text-xs text-slate-400">{{ receipt.receipt_no }}</p>
                    </div>
                    <div class="mb-4 space-y-1 text-sm">
                        <p><strong>Student:</strong> {{ receipt.student?.name }} ({{ receipt.student?.admission_no }})</p>
                        <p><strong>Date:</strong> {{ formatDate(receipt.payment_date) }}</p>
                        <p><strong>Mode:</strong> {{ receipt.payment_mode }}</p>
                    </div>
                    <table class="mb-4 w-full text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(item, i) in receipt.items" :key="i">
                                <td class="py-1.5 text-slate-600">{{ item.fee_head_name }}</td>
                                <td class="py-1.5 text-right">₹{{ money(item.amount) }}</td>
                            </tr>
                            <tr v-if="Number(receipt.fine_amount) > 0">
                                <td class="py-1.5 text-slate-600">Fine</td>
                                <td class="py-1.5 text-right">₹{{ money(receipt.fine_amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-slate-200 font-semibold">
                                <td class="py-2">Total</td>
                                <td class="py-2 text-right">₹{{ money(receipt.amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="flex justify-end gap-2 print:hidden">
                    <button type="button" class="btn-outline" @click="receipt = null">Close</button>
                    <button type="button" class="btn-primary" @click="windowPrint">Print</button>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmSubmitOpen"
            title="Submit fee"
            :message="confirmSubmitMessage"
            confirm-label="Submit fee"
            :busy="collecting"
            busy-label="Submitting…"
            @confirm="submitFeeConfirmed"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { fetchFeeStudentsLite, invalidateFeeLookups } from '../../api/feeManagement';
import { erpStore } from '../../store';
import { downloadPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';

const route = useRoute();
const tabs = [
    { id: 'receipt', label: 'Receipt' },
    { id: 'history', label: 'History' },
    { id: 'report', label: 'Report' },
];
const activeTab = ref(route.path.includes('fee-refund') ? 'history' : 'receipt');

const students = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const feeMeta = ref(null);
const transportRoutes = ref([]);
const transportStops = ref([]);

const quickSearch = ref('');
const showQuickResults = ref(false);
const student = ref(null);
const due = ref(null);
const loadingDue = ref(false);
const selectedHeadIds = ref([]);
const amounts = reactive({});
const discounts = reactive({});
const selectedMonths = ref([]);
const paidMonths = ref([]);
const feeStartMonth = ref(null);
const paidByHead = ref({});
const paidByHeadDiscount = ref({});
const paidByHeadMonth = ref({});
const paidByHeadMonthDiscount = ref({});
const applyAllMonths = ref(true);
const paymentDate = ref(new Date().toISOString().slice(0, 10));
const paymentMode = ref('Cash');
const bankAccounts = ref([]);
const bankAccountId = ref(null);
const referenceNo = ref('');
const remarks = ref('');
const feeHeads = ref([]);
const collecting = ref(false);
const confirmSubmitOpen = ref(false);
const confirmSubmitMessage = ref('');
const receipt = ref(null);
const transport = reactive({
    apply: false,
    route_id: null,
    stop_id: null,
    fee: 0,
});
const transportHeadId = ref(null);
const transportFeeStartMonth = ref(null);
const studentTransport = ref(null);

const studentReceipts = ref([]);
const studentReceiptsLoading = ref(false);
const studentReceiptSelected = ref([]);

const studentModalOpen = ref(false);
const modalPick = ref(null);
const modal = reactive({ branch_id: null, school_class_id: null, section_id: null, search: '' });

const filters = reactive({
    search: '',
    period: 'month',
    date: new Date().toISOString().slice(0, 10),
    branch_id: null,
    school_class_id: null,
    section_id: null,
    order_by: 'submitted',
    order: 'desc',
    min_amount: 0,
    max_amount: '',
});
const historyLoading = ref(false);
const historyRows = ref([]);
const reportGroups = ref([]);
const reportTotal = ref(0);
const selectedIds = ref([]);
const multiPrint = ref(2);
const rollbackOpen = ref(false);
const rollbackTarget = ref(null);
const rollbackingId = ref(null);
const rollbackForm = reactive({ reason: '', hasRefund: false, refund_amount: 0, refund_reason: '' });
const rollbackMax = computed(() => {
    const p = rollbackTarget.value;
    if (!p) return 0;
    return Math.max(0, Number(p.paid_net ?? p.amount) || 0);
});

const filterSections = computed(() =>
    filters.school_class_id
        ? sections.value.filter((s) => s.school_class_id === filters.school_class_id)
        : sections.value,
);
const modalSections = computed(() =>
    modal.school_class_id
        ? sections.value.filter((s) => s.school_class_id === modal.school_class_id)
        : sections.value,
);

const quickFilteredStudents = computed(() => {
    if (!quickSearch.value) return [];
    const q = quickSearch.value.trim().toLowerCase();
    return students.value
        .filter((s) => s.status !== 'Inactive' && `${s.name} ${s.admission_no}`.toLowerCase().includes(q))
        .slice(0, 10);
});

const modalStudents = computed(() => {
    let rows = students.value.filter((s) => s.status !== 'Inactive');
    if (modal.school_class_id) rows = rows.filter((s) => s.school_class_id === modal.school_class_id);
    if (modal.branch_id) rows = rows.filter((s) => s.branch_id === modal.branch_id);
    if (modal.section_id) rows = rows.filter((s) => s.section_id === modal.section_id);
    if (modal.search.trim()) {
        const q = modal.search.trim().toLowerCase();
        rows = rows.filter((s) => `${s.name} ${s.admission_no}`.toLowerCase().includes(q));
    }
    return rows.slice(0, 100);
});

const monthBlocks = computed(() => {
    const blocks = [];
    if (feeMeta.value?.previous_session?.months?.length) {
        blocks.push({ key: 'prev', label: 'Previous session', months: feeMeta.value.previous_session.months });
    }
    if (feeMeta.value?.current_session?.months?.length) {
        blocks.push({ key: 'curr', label: 'Current session', months: feeMeta.value.current_session.months });
    }
    if (feeMeta.value?.next_session?.months?.length) {
        blocks.push({ key: 'next', label: 'Next session', months: feeMeta.value.next_session.months });
    }
    return blocks;
});

const studentPlaceLabel = computed(() => {
    const s = student.value;
    if (!s) return '—';
    const branch = s.branch?.name || branches.value.find((b) => b.id === s.branch_id)?.name || '—';
    const klass = s.school_class?.name || s.schoolClass?.name || classes.value.find((c) => c.id === s.school_class_id)?.name || '—';
    const section = s.section?.name || sections.value.find((sec) => sec.id === s.section_id)?.name || '—';
    return `${branch} / ${klass} / ${section}`;
});

const transportStatusLabel = computed(() => {
    if (studentTransport.value?.status === 'Active') {
        const routeName = studentTransport.value.route?.name || 'Transport';
        return routeName;
    }
    return 'No transport';
});

const transportFare = computed(() => {
    const stop = transportStops.value.find((s) => s.id === transport.stop_id);
    if (stop?.fare != null) return Number(stop.fare) || 0;
    return Number(studentTransport.value?.routeStop?.fare) || 0;
});

/** Full transport charge for selected unpaid months (fare Ã— months). */
const transportChargeFull = computed(() => {
    const months = unpaidSelectedMonths();
    if (!months.length || transportFare.value <= 0) return 0;
    return transportFare.value * months.length;
});

/** Remaining transport due after prior payments on those months. */
const transportDue = computed(() => {
    const months = transportBillableMonths(unpaidSelectedMonths());
    const fare = transportFare.value;
    const headId = transportHeadId.value;
    if (!months.length || fare <= 0) return 0;
    let prior = 0;
    if (headId) {
        for (const m of months) {
            prior += alreadyPaidForHeadMonth({ fee_head_id: headId }, m);
        }
    }
    return Math.max(0, Math.round((fare * months.length - prior) * 100) / 100);
});

function transportBillableMonths(months) {
    const start = transportFeeStartMonth.value;
    if (!start) return months;
    return months.filter((m) => m >= start);
}

function syncTransportPaid() {
    if (!transport.apply) return;
    transport.fee = transportDue.value;
}

function isMonthPaid(key) {
    return paidMonths.value.includes(key);
}

function isMonthBeforeFeeStart(key) {
    return !!(feeStartMonth.value && key < feeStartMonth.value);
}

function isMonthLocked(key) {
    return isMonthPaid(key) || isMonthBeforeFeeStart(key);
}

function monthLabelClass(key) {
    if (isMonthPaid(key)) return 'cursor-not-allowed text-emerald-600';
    if (isMonthBeforeFeeStart(key)) return 'cursor-not-allowed text-slate-400 line-through';
    return 'text-slate-700 dark:text-slate-200';
}

function toggleMonth(key, checked) {
    if (isMonthLocked(key)) return;
    if (checked) {
        if (!selectedMonths.value.includes(key)) selectedMonths.value = [...selectedMonths.value, key];
    } else {
        selectedMonths.value = selectedMonths.value.filter((m) => m !== key);
    }
}

function unpaidSelectedMonths() {
    return selectedMonths.value.filter((m) => !isMonthLocked(m));
}

function feeFrequency(item) {
    return String(item?.frequency || 'monthly').toLowerCase().replace(' ', '_');
}

function headKey(item) {
    return String(item?.fee_head_id ?? '');
}

function alreadyPaidForHead(item) {
    const key = headKey(item);
    return (Number(paidByHead.value[key]) || 0) + (Number(paidByHeadDiscount.value[key]) || 0);
}

function alreadyPaidForHeadMonth(item, monthKey) {
    const key = headKey(item);
    const byMonth = paidByHeadMonth.value[key] || {};
    const discByMonth = paidByHeadMonthDiscount.value[key] || {};
    return (Number(byMonth[monthKey]) || 0) + (Number(discByMonth[monthKey]) || 0);
}

function quarterlyMonths(months) {
    return months.filter((key) => {
        const monthNum = Number(String(key).slice(5, 7));
        return [4, 7, 10, 1].includes(monthNum);
    });
}

function unitsForFee(item) {
    const months = unpaidSelectedMonths();
    if (!months.length) return 0;
    const freq = feeFrequency(item);
    if (freq === 'monthly') {
        return months.filter((m) => Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHeadMonth(item, m)) > 0.0001).length;
    }
    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        const targets = qMonths.length ? qMonths : months.slice(0, 1);
        return targets.filter((m) => Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHeadMonth(item, m)) > 0.0001).length;
    }
    // annual / one_time — once per session until fully paid
    return Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHead(item)) > 0.0001 ? 1 : 0;
}

/** Full units for selected months (ignores prior payments) — used for receipt Amount/charge. */
function unitsForFeeFull(item) {
    const months = unpaidSelectedMonths();
    if (!months.length) return 0;
    const freq = feeFrequency(item);
    if (freq === 'monthly') return months.length;
    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        return qMonths.length || (months.length ? 1 : 0);
    }
    return 1;
}

function feeChargeFull(item) {
    return (Number(item.amount) || 0) * unitsForFeeFull(item);
}

function feeBase(item) {
    const amount = Number(item.amount) || 0;
    const freq = feeFrequency(item);
    const months = unpaidSelectedMonths();

    if (freq === 'annual' || freq === 'one_time') {
        return Math.max(0, amount - alreadyPaidForHead(item));
    }

    if (!applyAllMonths.value) {
        return Math.max(0, amount - alreadyPaidForHead(item));
    }

    if (freq === 'monthly') {
        return months.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }

    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        const targets = qMonths.length ? qMonths : (months.length ? [months[0]] : []);
        return targets.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }

    return Math.max(0, amount - alreadyPaidForHead(item));
}

function feeStatusLabel(item) {
    const base = feeBase(item);
    const paidNow = Number(amounts[item.fee_head_id]) || 0;
    const discNow = Number(discounts[item.fee_head_id]) || 0;
    const dueAmt = Math.max(0, base - discNow - paidNow);
    const freq = feeFrequency(item);
    const prior = (freq === 'annual' || freq === 'one_time')
        ? alreadyPaidForHead(item)
        : unpaidSelectedMonths().reduce((sum, m) => sum + alreadyPaidForHeadMonth(item, m), 0);

    if (base <= 0.0001) return 'Settled';
    if (dueAmt <= 0.0001) {
        if (prior > 0) return `Collecting remaining ₹${money(base)}`;
        return `Due ₹${money(base)}`;
    }
    if (prior > 0) return `Due ₹${money(dueAmt)} (already paid ₹${money(prior)})`;
    return `Due ₹${money(dueAmt)}`;
}

const chargeableBreakdown = computed(() =>
    (due.value?.breakdown || []).filter((item) => feeBase(item) > 0.0001),
);

const summary = computed(() => {
    let base = 0;
    let discount = 0;
    let applied = 0;
    for (const item of chargeableBreakdown.value) {
        if (!selectedHeadIds.value.includes(item.fee_head_id)) continue;
        base += feeBase(item);
        discount += Number(discounts[item.fee_head_id]) || 0;
        applied += Number(amounts[item.fee_head_id]) || 0;
    }
    if (transport.apply) {
        base += transportDue.value;
        applied += Number(transport.fee) || 0;
    }
    discount = Math.min(discount, base);
    const payable = Math.max(0, base - discount);
    const received = applied;
    const dueAfter = Math.max(0, payable - received);
    return { base, discount, payable, applied, received, dueAfter };
});

const canSubmit = computed(() => {
    if (!student.value) return false;
    if (!unpaidSelectedMonths().length) return false;
    if (paymentMode.value === 'Bank Transfer' && !bankAccountId.value) return false;
    const hasFee = selectedHeadIds.value.some((id) => (Number(amounts[id]) || 0) > 0);
    const hasTransport = transport.apply && (Number(transport.fee) || 0) > 0;
    return hasFee || hasTransport;
});

const studentReceiptsAllSelected = computed(
    () => studentReceipts.value.length > 0 && studentReceiptSelected.value.length === studentReceipts.value.length,
);

const allSelected = computed(() => historyRows.value.length > 0 && selectedIds.value.length === historyRows.value.length);

function money(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function feeBaseForMonths(item, months) {
    const amount = Number(item.amount) || 0;
    const freq = feeFrequency(item);
    if (!months.length) {
        if (freq === 'annual' || freq === 'one_time') {
            return Math.max(0, amount - alreadyPaidForHead(item));
        }
        return 0;
    }
    if (freq === 'annual' || freq === 'one_time') {
        return Math.max(0, amount - alreadyPaidForHead(item));
    }
    if (freq === 'monthly') {
        return months.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }
    if (freq === 'quarterly') {
        const qMonths = quarterlyMonths(months);
        const targets = qMonths.length ? qMonths : [months[0]];
        return targets.reduce((sum, m) => sum + Math.max(0, amount - alreadyPaidForHeadMonth(item, m)), 0);
    }
    return Math.max(0, amount - alreadyPaidForHead(item));
}

/** Remaining due for months already under collection (matches receipt balance dues), not the whole session. */
const studentRemainingDue = computed(() => {
    if (!due.value) return null;
    const currMonths = feeMeta.value?.current_session?.months || [];
    const nowKey = new Date().toISOString().slice(0, 7);
    const unpaid = currMonths
        .map((m) => m.key)
        .filter((k) => !paidMonths.value.includes(k) && k <= nowKey && (!feeStartMonth.value || k >= feeStartMonth.value));

    // Only months that already appear on a receipt — otherwise the first unpaid month.
    // Avoid summing every future unpaid month (that inflated DUE to ₹20,500+).
    const touched = new Set();
    for (const byMonth of Object.values(paidByHeadMonth.value || {})) {
        for (const key of Object.keys(byMonth || {})) {
            touched.add(key);
        }
    }
    let focusMonths = unpaid.filter((k) => touched.has(k));
    if (!focusMonths.length) {
        // Previously collected months are fully settled — no open receipt balance.
        return 0;
    }
    const lastTouched = focusMonths[focusMonths.length - 1];
    focusMonths = unpaid.filter((k) => k <= lastTouched);

    let total = 0;
    for (const item of due.value.breakdown || []) {
        const freq = feeFrequency(item);
        if (freq === 'annual' || freq === 'one_time') {
            // Session fees: only count while the first session month is still unpaid / in focus.
            const firstKey = currMonths[0]?.key;
            if (firstKey && (focusMonths.includes(firstKey) || unpaid.includes(firstKey))) {
                total += Math.max(0, (Number(item.amount) || 0) - alreadyPaidForHead(item));
            }
        } else {
            total += feeBaseForMonths(item, focusMonths);
        }
    }

    const fare = transportFare.value;
    const headId = transportHeadId.value;
    const hasTransport = studentTransport.value?.status === 'Active' || (transport.apply && fare > 0);
    const transportMonths = transportBillableMonths(focusMonths);
    if (hasTransport && fare > 0 && transportMonths.length) {
        let prior = 0;
        if (headId) {
            for (const m of transportMonths) {
                prior += alreadyPaidForHeadMonth({ fee_head_id: headId }, m);
            }
        }
        total += Math.max(0, fare * transportMonths.length - prior);
    }

    return Math.round(total * 100) / 100;
});

/** Current remaining due for the selected student (shared across their receipt rows). */
function formatStudentDue() {
    const amount = studentRemainingDue.value;
    if (amount == null || Number.isNaN(amount)) return '—';
    return `₹${money(amount)}`;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function frequencyLabel(f) {
    return { monthly: 'Monthly', quarterly: 'Quarterly', annual: 'Annual', one_time: 'One time' }[f] || String(f || '').replace('_', ' ');
}

function statusClass(status) {
    if (status === 'Paid' || status === 'active' || !status) return 'bg-sky-50 text-sky-700 ring-sky-600/20';
    if (status === 'Refunded') return 'bg-rose-50 text-rose-700 ring-rose-600/20';
    return 'bg-amber-50 text-amber-700 ring-amber-600/20';
}

function historyStatusLabel(p) {
    const status = String(p.status || '').toLowerCase();
    if (status === 'rolled back') return 'rolled back';
    if (status === 'refunded') return 'refunded';
    if (status.includes('partial')) return 'partial';
    if (p.edited_at) return 'edited';
    return 'active';
}

function historyStatusClass(p) {
    const label = historyStatusLabel(p);
    if (label === 'rolled back') return 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300';
    if (label === 'refunded') return 'bg-rose-50 text-rose-700 ring-rose-600/20';
    if (label === 'partial') return 'bg-amber-50 text-amber-700 ring-amber-600/20';
    if (label === 'edited') return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20';
    return 'bg-sky-50 text-sky-700 ring-sky-600/20';
}

function historyMonths(p) {
    const keys = [];
    for (const item of p.items || []) {
        if (Array.isArray(item.months)) keys.push(...item.months);
        else if (item.month) keys.push(item.month);
    }
    const unique = [...new Set(keys.map((k) => String(k).slice(0, 7)).filter((k) => /^\d{4}-\d{2}$/.test(k)))];
    if (unique.length) return unique.join(', ');
    return p.months || '—';
}

function openRollback(p) {
    rollbackTarget.value = p;
    rollbackForm.reason = '';
    rollbackForm.hasRefund = false;
    rollbackForm.refund_amount = Number(p.paid_net ?? p.amount) || 0;
    rollbackForm.refund_reason = '';
    rollbackOpen.value = true;
}

function closeRollback() {
    rollbackOpen.value = false;
    rollbackTarget.value = null;
    rollbackingId.value = null;
}

async function confirmRollback() {
    if (!rollbackTarget.value) return;
    if (!rollbackForm.reason.trim()) {
        pushToast('Enter a reason for the rollback.', 'error');
        return;
    }
    if (rollbackForm.hasRefund) {
        if (!(Number(rollbackForm.refund_amount) > 0)) {
            pushToast('Enter the refund amount.', 'error');
            return;
        }
        if (!rollbackForm.refund_reason.trim()) {
            pushToast('Enter how/why the refund was given.', 'error');
            return;
        }
    }
    rollbackingId.value = rollbackTarget.value.id;
    try {
        await client.post(`/fee-management/payments/${rollbackTarget.value.id}/rollback`, {
            reason: rollbackForm.reason.trim(),
            refund_amount: rollbackForm.hasRefund ? Number(rollbackForm.refund_amount) : null,
            refund_reason: rollbackForm.hasRefund ? rollbackForm.refund_reason.trim() : null,
        });
        pushToast('Receipt rolled back — student balance restored.', 'success');
        closeRollback();
        await loadHistory();
    } catch (e) {
        pushToast(e?.response?.data?.message || e?.response?.data?.errors?.reason?.[0] || e?.response?.data?.errors?.refund_amount?.[0] || 'Rollback failed.', 'error');
    } finally {
        rollbackingId.value = null;
    }
}

// --- Edit payment ---
const editOpen = ref(false);
const editTarget = ref(null);
const editSaving = ref(false);
const editForm = reactive({
    items: [],
    payment_mode: 'Cash',
    payment_date: '',
    reference_no: '',
    remarks: '',
    fine_amount: 0,
    reason: '',
});
const editNewMonth = reactive({});

const editTotal = computed(() => {
    const itemsTotal = editForm.items.reduce((sum, i) => sum + (Number(i.amount) || 0), 0);
    return itemsTotal + (Number(editForm.fine_amount) || 0);
});

function openEdit(p) {
    editTarget.value = p;
    editForm.items = (p.items || []).map((i) => ({
        fee_head_id: i.fee_head_id,
        amount: Number(i.amount) || 0,
        discount: Number(i.discount) || 0,
        months: Array.isArray(i.months) ? [...i.months] : i.month ? [i.month] : [],
    }));
    editForm.payment_mode = p.payment_mode || 'Cash';
    editForm.payment_date = String(p.payment_date || '').slice(0, 10);
    editForm.reference_no = p.reference_no || '';
    editForm.remarks = p.remarks || '';
    editForm.fine_amount = Number(p.fine_amount) || 0;
    editForm.reason = '';
    Object.keys(editNewMonth).forEach((k) => delete editNewMonth[k]);
    editOpen.value = true;
}

function closeEdit() {
    editOpen.value = false;
    editTarget.value = null;
    editSaving.value = false;
}

function addEditItemMonth(idx) {
    const m = editNewMonth[idx];
    if (!m) return;
    const item = editForm.items[idx];
    if (!item.months.includes(m)) item.months.push(m);
    editNewMonth[idx] = '';
}

function removeEditItemMonth(idx, month) {
    editForm.items[idx].months = editForm.items[idx].months.filter((m) => m !== month);
}

function removeEditItem(idx) {
    if (editForm.items.length <= 1) {
        pushToast('A receipt needs at least one fee item.', 'error');
        return;
    }
    editForm.items.splice(idx, 1);
}

async function submitEdit() {
    if (!editTarget.value) return;
    if (!editForm.items.length) {
        pushToast('Add at least one fee item.', 'error');
        return;
    }
    if (editForm.items.some((i) => !(Number(i.amount) > 0))) {
        pushToast('Every item needs an amount greater than zero.', 'error');
        return;
    }
    if (!editForm.reason.trim()) {
        pushToast('Enter a reason for this edit.', 'error');
        return;
    }
    editSaving.value = true;
    try {
        await client.patch(`/fee-management/payments/${editTarget.value.id}`, {
            items: editForm.items.map((i) => ({
                fee_head_id: i.fee_head_id,
                amount: Number(i.amount),
                discount: Number(i.discount) || 0,
                months: i.months,
            })),
            fine_amount: Number(editForm.fine_amount) || 0,
            payment_mode: editForm.payment_mode,
            payment_date: editForm.payment_date,
            reference_no: editForm.reference_no || null,
            remarks: editForm.remarks || null,
            reason: editForm.reason.trim(),
        });
        pushToast('Receipt updated.', 'success');
        closeEdit();
        await loadHistory();
    } catch (e) {
        const errors = e?.response?.data?.errors || {};
        const firstError = Object.values(errors)[0]?.[0];
        pushToast(firstError || e?.response?.data?.message || 'Edit failed.', 'error');
    } finally {
        editSaving.value = false;
    }
}

// --- Audit trail / rollback details ---
const auditOpen = ref(false);
const auditTarget = ref(null);
const auditLoading = ref(false);
const auditRows = ref([]);

async function openAudits(p) {
    auditTarget.value = p;
    auditOpen.value = true;
    auditLoading.value = true;
    auditRows.value = [];
    try {
        const { data } = await client.get(`/fee-management/payments/${p.id}/audits`);
        auditRows.value = data || [];
    } catch {
        pushToast('Could not load the audit trail.', 'error');
    } finally {
        auditLoading.value = false;
    }
}

function closeAudits() {
    auditOpen.value = false;
    auditTarget.value = null;
    auditRows.value = [];
}

function windowPrint() {
    window.print();
}

function syncFeePaid(item) {
    if (!selectedHeadIds.value.includes(item.fee_head_id)) {
        amounts[item.fee_head_id] = 0;
        return;
    }
    const base = feeBase(item);
    const disc = Number(discounts[item.fee_head_id]) || 0;
    amounts[item.fee_head_id] = Math.max(0, base - disc);
}

function sameIdSet(a, b) {
    if (a.length !== b.length) return false;
    const set = new Set(a);
    return b.every((id) => set.has(id));
}

function refreshSelectedAmounts() {
    const chargeableIds = chargeableBreakdown.value.map((i) => i.fee_head_id);
    const chargeableSet = new Set(chargeableIds);
    let nextIds = selectedHeadIds.value.filter((id) => chargeableSet.has(id));
    if (!nextIds.length && chargeableIds.length) {
        nextIds = [...chargeableIds];
    }
    // Avoid reassigning selectedHeadIds unless the set actually changed — otherwise the
    // selectedHeadIds watcher re-enters forever and the Select button appears to do nothing.
    if (!sameIdSet(selectedHeadIds.value, nextIds)) {
        selectedHeadIds.value = nextIds;
    }
    for (const item of due.value?.breakdown || []) {
        if (!chargeableSet.has(item.fee_head_id)) {
            amounts[item.fee_head_id] = 0;
            continue;
        }
        if (!selectedHeadIds.value.includes(item.fee_head_id)) {
            amounts[item.fee_head_id] = 0;
            continue;
        }
        syncFeePaid(item);
    }
}

async function loadLookups() {
    const [lookups, meta, accounts, feeLookups] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/fee-management/due/meta').then((r) => r.data).catch(() => null),
        client.get('/finance-payroll/bank-accounts').then((r) => r.data).catch(() => []),
        client.get('/fee-management/lookups').then((r) => r.data).catch(() => null),
    ]);
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    feeMeta.value = meta;
    bankAccounts.value = accounts || [];
    feeHeads.value = feeLookups?.heads || [];
}

watch(paymentMode, (mode) => {
    if (mode !== 'Bank Transfer') bankAccountId.value = null;
});

const studentsLoading = ref(false);
/** @type {Promise<void> | null} */
let studentsLoadPromise = null;
async function ensureStudentsLoaded() {
    if (students.value.length) return;
    if (!studentsLoadPromise) {
        studentsLoading.value = true;
        studentsLoadPromise = fetchFeeStudentsLite()
            .then((st) => {
                students.value = Array.isArray(st) ? st : [];
            })
            .finally(() => {
                studentsLoading.value = false;
                studentsLoadPromise = null;
            });
    }
    await studentsLoadPromise;
}

const transportRoutesLoading = ref(false);
async function ensureTransportRoutes() {
    if (transportRoutes.value.length || transportRoutesLoading.value) return;
    transportRoutesLoading.value = true;
    try {
        const { data } = await client.get('/transport/routes');
        transportRoutes.value = Array.isArray(data) ? data : [];
    } catch {
        transportRoutes.value = [];
    } finally {
        transportRoutesLoading.value = false;
    }
}

function studentClassLabel(s) {
    const branch = s.branch?.name || branches.value.find((b) => b.id === s.branch_id)?.name || '—';
    const klass = s.school_class?.name || s.schoolClass?.name || classes.value.find((c) => c.id === s.school_class_id)?.name || '—';
    const section = s.section?.name || sections.value.find((sec) => sec.id === s.section_id)?.name || '—';
    return `${branch} / ${klass} / ${section}`;
}

function hideQuickResultsSoon() {
    setTimeout(() => { showQuickResults.value = false; }, 150);
}

function quickSelectStudent(s) {
    showQuickResults.value = false;
    quickSearch.value = '';
    selectStudent(s).catch((e) => {
        pushToast(e?.response?.data?.message || 'Could not load student fee details.', 'error');
    });
}

async function openStudentModal(prefill = '') {
    modal.search = prefill || '';
    modalPick.value = null;
    studentModalOpen.value = true;
    await ensureStudentsLoaded();
}

function confirmStudent() {
    if (!modalPick.value) return;
    const pick = modalPick.value;
    studentModalOpen.value = false;
    selectStudent(pick).catch((e) => {
        pushToast(e?.response?.data?.message || 'Could not load student fee details.', 'error');
    });
}

async function selectStudent(s) {
    student.value = s;
    due.value = null;
    selectedHeadIds.value = [];
    Object.keys(amounts).forEach((k) => delete amounts[k]);
    Object.keys(discounts).forEach((k) => delete discounts[k]);
    selectedMonths.value = [];
    paidMonths.value = [];
    feeStartMonth.value = null;
    paidByHead.value = {};
    paidByHeadDiscount.value = {};
    paidByHeadMonth.value = {};
    paidByHeadMonthDiscount.value = {};
    remarks.value = '';
    referenceNo.value = '';
    paymentDate.value = new Date().toISOString().slice(0, 10);
    transport.apply = false;
    transport.route_id = null;
    transport.stop_id = null;
    transport.fee = 0;
    transportHeadId.value = null;
    transportFeeStartMonth.value = null;
    transportStops.value = [];
    studentTransport.value = null;
    studentReceiptSelected.value = [];
    loadingDue.value = true;
    try {
        const [dueRes, transportRes] = await Promise.all([
            client.get(`/fee-management/students/${s.id}/due`),
            client.get('/transport/student-transport', { params: { student_id: s.id } }).catch(() => ({ data: [] })),
        ]);
        due.value = dueRes.data;
        paidMonths.value = Array.isArray(dueRes.data.paid_months) ? dueRes.data.paid_months : [];
        feeStartMonth.value = dueRes.data.fee_start_month || null;
        paidByHead.value = dueRes.data.paid_by_head && typeof dueRes.data.paid_by_head === 'object' ? dueRes.data.paid_by_head : {};
        paidByHeadDiscount.value = dueRes.data.paid_by_head_discount && typeof dueRes.data.paid_by_head_discount === 'object'
            ? dueRes.data.paid_by_head_discount
            : {};
        paidByHeadMonth.value = dueRes.data.paid_by_head_month && typeof dueRes.data.paid_by_head_month === 'object'
            ? dueRes.data.paid_by_head_month
            : {};
        paidByHeadMonthDiscount.value = dueRes.data.paid_by_head_month_discount && typeof dueRes.data.paid_by_head_month_discount === 'object'
            ? dueRes.data.paid_by_head_month_discount
            : {};
        transportHeadId.value = dueRes.data.transport_head_id || null;
        transportFeeStartMonth.value = dueRes.data.transport_fee_start_month || null;
        const assignment = (Array.isArray(transportRes.data) ? transportRes.data : []).find((row) => row.status === 'Active') || transportRes.data?.[0] || null;
        studentTransport.value = assignment;
        if (assignment) {
            if (!transportFeeStartMonth.value) {
                transportFeeStartMonth.value = (assignment.fee_start_month || assignment.start_date || '').toString().slice(0, 7) || null;
            }
            transport.apply = true;
            transport.route_id = assignment.route_id || assignment.route?.id || null;
            transport.stop_id = assignment.route_stop_id || assignment.routeStop?.id || null;
            // Routes + stops load in background so fee entry is usable immediately.
            ensureTransportRoutes().then(() => {
                if (transport.route_id) return loadTransportStops(transport.route_id);
            }).then(() => {
                syncTransportPaid();
            }).catch(() => {
                syncTransportPaid();
            });
        } else {
            syncTransportPaid();
        }
        (due.value.breakdown || []).forEach((item) => {
            discounts[item.fee_head_id] = 0;
            amounts[item.fee_head_id] = 0;
        });
        // Default: first unpaid month in current session (else first unpaid anywhere).
        const currMonths = feeMeta.value?.current_session?.months || [];
        const allMonths = monthBlocks.value.flatMap((b) => b.months.map((m) => m.key));
        const nowKey = new Date().toISOString().slice(0, 7);
        const isBillable = (k) => !feeStartMonth.value || k >= feeStartMonth.value;
        const unpaidCurr = currMonths.map((m) => m.key).filter((k) => isBillable(k) && !paidMonths.value.includes(k));
        const unpaidAll = allMonths.filter((k) => isBillable(k) && !paidMonths.value.includes(k));
        if (unpaidCurr.includes(nowKey)) {
            // Include all unpaid months up to the current month (so partial payments
            // like Aug due ₹500 automatically carry into Sep totals).
            selectedMonths.value = unpaidCurr.filter((k) => k <= nowKey);
        } else if (unpaidCurr.length) {
            selectedMonths.value = [unpaidCurr[0]];
        } else if (unpaidAll.length) {
            selectedMonths.value = [unpaidAll[0]];
        } else {
            selectedMonths.value = [];
        }
        selectedHeadIds.value = chargeableBreakdown.value.map((i) => i.fee_head_id);
        refreshSelectedAmounts();
        // Receipt history is secondary — don't block the collect form.
        loadStudentReceipts();
    } finally {
        loadingDue.value = false;
    }
}

async function loadStudentReceipts() {
    if (!student.value) {
        studentReceipts.value = [];
        return;
    }
    studentReceiptsLoading.value = true;
    try {
        const { data } = await client.get('/fee-management/payments', {
            params: { student_id: student.value.id, limit: 50, order_by: 'submitted', order: 'desc' },
        });
        studentReceipts.value = Array.isArray(data) ? data : [];
    } finally {
        studentReceiptsLoading.value = false;
    }
}

function clearStudent() {
    student.value = null;
    due.value = null;
    selectedHeadIds.value = [];
    paidMonths.value = [];
    paidByHead.value = {};
    paidByHeadDiscount.value = {};
    paidByHeadMonth.value = {};
    paidByHeadMonthDiscount.value = {};
    selectedMonths.value = [];
    studentReceipts.value = [];
    studentReceiptSelected.value = [];
}

async function onTransportRouteChange() {
    transport.stop_id = null;
    transport.fee = 0;
    await loadTransportStops(transport.route_id);
    syncTransportPaid();
}

async function loadTransportStops(routeId) {
    if (!routeId) {
        transportStops.value = [];
        return;
    }
    const { data } = await client.get('/transport/stops', { params: { route_id: routeId } });
    transportStops.value = Array.isArray(data) ? data : [];
}

function onTransportStopChange() {
    syncTransportPaid();
}

/** Split a head's paid/discount across unpaid months (earliest first). */
function allocateHeadAcrossMonths(item, months, paidAmt, discountAmt) {
    const amount = Number(item.amount) || 0;
    const freq = feeFrequency(item);
    const sorted = [...months].sort();
    const lines = [];

    if (freq === 'annual' || freq === 'one_time') {
        const dueAmt = Math.max(0, amount - alreadyPaidForHead(item));
        if (paidAmt <= 0) return [];
        return [{
            fee_head_id: item.fee_head_id,
            amount: paidAmt,
            discount: discountAmt || 0,
            charge: Math.max(dueAmt, paidAmt),
            months: sorted.length ? [sorted[0]] : [],
        }];
    }

    let paidLeft = paidAmt;
    let discLeft = discountAmt;
    const targets = freq === 'quarterly'
        ? (quarterlyMonths(sorted).length ? quarterlyMonths(sorted) : sorted.slice(0, 1))
        : sorted;

    for (const m of targets) {
        const unitDue = Math.max(0, amount - alreadyPaidForHeadMonth(item, m));
        if (unitDue <= 0.0001) continue;

        const discTake = Math.min(discLeft, unitDue);
        discLeft -= discTake;
        const need = Math.max(0, unitDue - discTake);
        const paidTake = Math.min(paidLeft, need);
        paidLeft -= paidTake;

        if (paidTake <= 0.0001 && discTake <= 0.0001) continue;

        lines.push({
            fee_head_id: item.fee_head_id,
            amount: paidTake > 0 ? paidTake : 0,
            discount: discTake || 0,
            charge: unitDue,
            months: [m],
        });
    }

    if (paidLeft > 0.0001 && lines.length) {
        lines[lines.length - 1].amount = Math.round((lines[lines.length - 1].amount + paidLeft) * 100) / 100;
        lines[lines.length - 1].charge = Math.max(lines[lines.length - 1].charge, lines[lines.length - 1].amount);
    }

    return lines.filter((l) => l.amount > 0);
}

async function collect() {
    if (!canSubmit.value) {
        if (paymentMode.value === 'Bank Transfer' && !bankAccountId.value) {
            pushToast('Select which bank account received this transfer.', 'error');
        } else {
            pushToast(unpaidSelectedMonths().length ? 'Enter at least one paid amount.' : 'Select unpaid months to collect.', 'error');
        }
        return;
    }

    const who = student.value?.name || 'this student';
    const amt = money(summary.value.payable);
    confirmSubmitMessage.value = `Submit fee of ₹${amt} for ${who}?`;
    confirmSubmitOpen.value = true;
}

async function submitFeeConfirmed() {
    if (collecting.value) return;
    confirmSubmitOpen.value = false;
    collecting.value = true;
    try {
        const months = unpaidSelectedMonths();
        const items = [];
        for (const id of selectedHeadIds.value) {
            const item = (due.value?.breakdown || []).find((row) => row.fee_head_id === id);
            const paidAmt = Number(amounts[id]) || 0;
            if (paidAmt <= 0 || !item) continue;
            const discAmt = Number(discounts[id]) || 0;
            items.push(...allocateHeadAcrossMonths(item, months, paidAmt, discAmt));
        }

        const transportFee = transport.apply ? (Number(transport.fee) || 0) : 0;
        const transportCharge = transport.apply ? Math.max(transportDue.value, transportFee) : 0;
        if (!items.length && transportFee <= 0) {
            pushToast('Enter amounts for selected fee types.', 'error');
            return;
        }

        const { data } = await client.post('/fee-management/payments', {
            student_id: student.value.id,
            items,
            months,
            discount_amount: summary.value.discount,
            payment_mode: paymentMode.value,
            bank_account_id: paymentMode.value === 'Bank Transfer' ? bankAccountId.value : null,
            payment_date: paymentDate.value,
            reference_no: referenceNo.value || null,
            remarks: remarks.value || null,
            transport_fee: transportFee,
            transport_charge: transportCharge,
            transport_route_id: transport.apply ? transport.route_id : null,
            transport_stop_id: transport.apply ? transport.stop_id : null,
        });

        pushToast('Receipt generated.', 'success');
        openDepositReceipt(data.id);
        await selectStudent(student.value);
    } catch (e) {
        const errors = e?.response?.data?.errors;
        const msg = errors?.months?.[0] || e?.response?.data?.message || 'Could not submit fee.';
        pushToast(msg, 'error');
    } finally {
        collecting.value = false;
    }
}

function toggleStudentReceiptsAll(e) {
    studentReceiptSelected.value = e.target.checked ? studentReceipts.value.map((p) => p.id) : [];
}

function openDepositReceipt(paymentId, extraQuery = '') {
    if (!paymentId) return;
    // Print and Download both open in a new tab and save the PDF.
    downloadPdf(`/fee-management/payments/${paymentId}/pdf`, `fee-receipt-${paymentId}.pdf`).catch(() => {
        pushToast(extraQuery === 'download' ? 'Could not download receipt PDF.' : 'Could not open receipt PDF.', 'error');
    });
}

function printStudentSelected() {
    const rows = studentReceipts.value.filter((p) => studentReceiptSelected.value.includes(p.id));
    if (rows.length !== multiPrint.value) {
        pushToast(`Select exactly ${multiPrint.value} receipts.`, 'error');
        return;
    }
    rows.forEach((p) => openDepositReceipt(p.id));
    pushToast(`Opened ${rows.length} receipt(s) in new tabs.`, 'info');
}

function downloadDepositReceipt(paymentId) {
    openDepositReceipt(paymentId, 'download');
}

function downloadReceipt(p) {
    downloadDepositReceipt(p.id);
}

function filterParams() {
    const params = {
        period: filters.period,
        date: filters.date,
        order_by: filters.order_by,
        order: filters.order,
        min_amount: filters.min_amount || 0,
        with_due: 1,
        limit: 400,
    };
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.branch_id) params.branch_id = filters.branch_id;
    if (filters.school_class_id) params.school_class_id = filters.school_class_id;
    if (filters.section_id) params.section_id = filters.section_id;
    if (filters.max_amount !== '' && filters.max_amount != null) params.max_amount = filters.max_amount;
    return params;
}

async function loadHistory() {
    historyLoading.value = true;
    selectedIds.value = [];
    try {
        const { data } = await client.get('/fee-management/payments', { params: filterParams() });
        historyRows.value = data;
    } finally {
        historyLoading.value = false;
    }
}

async function loadReport() {
    historyLoading.value = true;
    try {
        const params = { ...filterParams() };
        delete params.with_due;
        const { data } = await client.get('/fee-management/payments/report', { params });
        reportGroups.value = data.groups || [];
        reportTotal.value = data.total_collected || 0;
    } finally {
        historyLoading.value = false;
    }
}

function applyFilters() {
    if (activeTab.value === 'report') loadReport();
    else loadHistory();
}

async function exportCsv() {
    const params = new URLSearchParams(filterParams());
    params.delete('with_due');
    const response = await client.get(`/fee-management/payments/export?${params}`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'fee-receipts.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function toggleSelectAll(e) {
    selectedIds.value = e.target.checked ? historyRows.value.map((p) => p.id) : [];
}

function viewReceipt(p) {
    openDepositReceipt(p.id);
}

function printSelected() {
    const rows = historyRows.value.filter((p) => selectedIds.value.includes(p.id));
    if (rows.length !== multiPrint.value) {
        pushToast(`Select exactly ${multiPrint.value} receipts.`, 'error');
        return;
    }
    rows.forEach((p) => openDepositReceipt(p.id));
    pushToast(`Opened ${rows.length} receipt(s) in new tabs.`, 'info');
}

watch(selectedMonths, () => {
    refreshSelectedAmounts();
    syncTransportPaid();
}, { deep: true });
watch(applyAllMonths, () => refreshSelectedAmounts());
watch(
    () => transport.apply,
    (on) => {
        if (on) {
            ensureTransportRoutes();
            syncTransportPaid();
        }
    },
);
watch(
    () => transport.stop_id,
    () => syncTransportPaid(),
);

watch(activeTab, (tab) => {
    if (tab === 'history') loadHistory();
    if (tab === 'report') loadReport();
});

watch(() => erpStore.currentSession, async () => {
    invalidateFeeLookups();
    const { data } = await client.get('/fee-management/due/meta').catch(() => ({ data: null }));
    feeMeta.value = data;
    students.value = [];
    if (student.value) await selectStudent(student.value);
});

onMounted(loadLookups);
</script>
