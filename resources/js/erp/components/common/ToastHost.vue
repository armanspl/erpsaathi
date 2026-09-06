<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
        <transition-group name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white px-4 py-3 shadow-lg dark:bg-slate-800"
                :class="borderClass(t.type)"
            >
                <span class="mt-0.5 text-base">{{ icon(t.type) }}</span>
                <p class="flex-1 text-sm text-slate-700 dark:text-slate-200">{{ t.message }}</p>
                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="dismissToast(t.id)">×</button>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import { toasts, dismissToast } from '../../utils/toast';

function icon(type) {
    return { success: '✅', error: '⚠️', info: 'ℹ️' }[type] || 'ℹ️';
}
function borderClass(type) {
    return { success: 'border-emerald-200 dark:border-emerald-800', error: 'border-rose-200 dark:border-rose-800', info: 'border-slate-200 dark:border-slate-700' }[type] || 'border-slate-200 dark:border-slate-700';
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
