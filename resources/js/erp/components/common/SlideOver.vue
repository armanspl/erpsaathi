<template>
    <transition name="drawer">
        <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="$emit('close')" />
            <div class="relative flex h-full w-full flex-col bg-white shadow-2xl dark:bg-slate-900" :class="wide ? 'max-w-2xl' : 'max-w-md'">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="$emit('close')">×</button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <slot />
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});
defineEmits(['close']);
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
