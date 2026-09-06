<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/45" @click="onCancel" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ message }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-outline" :disabled="busy" @click="onCancel">{{ cancelLabel }}</button>
                    <button type="button" class="btn-primary" :disabled="busy" @click="onConfirm">
                        {{ busy ? busyLabel : confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Please wait…' },
});

const emit = defineEmits(['confirm', 'cancel', 'update:open']);

function onCancel() {
    emit('update:open', false);
    emit('cancel');
}

function onConfirm() {
    emit('confirm');
}
</script>
