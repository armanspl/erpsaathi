<template>
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="name === 'plus' ? 2.5 : 2" v-html="paths[name] || paths.view" />
</template>

<script setup>
defineProps({ name: { type: String, required: true } });

const paths = {
    upload: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4L8 8M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
    download: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0 4-4m-4 4-4-4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
    print: '<path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v8H6Z"/>',
    idcard: '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="11.5" r="1.5"/><path stroke-linecap="round" d="M13 10h6M13 14h4M5 17c.5-1.5 2-2 3.5-2s3 .5 3.5 2"/>',
    certificate: '<circle cx="12" cy="8" r="6"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 14-1 7 4-2 4 2-1-7"/>',
    transfer: '<path stroke-linecap="round" stroke-linejoin="round" d="m17 3 4 4-4 4M3 7h18M7 21l-4-4 4-4m-4 4h18"/>',
    promote: '<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5"/>',
    plus: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>',
    view: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
    file: '<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>',
};
</script>
