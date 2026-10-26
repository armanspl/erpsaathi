<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Paid</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Students who have cleared fees till the current month, or paid in advance for future months.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !py-1.5 !text-xs" :disabled="loading || !rows.length" @click="printPaid">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                    Print
                </button>
                <Dropdown align="right">
                    <template #trigger>
                        <button type="button" class="btn-primary !py-1.5 !text-xs" :disabled="exporting || !rows.length">
                            {{ exporting ? 'Exporting…' : 'Export ▾' }}
                        </button>
                    </template>
                    <template #panel="{ close }">
                        <div class="w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('xlsx'); close()">📊 Excel (.xlsx)</button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('csv'); close()">📄 CSV</button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700" @click="runExport('pdf'); close()">📕 PDF</button>
                        </div>
                    </template>
                </Dropdown>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <!-- Academic session comes from the header picker; no separate year filter here. -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-if="branches.length > 1">
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="filters.school_class_id" class="form-input">
                        <option :value="null">All classes</option>
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
                    <label class="form-label">Vehicle</label>
                    <select v-model="filters.vehicle" class="form-input">
                        <option value="">All vehicles</option>
                        <option value="__any">Transport students (any vehicle)</option>
                        <option value="None">No transport</option>
                        <option v-for="v in vehicles" :key="v" :value="v">{{ v }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Dues</label>
                    <select v-model="filters.dues" class="form-input">
                        <option value="all">All students</option>
                        <option value="due">Only with dues</option>
                        <option value="clear">Only fully paid</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Search</label>
                    <input v-model="filters.search" type="search" class="form-input" placeholder="Name / admission (auto)" />
                </div>
                <div>
                    <label class="form-label">Paid on</label>
                    <select v-model="filters.paid_mode" class="form-input">
                        <option value="">Any date</option>
                        <option value="date">A date</option>
                        <option value="month">A month</option>
                        <option value="range">Between dates</option>
                    </select>
                </div>
                <div v-if="filters.paid_mode === 'date'">
                    <label class="form-label">Date</label>
                    <input v-model="filters.paid_date" type="date" class="form-input" />
                </div>
                <div v-else-if="filters.paid_mode === 'month'">
                    <label class="form-label">Month</label>
                    <input v-model="filters.paid_month" type="month" class="form-input" />
                </div>
                <template v-else-if="filters.paid_mode === 'range'">
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="filters.paid_from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="filters.paid_to" type="date" class="form-input" :min="filters.paid_from || undefined" />
                    </div>
                </template>
            </div>
        </div>

        <div v-if="view === 'paid_any'" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students with payments</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ summary.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ paidRange ? `Collected ${paidRange.label}` : 'Total collected' }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ money(paidRange ? summary.range_collected : summary.collected) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Outstanding (till now)</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">₹{{ money(summary.due) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Fully paid / Partly paid</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">
                    <span class="text-emerald-600 dark:text-emerald-400">{{ summary.cleared }}</span>
                    <span class="text-slate-300"> / </span>
                    <span class="text-amber-600">{{ summary.partial }}</span>
                </p>
            </div>
        </div>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ summary.students }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ view === 'advance' ? 'Advance charge' : 'Charged' }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.charged) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ view === 'advance' ? 'Advance paid' : 'Paid' }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ money(view === 'advance' ? summary.advance_paid : summary.paid) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Concession</p>
                <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">₹{{ money(summary.concession) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                        {{ viewMeta.title }}
                    </h2>
                    <p class="text-xs text-slate-400">{{ viewMeta.hint }}</p>
                    <p v-if="paidRange" class="mt-0.5 text-xs font-medium text-primary-600 dark:text-primary-400">Only students who paid {{ paidRange.label }} — receipt, date and amount are from that period.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
                        <button
                            v-for="opt in VIEWS"
                            :key="opt.key"
                            type="button"
                            class="rounded-md px-3 py-1 text-xs font-medium transition"
                            :class="view === opt.key ? 'bg-white text-primary-700 shadow-sm dark:bg-slate-700 dark:text-primary-300' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                            @click="view = opt.key"
                        >{{ opt.label }}</button>
                    </div>
                    <span class="text-xs text-slate-400">{{ rows.length }} student(s)</span>
                </div>
            </div>
            <div v-if="loading" class="px-4 py-16 text-center text-sm text-slate-400">Loading…</div>
            <div v-else-if="!rows.length" class="px-4 py-12 text-center text-sm text-slate-400">
                {{ view === 'paid_any' ? 'No students with payments match these filters.' : 'No fee-paid students match these filters.' }}
            </div>
            <div v-else class="overflow-x-auto">
                <div class="min-w-[900px]">
                    <div class="grid grid-cols-[44px_minmax(0,2.2fr)_minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(0,1.1fr)_minmax(0,1.4fr)] items-center gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/50">
                        <span></span>
                        <span>Name</span>
                        <span>Adm. No.</span>
                        <span>Class / Sec</span>
                        <span>Receipt</span>
                        <span>Paid Date</span>
                        <span class="text-right">Paid Amount</span>
                        <span>Status</span>
                    </div>

                    <div v-for="r in pagedRows" :key="r.student_id" class="border-b border-slate-100 last:border-b-0 dark:border-slate-800">
                        <div
                            class="grid cursor-pointer grid-cols-[44px_minmax(0,2.2fr)_minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(0,1.1fr)_minmax(0,1.4fr)] items-center gap-3 px-3 py-3 text-sm transition-colors"
                            :class="isOpen(r) ? 'bg-primary-50/60 dark:bg-primary-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'"
                            @click="toggle(r)"
                        >
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-md transition-colors"
                                :class="isOpen(r) ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'"
                                :aria-expanded="isOpen(r)"
                                :aria-label="isOpen(r) ? 'Collapse' : 'Expand'"
                                @click.stop="toggle(r)"
                            >
                                <svg class="h-4 w-4 transition-transform duration-300" :class="isOpen(r) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                            </div>
                            <span class="truncate font-medium text-primary-600 dark:text-primary-400">{{ r.admission_no || '—' }}</span>
                            <span class="truncate text-slate-600 dark:text-slate-300">{{ [r.school_class, r.section].filter(Boolean).join(' / ') || '—' }}</span>
                            <div class="min-w-0">
                                <button
                                    v-if="r.last_payment_id"
                                    type="button"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    :title="`Open receipt ${r.last_receipt_no}`"
                                    @click.stop="openReceipt(r.last_payment_id, r.last_receipt_no)"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                                    <span class="truncate">{{ r.last_receipt_no }}</span>
                                </button>
                                <span v-else class="text-slate-400">—</span>
                                <p v-if="receiptsShown(r) > 1" class="mt-0.5 text-[11px] text-slate-400">+{{ receiptsShown(r) - 1 }} more</p>
                            </div>
                            <span class="text-slate-600 dark:text-slate-300">{{ fmtDate(r.last_paid_date) }}</span>
                            <div class="text-right">
                                <p class="font-semibold text-emerald-600 dark:text-emerald-400">₹ {{ money2(r.last_paid_amount) }}</p>
                                <p v-if="receiptsShown(r) > 1" class="text-[11px] text-slate-400">Total ₹ {{ money2(paidShown(r)) }}</p>
                            </div>
                            <div class="flex min-w-0 flex-wrap items-center gap-1">
                                <template v-if="view === 'paid_any'">
                                    <span
                                        v-if="r.payment_status === 'partial'"
                                        class="inline-block max-w-full truncate rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                        title="Paid some fees; this much is still due up to the current month"
                                    >Due ₹ {{ money(r.due) }}</span>
                                    <span
                                        v-else-if="r.payment_status === 'cleared'"
                                        class="inline-block max-w-full truncate rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                    >Fully paid</span>
                                    <span v-if="r.student_status && r.student_status !== 'Active'" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800">Left school</span>
                                </template>
                                <span
                                    v-else-if="view === 'advance'"
                                    class="inline-block max-w-full truncate rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300"
                                    :title="r.advance_months_label"
                                >Advance · {{ r.advance_months_label }}</span>
                                <span
                                    v-else
                                    class="inline-block max-w-full truncate rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                >Paid till {{ r.paid_through_label }}</span>
                            </div>
                        </div>

                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="-translate-y-1 opacity-0"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="-translate-y-1 opacity-0"
                        >
                            <div v-if="isOpen(r)" class="overflow-hidden">
                                <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900/60">
                                    <div v-if="details[r.student_id]?.loading" class="space-y-2 py-2">
                                        <div v-for="n in 3" :key="n" class="h-8 animate-pulse rounded-lg bg-slate-200/70 dark:bg-slate-800"></div>
                                    </div>
                                    <p v-else-if="details[r.student_id]?.error" class="py-4 text-center text-sm text-rose-500">
                                        Could not load payment details.
                                        <button type="button" class="ml-1 font-semibold underline" @click="loadDetails(r, true)">Retry</button>
                                    </p>
                                    <template v-else-if="details[r.student_id]">
                                        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                                            <div v-for="chip in summaryChips(r)" :key="chip.label" class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ chip.label }}</p>
                                                <p class="mt-0.5 truncate text-sm font-bold" :class="chip.cls || 'text-slate-700 dark:text-slate-100'" :title="chip.value">{{ chip.value }}</p>
                                            </div>
                                        </div>

                                        <div v-if="receivedGap(r)" class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-100">
                                            <p class="font-semibold">
                                                ₹ {{ money2(receivedGap(r).gap) }} of the ₹ {{ money2(r.session_paid) }} received is not counted toward this session's dues
                                                (counted: ₹ {{ money2(r.counted_paid) }}<template v-if="r.advance_paid > 0"> = ₹ {{ money2(r.counted_paid - r.advance_paid) }} up to now, as on Fee Due, + ₹ {{ money2(r.advance_paid) }} for advance months</template>):
                                            </p>
                                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                                <li v-for="(p, i) in receivedGap(r).parts" :key="i">{{ p }}</li>
                                            </ul>
                                        </div>

                                        <p v-if="!details[r.student_id].receipts.length" class="py-4 text-center text-sm text-slate-400">No receipts in this session.</p>

                                        <div class="space-y-4">
                                            <div
                                                v-for="rc in details[r.student_id].receipts"
                                                :key="rc.id"
                                                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                            >
                                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                                    <div>
                                                        <p class="text-[11px] font-semibold uppercase text-slate-400">Receipt</p>
                                                        <button
                                                            type="button"
                                                            class="mt-0.5 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                            :title="`Open receipt ${rc.receipt_no}`"
                                                            @click="openReceipt(rc.id, rc.receipt_no)"
                                                        >
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                                                            {{ rc.receipt_no }}
                                                        </button>
                                                    </div>
                                                    <div>
                                                        <p class="text-[11px] font-semibold uppercase text-slate-400">Paid Date</p>
                                                        <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-200">{{ fmtDate(rc.payment_date) }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[11px] font-semibold uppercase text-slate-400">Mode</p>
                                                        <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-200">{{ rc.payment_mode || '—' }}<span v-if="rc.reference_no" class="text-xs text-slate-400"> · {{ rc.reference_no }}</span></p>
                                                    </div>
                                                    <span v-if="rc.multi_session" class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="This receipt also pays fees of another session">Multi-session</span>
                                                    <span v-if="rc.status && rc.status !== 'Paid'" class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:bg-rose-900/30">{{ rc.status }}</span>
                                                    <div class="ml-auto text-right">
                                                        <p class="text-[11px] font-semibold uppercase text-slate-400">Paid Amount</p>
                                                        <p class="mt-0.5 text-base font-bold text-emerald-600 dark:text-emerald-400">₹ {{ money2(rc.totals.paid) }}</p>
                                                    </div>
                                                </div>

                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-left text-sm">
                                                        <thead class="text-xs text-slate-500">
                                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                                <th class="px-4 py-2 font-semibold">#</th>
                                                                <th class="px-3 py-2 font-semibold">Fee Name</th>
                                                                <th class="px-3 py-2 font-semibold">Fee Type</th>
                                                                <th class="px-3 py-2 font-semibold">Session</th>
                                                                <th class="px-3 py-2 font-semibold">Pay Month</th>
                                                                <th class="px-3 py-2 font-semibold">Pay Year</th>
                                                                <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                                                <th class="px-3 py-2 text-right font-semibold">Discount</th>
                                                                <th class="px-4 py-2 text-right font-semibold">Final Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(l, i) in rc.lines" :key="i" class="border-b border-slate-50 last:border-b-0 hover:bg-slate-50/70 dark:border-slate-800/60 dark:hover:bg-slate-800/40">
                                                                <td class="px-4 py-2 text-slate-400">{{ i + 1 }}</td>
                                                                <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ l.fee_name }}</td>
                                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ l.fee_type || '—' }}</td>
                                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ sessionLabel(l.session) }}</td>
                                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                                                    {{ l.month || '—' }}
                                                                    <span v-if="l.outside_session" class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300" title="This month is outside the line's session — not counted in this session's dues">Outside session</span>
                                                                </td>
                                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ l.year || '—' }}</td>
                                                                <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-200">₹ {{ money2(l.amount) }}</td>
                                                                <td class="px-3 py-2 text-right" :class="l.discount > 0 ? 'text-amber-600' : 'text-slate-500'">₹ {{ money2(l.discount) }}</td>
                                                                <td class="px-4 py-2 text-right font-semibold text-slate-800 dark:text-slate-100">₹ {{ money2(l.final) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class="grid gap-2 border-t border-slate-100 bg-slate-50/50 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800 dark:bg-slate-800/30">
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                        <span class="text-slate-500">Total Fee</span><span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ money2(rc.totals.total_fee) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                        <span class="text-slate-500">Total Discount</span><span class="font-semibold text-amber-600">₹ {{ money2(rc.totals.total_discount) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                        <span class="text-slate-500">Grand Total</span><span class="font-semibold text-slate-700 dark:text-slate-200">₹ {{ money2(rc.totals.grand_total) }}</span>
                                                    </div>
                                                    <div v-if="rc.totals.fine > 0" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                        <span class="text-slate-500">Fine</span><span class="font-semibold text-rose-600">₹ {{ money2(rc.totals.fine) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-900/50 dark:bg-emerald-900/20">
                                                        <span class="font-semibold text-emerald-700 dark:text-emerald-300">Paid Amount</span><span class="font-bold text-emerald-700 dark:text-emerald-300">₹ {{ money2(rc.totals.paid) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm" :class="rc.totals.balance > 0 ? 'border-rose-200 bg-rose-50 dark:border-rose-900/50 dark:bg-rose-900/20' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'">
                                                        <span class="text-slate-500">Total Balance</span><span class="font-semibold" :class="rc.totals.balance > 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200'">₹ {{ money2(rc.totals.balance) }}</span>
                                                    </div>
                                                    <div v-if="rc.totals.refunded > 0" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                        <span class="text-slate-500">Refunded</span><span class="font-semibold text-rose-600">₹ {{ money2(rc.totals.refunded) }}</span>
                                                    </div>
                                                </div>
                                                <p v-if="rc.remarks" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400 dark:border-slate-800">Remarks: {{ rc.remarks }}</p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
            <div v-if="rows.length" class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row dark:border-slate-800">
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    Show
                    <select v-model="perPageSelection" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800">
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt === 'all' ? 'All' : opt }}</option>
                    </select>
                    per page
                </label>
                <p v-if="perPageSelection === 'all'" class="text-xs text-slate-500 dark:text-slate-400">Showing all {{ rows.length }} entries</p>
            </div>
            <Pagination v-if="perPageSelection !== 'all' && rows.length > perPage" v-model="page" :per-page="perPage" :total="rows.length" />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Dropdown from '../../components/common/Dropdown.vue';
import Pagination from '../../components/common/Pagination.vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { openPdfBlob } from '../../utils/documentPdf';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const exporting = ref(false);
const rows = ref([]);
// "All with payments" is the default: a student who has paid anything is always listed,
// whether or not they still owe fees. The other two views are the stricter filters.
const VIEWS = [
    { key: 'paid_any', label: 'All with payments' },
    { key: 'till_current', label: 'Fully paid till now' },
    { key: 'advance', label: 'Advance months' },
];
const view = ref('paid_any');
const viewMeta = computed(() => ({
    paid_any: {
        title: 'All Students With Payments',
        hint: 'Every student who paid anything this session — with what is still due shown separately.',
    },
    till_current: {
        title: 'Fully Paid Till Current Month',
        hint: 'Only students with zero due for every month up to the current month (same math as Fee Due).',
    },
    advance: {
        title: 'Advance Fee Paid Students',
        hint: 'Students with payments applied to months after the current month.',
    },
}[view.value]));
const summary = reactive({ students: 0, charged: 0, paid: 0, concession: 0, advance_paid: 0, collected: 0, due: 0, cleared: 0, partial: 0, range_collected: 0 });

const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const vehicles = ref([]);

const filters = reactive({
    branch_id: null,
    school_class_id: null,
    section_id: null,
    search: '',
    vehicle: '',
    dues: 'all',
    // Paid on: '' any date, 'date' one day, 'month' one month, 'range' between two dates.
    paid_mode: '',
    paid_date: '',
    paid_month: '',
    paid_from: '',
    paid_to: '',
});

/** The Paid-on filter as { from, to, label } (Y-m-d), or null when off / not filled in yet. */
const paidRange = computed(() => {
    const short = (d) => fmtDate(d);
    if (filters.paid_mode === 'date' && filters.paid_date) {
        return { from: filters.paid_date, to: filters.paid_date, label: `on ${short(filters.paid_date)}` };
    }
    if (filters.paid_mode === 'month' && filters.paid_month) {
        const [y, m] = filters.paid_month.split('-').map(Number);
        const last = new Date(y, m, 0).getDate();
        const label = new Date(y, m - 1, 1).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
        return { from: `${filters.paid_month}-01`, to: `${filters.paid_month}-${String(last).padStart(2, '0')}`, label: `in ${label}` };
    }
    if (filters.paid_mode === 'range' && (filters.paid_from || filters.paid_to)) {
        const { paid_from: from, paid_to: to } = filters;
        const label = from && to ? `${short(from)} – ${short(to)}` : from ? `from ${short(from)}` : `till ${short(to)}`;
        return { from: from || null, to: to || null, label };
    }
    return null;
});
// With a Paid-on filter, the row's receipts / total are the ones in that period.
function receiptsShown(r) {
    return paidRange.value ? r.range_receipts : r.receipts_count;
}
function paidShown(r) {
    return paidRange.value ? r.range_paid : r.session_paid;
}

const perPageOptions = [10, 25, 50, 100, 'all'];
const perPageSelection = ref(25);
const perPage = computed(() => (perPageSelection.value === 'all' ? Math.max(rows.value.length, 1) : perPageSelection.value));
const page = ref(1);
const filterSections = computed(() =>
    sections.value.filter((s) => !filters.school_class_id || s.school_class_id === filters.school_class_id),
);
const pagedRows = computed(() => {
    if (perPageSelection.value === 'all') return rows.value;
    const start = (page.value - 1) * perPage.value;
    return rows.value.slice(start, start + perPage.value);
});

function money(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function money2(n) {
    return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(`${d}T00:00:00`);
    return Number.isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** "2026-2027" → "2026 - 2027" */
function sessionLabel(s) {
    return s ? String(s).replace(/\s*-\s*/, ' - ') : '—';
}

// ---- Expandable rows: fee lines grouped by receipt, loaded on first open ----
const openIds = ref(new Set());
const details = reactive({});

function isOpen(r) {
    return openIds.value.has(r.student_id);
}

function toggle(r) {
    const next = new Set(openIds.value);
    if (next.has(r.student_id)) {
        next.delete(r.student_id);
    } else {
        next.add(r.student_id);
        loadDetails(r);
    }
    openIds.value = next;
}

async function loadDetails(r, force = false) {
    if (!force && details[r.student_id] && !details[r.student_id].error) return;
    details[r.student_id] = { loading: true };
    try {
        const { data } = await client.get(`/fee-management/fee-paid/${r.student_id}/details`);
        details[r.student_id] = { ...data, loading: false };
    } catch {
        details[r.student_id] = { loading: false, error: true };
    }
}

function summaryChips(r) {
    const d = details[r.student_id] || {};
    const s = d.summary || {};
    const chips = [
        { label: 'Receipts', value: String(s.receipts ?? 0) },
        { label: 'Total received', value: `₹ ${money2(s.paid)}`, cls: 'text-emerald-600 dark:text-emerald-400' },
        { label: 'Counted toward dues', value: `₹ ${money2(r.counted_paid)}`, cls: 'text-emerald-700 dark:text-emerald-300' },
        { label: view.value === 'advance' ? 'Advance Charge' : 'Charged (till now)', value: `₹ ${money2(r.total_fee)}` },
        { label: 'Concession', value: `₹ ${money2(r.total_discount)}`, cls: 'text-amber-600' },
        { label: 'Outstanding (till now)', value: `₹ ${money2(r.due)}`, cls: r.due > 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-100' },
        { label: 'Paid Through', value: r.paid_through_label || '—' },
        { label: 'Advance Months', value: r.advance_months_label || '—', cls: 'text-sky-600 dark:text-sky-400' },
    ];
    return chips;
}

/** Why "Total received" is more than "Counted toward dues" — every rupee accounted for. */
function receivedGap(r) {
    const parts = [];
    if (r.outside_session_paid > 0) parts.push(`₹ ${money2(r.outside_session_paid)} for ${r.outside_session_months || 'months'} — outside this session, so no charge of this session exists for it`);
    if (r.fine_paid > 0) parts.push(`₹ ${money2(r.fine_paid)} fine / late fee — collected on top of fees, not a fee payment`);
    if (r.unmatched_paid > 0) parts.push(`₹ ${money2(r.unmatched_paid)} paid toward a fee head with no charge set up, or more than the month's charge`);
    const gap = Number(r.session_paid || 0) - Number(r.counted_paid || 0);
    return gap > 0.01 && parts.length ? { gap, parts } : null;
}

/** Opens the full receipt PDF in a new tab (tab opened synchronously so pop-up blockers allow it). */
async function openReceipt(paymentId, receiptNo) {
    const tab = window.open('about:blank', '_blank');
    try {
        if (tab) tab.document.title = `Receipt ${receiptNo || ''}`.trim();
    } catch {
        // ignore cross-origin about:blank
    }
    try {
        const { data } = await client.get(`/fee-management/payments/${paymentId}/pdf`, { responseType: 'blob' });
        openPdfBlob(data, tab);
    } catch {
        if (tab && !tab.closed) tab.close();
        pushToast('Could not open the receipt.', 'error');
    }
}

function currentParams() {
    const p = { view: view.value };
    if (filters.branch_id) p.branch_id = filters.branch_id;
    if (filters.school_class_id) p.school_class_id = filters.school_class_id;
    if (filters.section_id) p.section_id = filters.section_id;
    if (filters.vehicle) p.vehicle = filters.vehicle;
    if (filters.dues !== 'all') p.dues = filters.dues;
    if (filters.search.trim()) p.search = filters.search.trim();
    if (paidRange.value?.from) p.paid_from = paidRange.value.from;
    if (paidRange.value?.to) p.paid_to = paidRange.value.to;
    return p;
}

async function reload() {
    loading.value = true;
    page.value = 1;
    openIds.value = new Set();
    Object.keys(details).forEach((k) => delete details[k]);
    try {
        const { data } = await client.get('/fee-management/fee-paid', { params: currentParams() });
        rows.value = data.rows || [];
        vehicles.value = data.vehicles || vehicles.value;
        Object.assign(summary, data.summary || {});
    } catch {
        rows.value = [];
        pushToast('Could not load fee paid list.', 'error');
    } finally {
        loading.value = false;
    }
}

async function runExport(format) {
    exporting.value = true;
    try {
        const response = await client.get('/fee-management/fee-paid/export', {
            params: { ...currentParams(), format },
            responseType: 'blob',
        });
        const ext = format === 'xlsx' ? 'xlsx' : format;
        const url = URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `fee-paid-${view.value}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
        pushToast('Export ready.', 'success');
    } catch {
        pushToast('Export failed.', 'error');
    } finally {
        exporting.value = false;
    }
}

// ---- Print (in the browser): every row matching the filters, same columns as the table ----
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function printPaid() {
    const list = rows.value;
    const amt = (n, cls = '') => `<td class="num ${cls}">${money(n)}</td>`;
    const sum = (key) => list.reduce((s, r) => s + (Number(r[key]) || 0), 0);
    const advance = view.value === 'advance';
    const all = view.value === 'paid_any';
    const range = paidRange.value;
    const paidKey = range ? 'range_paid' : 'session_paid';
    const title = range ? `${viewMeta.value.title} — Paid ${range.label}` : viewMeta.value.title;
    const head = '<tr><th>#</th><th>Name</th><th>Adm. No.</th><th>Class / Sec</th><th>Last Receipt</th><th>Paid Date</th>'
        + `<th class="num">${range ? 'Paid in Period' : 'Total Paid'}</th>${all ? '<th class="num">Outstanding</th>' : ''}<th>${advance ? 'Advance months' : 'Paid through'}</th></tr>`;
    const body = list.map((r, i) => `<tr><td>${i + 1}</td><td><b>${esc(r.name)}</b>${r.student_status && r.student_status !== 'Active' ? ' (left)' : ''}</td><td>${esc(r.admission_no)}</td>
        <td>${esc([r.school_class, r.section].filter(Boolean).join(' / ') || '—')}</td>
        <td>${esc(r.last_receipt_no || '—')}</td><td>${esc(fmtDate(r.last_paid_date))}</td>
        ${amt(r[paidKey], 'paid')}${all ? amt(r.due) : ''}
        <td>${esc((advance ? r.advance_months_label : r.paid_through_label) || '—')}</td></tr>`).join('');
    const foot = `<tr><td></td><td colspan="5">TOTAL (${list.length} students)</td>${amt(sum(paidKey), 'paid')}${all ? amt(sum('due')) : ''}<td></td></tr>`;

    const win = window.open('', '_blank');
    if (!win) {
        pushToast('Allow pop-ups for this site to print.', 'error');
        return;
    }
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; }
        .school { text-align: center; font-size: 16px; font-weight: bold; }
        h1 { font-size: 13px; text-align: center; margin: 4px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #999; padding: 3px 5px; vertical-align: top; }
        th { background: #eee; text-align: left; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; } .paid { color: #047857; font-weight: bold; }
        tfoot td { font-weight: bold; background: #f3f3f3; }
    </style></head><body>
        <div class="school">${esc(erpStore.school?.school_name || '')}</div>
        <h1>${esc(title)}</h1>
        <table><thead>${head}</thead><tbody>${body}</tbody><tfoot>${foot}</tfoot></table>
        <script>window.onload = function () { window.focus(); window.print(); };<\/script>
    </body></html>`);
    win.document.close();
}

watch(() => filters.school_class_id, () => {
    filters.section_id = null;
});
watch(perPageSelection, () => { page.value = 1; });

let loadTimer;
watch([filters, view], () => {
    clearTimeout(loadTimer);
    loadTimer = setTimeout(reload, 250);
}, { deep: true });
// The header Academic Session drives this page.
watch(() => erpStore.currentSession, () => reload());

onMounted(async () => {
    const data = await fetchAcademicsLookups();
    branches.value = data.branches || [];
    classes.value = data.classes || [];
    sections.value = data.sections || [];
    await reload();
});
</script>
