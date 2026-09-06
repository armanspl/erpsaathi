<template>
    <div class="dash-panel">
        <div class="dash-panel__head">
            <h3 class="dash-panel__title">{{ title }}</h3>
            <RouterLink v-if="to" :to="to" class="dash-panel__link">View all</RouterLink>
        </div>
        <slot />
    </div>
</template>

<script setup>
defineProps({ title: { type: String, required: true }, to: { type: String, default: '' } });
</script>

<style scoped>
.dash-panel {
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    border-radius: 14px;
    padding: 1.15rem 1.2rem;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
    backdrop-filter: blur(10px);
}
.dash-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--erp-border, rgba(198, 167, 94, 0.14));
}
.dash-panel__title {
    margin: 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--erp-cream, #f3efe6);
}
.dash-panel__link {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--erp-gold, #c6a75e);
    text-decoration: none;
}
.dash-panel__link:hover {
    color: var(--erp-gold-soft, #e2c98a);
    text-decoration: underline;
}
</style>
