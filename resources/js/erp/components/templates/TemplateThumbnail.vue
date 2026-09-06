<template>
    <div class="overflow-hidden rounded border border-slate-200 bg-white dark:border-slate-700" :style="{ width: width + 'px', height: height + 'px' }">
        <div :style="page">
            <div v-for="el in visible" :key="el.id" :style="box(el)">
                <div v-if="el.type === 'text'" :style="text(el)">{{ content(el) }}</div>
                <img v-else-if="el.type === 'image' && assetSrc(el)" :src="assetSrc(el)" style="width: 100%; height: 100%; object-fit: cover" />
                <div v-else-if="el.type === 'image'" class="flex h-full w-full items-center justify-center text-[6px] text-slate-300">{{ el.field_key }}</div>
                <div v-else-if="el.type === 'qrcode'" class="flex h-full w-full items-center justify-center bg-slate-100 text-[6px] text-slate-400">QR</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { assetUrl, elementBoxStyle, pageStyle, substituteTokens, textStyle } from '../../utils/templateRender';

const props = defineProps({
    template: { type: Object, required: true },
    sample: { type: Object, default: () => ({}) },
    width: { type: Number, default: 220 },
});

const scale = computed(() => props.width / props.template.page_width_mm);
const height = computed(() => Math.round(props.template.page_height_mm * scale.value));
const page = computed(() => pageStyle(props.template, scale.value));
const visible = computed(() => [...(props.template.elements || [])].filter((e) => !e.hidden).sort((a, b) => a.z_index - b.z_index));

function box(el) {
    return { ...elementBoxStyle(el, scale.value), overflow: 'hidden' };
}
function text(el) {
    return textStyle(el, scale.value);
}
function content(el) {
    return (el.label_prefix || '') + substituteTokens(el.content, props.sample);
}
function assetSrc(el) {
    return el.image_path ? assetUrl(props.template.id, el.image_path) : null;
}
</script>
