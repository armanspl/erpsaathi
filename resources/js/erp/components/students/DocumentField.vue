<template>
    <div>
        <label class="form-label">{{ doc.label }}</label>

        <!-- Just picked, not saved yet -->
        <div v-if="fileName" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <span class="truncate text-slate-600 dark:text-slate-300">📎 {{ fileName }}</span>
            <button type="button" class="shrink-0 rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Remove" @click="$emit('clear', doc.key)">×</button>
        </div>

        <!-- Already uploaded (editing an existing record) -->
        <div v-else-if="editing && editing.documents?.[doc.column]" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <button type="button" class="truncate font-medium text-primary-600 hover:underline dark:text-primary-400" @click="$emit('download', doc)">📄 Uploaded — Download</button>
            <button type="button" class="shrink-0 rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Delete" :disabled="deleting" @click="$emit('remove', doc)">{{ deleting ? '...' : '×' }}</button>
        </div>

        <!-- Nothing chosen or uploaded yet -->
        <input v-else type="file" accept=".jpg,.jpeg,.png,.pdf" class="form-input" @change="onChange" />
    </div>
</template>

<script setup>
const props = defineProps({
    doc: { type: Object, required: true },
    editing: { type: Object, default: null },
    fileName: { type: String, default: '' },
    deleting: { type: Boolean, default: false },
});
const emit = defineEmits(['pick', 'clear', 'download', 'remove']);

function onChange(event) {
    // Parent owns the actual File storage, kept out of reactive() (see Registration.vue) —
    // this just hands the raw File up, it's never held in this component's own state.
    emit('pick', props.doc.key, event.target.files[0] || null);
}
</script>
