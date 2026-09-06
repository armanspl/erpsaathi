<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <template v-for="f in filters" :key="f.key">
                <div v-if="f.type === 'search'" class="relative xl:col-span-2">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" /><path stroke-linecap="round" d="m21 21-4.3-4.3" />
                    </svg>
                    <input
                        type="text"
                        :placeholder="`Search ${label}...`"
                        class="form-input w-full pl-9 pr-9 text-sm"
                        :value="modelValue[f.key] || ''"
                        @input="update(f.key, $event.target.value)"
                    />
                </div>
                <select
                    v-else
                    class="form-input w-full"
                    :value="modelValue[f.key] || ''"
                    @change="update(f.key, $event.target.value)"
                >
                    <option value="">{{ f.label }} — All</option>
                    <option v-for="opt in f.options" :key="opt" :value="opt">{{ opt }}</option>
                </select>
            </template>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="$emit('reset')"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5" />
                </svg>
                Reset
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    filters: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    label: { type: String, default: 'records' },
});
defineEmits(['reset']);

// Every page passes a `reactive({})` as modelValue and binds it via `v-model`. Emitting a
// brand-new plain object here (the old `update:modelValue` approach) broke reactivity on
// the FIRST filter/search change: the parent's compiled v-model handler reassigns its local
// variable to that new plain object, which is never wrapped in reactive() — so every computed
// that already depends on the original reactive proxy silently stops updating, forever, for
// that page's lifetime. Mutating the existing reactive object's property in place instead
// keeps the same proxy instance alive, so every dependent computed keeps tracking correctly.
function update(key, value) {
    props.modelValue[key] = value;
}
</script>
