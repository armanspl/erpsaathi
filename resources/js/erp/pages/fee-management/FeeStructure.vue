<template>
    <div class="space-y-5">
        <!-- LIST -->
        <template v-if="mode === 'list'">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Fee Structure</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Define faculty and transport fee structures for the session.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openCreate">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
                        Bulk create
                    </button>
                    <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                        <span class="text-lg leading-none">+</span> Create Fee Structure
                    </button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Branch</label>
                    <select v-model="filters.branch_id" class="form-input" @change="load">
                        <option :value="null">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="filters.school_class_id" class="form-input" @change="load">
                        <option :value="null">All classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                    <button
                        v-for="v in viewModes"
                        :key="v.id"
                        type="button"
                        class="rounded-md p-1.5"
                        :class="viewMode === v.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800' : 'text-slate-400'"
                        @click="viewMode = v.id"
                        v-html="v.icon"
                    />
                </div>

                <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
                <div v-else-if="!sortedPlans.length" class="px-6 py-16 text-center text-sm text-slate-400">No fee structures yet. Create one to get started.</div>

                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-8 px-3 py-3" />
                            <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('title')">Title</th>
                            <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('type')">Type</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Scope</th>
                            <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('amount')">Amount</th>
                            <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template v-for="plan in sortedPlans" :key="plan.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-3 py-3">
                                    <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="toggleExpand(plan.id)">
                                        <svg class="h-4 w-4 transition" :class="expanded[plan.id] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ plan.title }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ plan.is_transport
                                            ? `${plan.scope_count} stop${plan.scope_count === 1 ? '' : 's'}`
                                            : `${plan.scope_count} scope${plan.scope_count === 1 ? '' : 's'}` }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ plan.type }}</span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-xs text-slate-500" :title="plan.scope_summary">{{ plan.scope_summary || '—' }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">
                                    <span v-if="plan.amount_range">₹{{ formatMoney(plan.amount_range.min) }} – ₹{{ formatMoney(plan.amount_range.max) }}</span>
                                    <span v-else>₹{{ formatMoney(plan.amount) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium text-white" :class="plan.status === 'active' ? 'bg-primary-600' : 'bg-slate-400'">{{ plan.status }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" title="Edit" @click="openEdit(plan)">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M4 20h4l10.5-10.5a2.5 2.5 0 00-3.536-3.536L4 16.464V20z"/></svg>
                                        </button>
                                        <button v-if="!plan.is_transport" type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" title="Duplicate" @click="duplicate(plan)">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 8h10a2 2 0 012 2v10a2 2 0 01-2 2H8a2 2 0 01-2-2V10a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 16H5a2 2 0 01-2-2V5a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" title="Delete" @click="remove(plan)">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expanded[plan.id]" class="bg-slate-50/70 dark:bg-slate-800/30">
                                <td colspan="7" class="px-6 py-3">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ plan.is_transport ? 'Stop fares' : 'Fee types' }}</p>
                                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        <div v-for="item in plan.items" :key="item.id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900">
                                            <div class="font-medium text-slate-700 dark:text-slate-200">{{ item.label }}</div>
                                            <div class="mt-0.5 text-slate-500">
                                                ₹{{ formatMoney(item.amount) }}<span v-if="!plan.is_transport"> · {{ frequencyLabel(item.frequency) }}</span>
                                                <span v-if="item.km != null && item.km !== ''"> · {{ item.km }} km</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- CREATE / EDIT -->
        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="mode = 'list'">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Back
                </button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <label class="form-label">Fee structure type</label>
                <select v-model="form.type" class="form-input" @change="onTypeChange">
                    <option value="Faculty">Faculty</option>
                    <option value="Transport">Transport</option>
                </select>
            </div>

            <!-- Transport: select existing routes and set stop fees -->
            <div v-if="form.type === 'Transport'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Transport Fee Structure</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Select routes created under Transport Management, then set fare per stop.</p>
                    </div>
                    <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="!canAddRouteFee" @click="addRouteFee">+ Add Route</button>
                </div>

                <p v-if="transportLoading" class="py-8 text-center text-sm text-slate-400">Loading routes...</p>
                <p v-else-if="!transportCatalog.length" class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400 dark:border-slate-700">
                    No routes found. Create routes under <strong>Transport Management → Routes</strong> first.
                </p>

                <div v-else class="space-y-4">
                    <div
                        v-for="(entry, entryIndex) in form.routeFees"
                        :key="entryIndex"
                        class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <div class="grid items-end gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                            <div>
                                <label class="form-label">Route</label>
                                <select v-model="entry.route_id" class="form-input" @change="onRouteSelected(entry)">
                                    <option :value="null">— Select route —</option>
                                    <option
                                        v-for="r in routesForPicker(entry.route_id)"
                                        :key="r.id"
                                        :value="r.id"
                                    >
                                        {{ routeOptionLabel(r) }}
                                    </option>
                                </select>
                                <p v-if="routeMeta(entry.route_id)" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ routeMeta(entry.route_id).start_point || '—' }} → {{ routeMeta(entry.route_id).end_point || '—' }}
                                    <span v-if="routeMeta(entry.route_id).vehicle?.vehicle_no"> · Bus {{ routeMeta(entry.route_id).vehicle.vehicle_no }}</span>
                                    <span v-if="routeMeta(entry.route_id).branch?.name"> · {{ routeMeta(entry.route_id).branch.name }}</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="mb-0.5 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-40"
                                :disabled="form.routeFees.length <= 1"
                                title="Remove"
                                @click="removeRouteFee(entryIndex)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        </div>

                        <div v-if="entry.route_id" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Stop fares</h3>
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="addStop(entry)">+ Add address</button>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(stop, stopIndex) in entry.stops"
                                    :key="stopIndex"
                                    class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_120px_100px_auto]"
                                >
                                    <div>
                                        <label v-if="stopIndex === 0" class="form-label">Address</label>
                                        <input v-model="stop.stop_name" type="text" class="form-input" placeholder="Stop / locality" />
                                    </div>
                                    <div>
                                        <label v-if="stopIndex === 0" class="form-label">Fee</label>
                                        <input v-model.number="stop.fare" type="number" min="0" step="0.01" class="form-input" />
                                    </div>
                                    <div>
                                        <label v-if="stopIndex === 0" class="form-label">KM</label>
                                        <input v-model="stop.km" type="number" min="0" step="0.1" class="form-input" placeholder="Optional" />
                                    </div>
                                    <button
                                        type="button"
                                        class="mb-0.5 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-40"
                                        :disabled="entry.stops.length <= 1"
                                        title="Remove address"
                                        @click="removeStop(entry, stopIndex)"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Branch / Class / Section</h2>
                    <select v-model="form.academic_session_id" class="form-input !w-auto !py-1.5 !text-xs">
                        <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div v-if="!branches.length" class="space-y-2">
                    <p class="mb-2 text-xs text-slate-400">No branches configured — selecting classes applies school-wide.</p>
                    <div
                        v-for="cls in classes"
                        :key="`all-${cls.id}`"
                        class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700"
                    >
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                :checked="isClassSelected(null, cls.id)"
                                @change="toggleClass(null, cls.id, $event.target.checked)"
                            />
                            {{ cls.name }}
                        </label>
                        <div class="mt-2 space-y-1 pl-6">
                            <label
                                v-for="sec in sectionsFor(cls.id)"
                                :key="sec.id"
                                class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    :checked="isSectionSelected(null, cls.id, sec.id)"
                                    @change="toggleSection(null, cls.id, sec.id, $event.target.checked)"
                                />
                                {{ sec.name }}
                            </label>
                        </div>
                    </div>
                </div>
                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ branch.name }}</h3>
                        <div class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="cls in classes"
                                :key="`${branch.id}-${cls.id}`"
                                class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700"
                            >
                                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                        :checked="isClassSelected(branch.id, cls.id)"
                                        @change="toggleClass(branch.id, cls.id, $event.target.checked)"
                                    />
                                    {{ cls.name }}
                                </label>
                                <div class="mt-2 space-y-1 pl-6">
                                    <label
                                        v-for="sec in sectionsFor(cls.id)"
                                        :key="sec.id"
                                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                            :checked="isSectionSelected(branch.id, cls.id, sec.id)"
                                            @change="toggleSection(branch.id, cls.id, sec.id, $event.target.checked)"
                                        />
                                        {{ sec.name }}
                                    </label>
                                    <p v-if="!sectionsFor(cls.id).length" class="text-xs text-slate-400">No sections</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Fee Types</h2>
                    <button type="button" class="btn-outline !py-1.5 !text-xs" @click="addItem">+ Add More</button>
                </div>
                <div class="space-y-3">
                    <div v-for="(item, index) in form.items" :key="index" class="grid items-end gap-3 sm:grid-cols-[1fr_140px_160px_auto]">
                        <div>
                            <label class="form-label">Label</label>
                            <input v-model="item.label" type="text" class="form-input" placeholder="e.g. Tuition" />
                        </div>
                        <div>
                            <label class="form-label">Fee</label>
                            <input v-model.number="item.amount" type="number" min="0" step="0.01" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Fee timing</label>
                            <select v-model="item.frequency" class="form-input">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                                <option value="one_time">One time</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="mb-0.5 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-40"
                            :disabled="form.items.length <= 1"
                            @click="removeItem(index)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { erpStore, matchesSelectedSession } from '../../store';
import { pushToast } from '../../utils/toast';

const mode = ref('list');
const loading = ref(true);
const saving = ref(false);
const transportLoading = ref(false);
const transportCatalog = ref([]);
const plans = ref([]);
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const sessions = ref([]);
const expanded = reactive({});
const viewMode = ref('table');
const sortKey = ref('title');
const sortDir = ref('asc');

const filters = reactive({ branch_id: null, school_class_id: null });
const editingId = ref(null);
const form = reactive({
    academic_session_id: null,
    type: 'Faculty',
    status: 'active',
    scopes: [],
    items: [{ label: '', amount: null, frequency: 'monthly' }],
    routeFees: [],
});

const viewModes = [
    { id: 'table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const sessionFiltered = computed(() => plans.value.filter((p) => p.is_transport || matchesSelectedSession(p.academic_session?.name)));

const sortedPlans = computed(() => {
    const rows = [...sessionFiltered.value];
    rows.sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        if (sortKey.value === 'title') {
            av = a.title;
            bv = b.title;
        }
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
    return rows;
});

function formatMoney(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function frequencyLabel(f) {
    return { monthly: 'Monthly', quarterly: 'Quarterly', annual: 'Annual', one_time: 'One time' }[f] || f;
}

function sectionsFor(classId) {
    return sections.value.filter((s) => s.school_class_id === classId);
}

function toggleSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}

function toggleExpand(id) {
    expanded[id] = !expanded[id];
}

function defaultSessionId() {
    const currentName = erpStore.currentSession;
    const match = sessions.value.find((s) => s.name === currentName);
    if (match) return match.id;
    const current = sessions.value.find((s) => s.is_current);
    return current?.id || sessions.value[0]?.id || null;
}

function isClassSelected(branchId, classId) {
    return form.scopes.some((s) => s.branch_id == branchId && s.school_class_id === classId);
}

function isSectionSelected(branchId, classId, sectionId) {
    return form.scopes.some((s) => s.branch_id == branchId && s.school_class_id === classId && s.section_id == sectionId);
}

function toggleClass(branchId, classId, checked) {
    if (checked) {
        const secs = sectionsFor(classId);
        if (secs.length) {
            secs.forEach((sec) => {
                if (!isSectionSelected(branchId, classId, sec.id)) {
                    form.scopes.push({ branch_id: branchId, school_class_id: classId, section_id: sec.id });
                }
            });
        } else if (!form.scopes.some((s) => s.branch_id == branchId && s.school_class_id === classId && s.section_id == null)) {
            form.scopes.push({ branch_id: branchId, school_class_id: classId, section_id: null });
        }
    } else {
        form.scopes = form.scopes.filter((s) => !(s.branch_id == branchId && s.school_class_id === classId));
    }
}

function toggleSection(branchId, classId, sectionId, checked) {
    if (checked) {
        if (!isSectionSelected(branchId, classId, sectionId)) {
            form.scopes.push({ branch_id: branchId, school_class_id: classId, section_id: sectionId });
        }
    } else {
        form.scopes = form.scopes.filter((s) => !(s.branch_id == branchId && s.school_class_id === classId && s.section_id == sectionId));
    }
}

function addItem() {
    form.items.push({ label: '', amount: null, frequency: 'monthly' });
}

function removeItem(index) {
    if (form.items.length <= 1) return;
    form.items.splice(index, 1);
}

const canAddRouteFee = computed(() => {
    const picked = new Set(form.routeFees.map((e) => e.route_id).filter(Boolean));
    return transportCatalog.value.some((r) => !picked.has(r.id));
});

function routeOptionLabel(r) {
    const parts = [r.name];
    if (r.vehicle?.vehicle_no) parts.push(`Bus ${r.vehicle.vehicle_no}`);
    if (r.route_code) parts.push(r.route_code);
    return parts.join(' · ');
}

function routeMeta(routeId) {
    if (!routeId) return null;
    return transportCatalog.value.find((r) => r.id === routeId) || null;
}

function routesForPicker(currentRouteId) {
    const picked = new Set(form.routeFees.map((e) => e.route_id).filter((id) => id && id !== currentRouteId));
    return transportCatalog.value.filter((r) => !picked.has(r.id));
}

function emptyStop() {
    return { id: null, stop_name: '', fare: null, km: null };
}

function emptyRouteFeeEntry() {
    return { route_id: null, stops: [emptyStop()] };
}

function stopsFromRoute(route) {
    if (!route) return [emptyStop()];
    if ((route.stops || []).length) {
        return route.stops.map((s) => ({
            id: s.id,
            stop_name: s.stop_name,
            fare: s.fare != null ? Number(s.fare) : null,
            km: s.km != null ? Number(s.km) : null,
        }));
    }
    return [{ id: null, stop_name: route.start_point || '', fare: null, km: null }];
}

function onRouteSelected(entry) {
    entry.stops = stopsFromRoute(routeMeta(entry.route_id));
}

function addRouteFee() {
    form.routeFees.push(emptyRouteFeeEntry());
}

function removeRouteFee(index) {
    form.routeFees.splice(index, 1);
}

function addStop(entry) {
    entry.stops.push(emptyStop());
}

function removeStop(entry, index) {
    if (entry.stops.length <= 1) return;
    entry.stops.splice(index, 1);
}

function buildRouteFeesFromCatalog() {
    const withStops = transportCatalog.value.filter((r) => (r.stops || []).length > 0);
    if (withStops.length) {
        form.routeFees = withStops.map((r) => ({ route_id: r.id, stops: stopsFromRoute(r) }));
    } else if (transportCatalog.value.length === 1) {
        form.routeFees = [{ route_id: transportCatalog.value[0].id, stops: stopsFromRoute(transportCatalog.value[0]) }];
    } else {
        form.routeFees = [emptyRouteFeeEntry()];
    }
}

async function loadTransportCatalog() {
    transportLoading.value = true;
    try {
        const { data } = await client.get('/fee-management/transport-routes', { params: { with_stops: 1 } });
        transportCatalog.value = data || [];
        buildRouteFeesFromCatalog();
    } finally {
        transportLoading.value = false;
    }
}

async function onTypeChange() {
    if (form.type === 'Transport') {
        await loadTransportCatalog();
    }
}

function resetForm() {
    editingId.value = null;
    Object.assign(form, {
        academic_session_id: defaultSessionId(),
        type: 'Faculty',
        status: 'active',
        scopes: [],
        items: [{ label: '', amount: null, frequency: 'monthly' }],
        routeFees: [],
    });
}

function openCreate() {
    resetForm();
    mode.value = 'edit';
}

function openEdit(plan) {
    if (plan.is_transport) {
        editingId.value = null;
        Object.assign(form, {
            academic_session_id: defaultSessionId(),
            type: 'Transport',
            status: 'active',
            scopes: [],
            items: [{ label: '', amount: null, frequency: 'monthly' }],
            routeFees: [],
        });
        mode.value = 'edit';
        loadTransportCatalog().then(() => {
            const routeId = plan.transport_route_id;
            const catalogRoute = transportCatalog.value.find((r) => r.id === routeId);
            form.routeFees = [{
                route_id: routeId,
                stops: catalogRoute
                    ? stopsFromRoute(catalogRoute)
                    : (plan.items || []).map((i) => ({
                        id: i.id,
                        stop_name: i.label,
                        fare: i.amount,
                        km: i.km ?? null,
                    })),
            }];
        });
        return;
    }

    editingId.value = plan.id;
    Object.assign(form, {
        academic_session_id: plan.academic_session_id,
        type: plan.type,
        status: plan.status,
        scopes: (plan.scopes || []).map((s) => ({
            branch_id: s.branch_id,
            school_class_id: s.school_class_id,
            section_id: s.section_id,
        })),
        items: (plan.items || []).length
            ? plan.items.map((i) => ({ label: i.label, amount: i.amount, frequency: i.frequency }))
            : [{ label: '', amount: null, frequency: 'monthly' }],
        routeFees: [],
    });
    mode.value = 'edit';
    if (plan.type === 'Transport') {
        loadTransportCatalog();
    }
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (filters.branch_id) params.branch_id = filters.branch_id;
        if (filters.school_class_id) params.school_class_id = filters.school_class_id;
        const { data } = await client.get('/fee-management/structures', { params });
        plans.value = data;
    } finally {
        loading.value = false;
    }
}

async function loadLookups() {
    const [lookups, sess] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/settings/academic-sessions'),
    ]);
    branches.value = lookups.branches || [];
    classes.value = lookups.classes || [];
    sections.value = lookups.sections || [];
    sessions.value = Array.isArray(sess.data) ? sess.data : sess.data.data || [];
}

async function save() {
    if (form.type === 'Transport') {
        return saveTransportRoutes();
    }

    if (!form.academic_session_id) {
        pushToast('Select an academic session.', 'error');
        return;
    }
    if (!form.scopes.length) {
        pushToast('Select at least one class or section.', 'error');
        return;
    }
    const items = form.items.filter((i) => String(i.label || '').trim());
    if (!items.length) {
        pushToast('Add at least one fee type with a label.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            academic_session_id: form.academic_session_id,
            type: form.type,
            status: form.status,
            scopes: form.scopes,
            items: items.map((i) => ({
                label: String(i.label).trim(),
                amount: Number(i.amount || 0),
                frequency: i.frequency,
            })),
        };
        if (editingId.value) {
            await client.put(`/fee-management/structures/${editingId.value}`, payload);
            pushToast('Fee structure updated.', 'success');
        } else {
            await client.post('/fee-management/structures', payload);
            pushToast('Fee structure created.', 'success');
        }
        mode.value = 'list';
        await load();
    } finally {
        saving.value = false;
    }
}

async function saveTransportRoutes() {
    const entries = form.routeFees.filter((e) => e.route_id);
    if (!entries.length) {
        pushToast('Select at least one route.', 'error');
        return;
    }

    for (const entry of entries) {
        const route = routeMeta(entry.route_id);
        const stops = (entry.stops || []).filter((s) => String(s.stop_name || '').trim());
        if (!stops.length) {
            pushToast(`Add at least one stop with fare for "${route?.name || 'route'}".`, 'error');
            return;
        }
    }

    saving.value = true;
    try {
        await client.post('/fee-management/transport-routes/fees', {
            routes: entries.map((e) => ({
                id: e.route_id,
                stops: (e.stops || [])
                    .filter((s) => String(s.stop_name || '').trim())
                    .map((s) => ({
                        id: s.id || undefined,
                        stop_name: String(s.stop_name).trim(),
                        fare: Number(s.fare || 0),
                        km: s.km === '' || s.km == null ? null : Number(s.km),
                    })),
            })),
        });
        pushToast('Transport fees saved.', 'success');
        mode.value = 'list';
        await load();
    } finally {
        saving.value = false;
    }
}

async function duplicate(plan) {
    await client.post(`/fee-management/structures/${plan.id}/duplicate`);
    pushToast('Fee structure duplicated.', 'success');
    await load();
}

async function remove(plan) {
    const label = plan.is_transport ? `transport fees for ${plan.title}` : plan.title;
    if (!window.confirm(`Delete ${label}?`)) return;

    if (plan.is_transport) {
        await client.delete(`/fee-management/transport-routes/${plan.transport_route_id}/fees`);
        pushToast('Transport fees removed.', 'success');
    } else {
        await client.delete(`/fee-management/structures/${plan.id}`);
        pushToast('Fee structure deleted.', 'success');
    }
    await load();
}

onMounted(async () => {
    await loadLookups();
    await load();
});
</script>
