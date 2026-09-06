<template>
    <div class="flex h-full w-full items-center justify-center">
        <div :style="outerStyle">
            <iframe :srcdoc="html" :style="frameStyle" scrolling="no" tabindex="-1" title="Design preview"></iframe>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const PX_PER_MM = 96 / 25.4;

const props = defineProps({
    html: { type: String, required: true },
    widthMm: { type: Number, required: true },
    heightMm: { type: Number, required: true },
    boxHeight: { type: Number, default: 140 },
});

const scale = computed(() => props.boxHeight / (props.heightMm * PX_PER_MM));
const naturalWidth = computed(() => props.widthMm * PX_PER_MM);
const naturalHeight = computed(() => props.heightMm * PX_PER_MM);

const outerStyle = computed(() => ({
    width: `${naturalWidth.value * scale.value}px`,
    height: `${props.boxHeight}px`,
    overflow: 'hidden',
    position: 'relative',
    boxShadow: '0 0 0 1px rgba(0,0,0,0.06)',
}));

const frameStyle = computed(() => ({
    width: `${naturalWidth.value}px`,
    height: `${naturalHeight.value}px`,
    border: 'none',
    transform: `scale(${scale.value})`,
    transformOrigin: 'top left',
    pointerEvents: 'none',
    background: '#ffffff',
}));
</script>
