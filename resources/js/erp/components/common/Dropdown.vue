<template>
    <div ref="root" class="relative">
        <div @click="open = !open">
            <slot name="trigger" :open="open" />
        </div>
        <transition name="dropdown">
            <div v-if="open" class="absolute z-40 mt-2" :class="align === 'right' ? 'right-0' : 'left-0'" @click="closeOnClick && (open = false)">
                <slot name="panel" :close="() => (open = false)" />
            </div>
        </transition>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    align: { type: String, default: 'left' },
    closeOnClick: { type: Boolean, default: false },
});

const root = ref(null);
const open = ref(false);

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));

defineExpose({ open });
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
