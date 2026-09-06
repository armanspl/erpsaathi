<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th v-if="selectable" class="w-10 px-4 py-3">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" :checked="allSelected" @change="toggleAll($event.target.checked)" />
                        </th>
                        <th v-for="col in columns" :key="col.key" class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ col.label }}
                        </th>
                        <th v-if="actions.length" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td :colspan="columns.length + (selectable ? 1 : 0) + (actions.length ? 1 : 0)" class="px-4 py-10 text-center text-sm text-slate-400">
                            Loading...
                        </td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td :colspan="columns.length + (selectable ? 1 : 0) + (actions.length ? 1 : 0)" class="px-4 py-10 text-center text-sm text-slate-400">
                            No records match your filters.
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td v-if="selectable" class="px-4 py-3">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" :checked="selected.has(row.id)" @change="toggleOne(row.id, $event.target.checked)" />
                        </td>
                        <td v-for="col in columns" :key="col.key" class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            <div v-if="col.type === 'photo'" class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                                {{ initials(row.__name || row.name || '—') }}
                            </div>
                            <span v-else-if="col.type === 'status'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(row[col.key])">
                                {{ row[col.key] }}
                            </span>
                            <span v-else-if="col.type === 'amount'" class="font-medium text-slate-800 dark:text-slate-100">₹{{ Number(row[col.key]).toLocaleString('en-IN') }}</span>
                            <span v-else-if="col.type === 'name'" class="font-medium text-slate-800 dark:text-slate-100">{{ row[col.key] }}</span>
                            <span v-else class="text-slate-600 dark:text-slate-300">{{ row[col.key] }}</span>
                        </td>
                        <td v-if="actions.length" class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-for="act in actions"
                                    :key="act"
                                    type="button"
                                    :title="actionLabel(act)"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800 dark:hover:text-primary-400"
                                    @click="$emit('action', { type: act, row })"
                                    v-html="actionIcon(act)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { statusBadgeClass } from '../../utils/colors';
import { initials } from '../../utils/mock';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    selectable: { type: Boolean, default: true },
    actions: { type: Array, default: () => ['view', 'edit', 'delete'] },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['action']);

const selected = reactive(new Set());

const allSelected = computed(() => props.rows.length > 0 && props.rows.every((r) => selected.has(r.id)));

function toggleAll(checked) {
    if (checked) props.rows.forEach((r) => selected.add(r.id));
    else selected.clear();
}
function toggleOne(id, checked) {
    checked ? selected.add(id) : selected.delete(id);
}

const ICONS = {
    view: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
    edit: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>',
    delete: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>',
    print: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v8H6Z"/></svg>',
    idcard: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="11.5" r="1.5"/><path stroke-linecap="round" d="M13 10h6M13 14h4M5 17c.5-1.5 2-2 3.5-2s3 .5 3.5 2"/></svg>',
    certificate: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 14-1 7 4-2 4 2-1-7"/></svg>',
    transfer: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m17 3 4 4-4 4M3 7h18M7 21l-4-4 4-4m-4 4h18"/></svg>',
    promote: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5"/></svg>',
};

function actionIcon(act) {
    return ICONS[act] || ICONS.view;
}
function actionLabel(act) {
    return { view: 'View', edit: 'Edit', delete: 'Delete', print: 'Print', idcard: 'ID Card', certificate: 'Certificate', transfer: 'Transfer', promote: 'Promote' }[act] || act;
}
</script>
