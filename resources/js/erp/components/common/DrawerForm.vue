<template>
    <transition name="drawer">
        <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="$emit('close')" />
            <div class="relative flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="$emit('close')">×</button>
                </div>
                <form class="flex-1 space-y-4 overflow-y-auto px-5 py-5" @submit.prevent="submit">
                    <div v-for="col in fields" :key="col.key">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ col.label }}</label>
                        <select
                            v-if="col.type === 'status'"
                            v-model="form[col.key]"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                        <input
                            v-else
                            v-model="form[col.key]"
                            :type="col.type === 'amount' || col.type === 'number' ? 'number' : col.type === 'date' ? 'date' : 'text'"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            :placeholder="`Enter ${col.label}`"
                        />
                    </div>
                </form>
                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" @click="$emit('close')">Cancel</button>
                    <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700" @click="submit">Save</button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Add Record' },
    columns: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'save']);

const fields = props.columns.filter((c) => c.type !== 'photo');
const form = reactive({});

watch(
    () => props.open,
    (val) => {
        if (val) fields.forEach((f) => (form[f.key] = f.type === 'status' ? 'Active' : ''));
    },
);

function submit() {
    emit('save', { ...form });
}
</script>

<style scoped>
.drawer-enter-active .relative,
.drawer-leave-active .relative {
    transition: transform 0.25s ease;
}
.drawer-enter-from .relative,
.drawer-leave-to .relative {
    transform: translateX(100%);
}
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.2s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
</style>
