<template>
    <section class="dash-panel">
        <div class="dash-panel__head">
            <h3 class="dash-panel__title">
                <span v-if="icon" class="dash-panel__icon"><AppIcon :name="icon" class="h-4 w-4" /></span>
                {{ title }}
            </h3>
            <slot name="actions">
                <RouterLink v-if="to" :to="to" class="dash-panel__link">
                    View all
                    <AppIcon name="arrow-right" class="h-3.5 w-3.5" />
                </RouterLink>
            </slot>
        </div>
        <slot />
    </section>
</template>

<script setup>
import AppIcon from '../common/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    to: { type: String, default: '' },
    icon: { type: String, default: '' },
});
</script>

<style scoped>
.dash-panel {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    border-radius: var(--erp-radius-card, 14px);
    padding: 1.15rem 1.2rem;
    box-shadow: var(--erp-shadow, 0 12px 40px rgba(0, 0, 0, 0.22));
    backdrop-filter: var(--erp-backdrop, blur(10px));
}
.dash-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}
.dash-panel__title {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0;
    font-family: var(--erp-font-display, 'Cormorant Garamond', Georgia, serif);
    font-size: var(--erp-card-title-size, 1.2rem);
    font-weight: 600;
    letter-spacing: 0.005em;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.dash-panel__icon {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--erp-stat-icon-bg, rgba(198, 167, 94, 0.12));
    color: var(--erp-stat-icon, var(--erp-gold, #c6a75e));
}
.dash-panel__link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    padding: 0.3rem 0.55rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--erp-gold, #c6a75e);
    text-decoration: none;
    transition: background 0.15s ease, color 0.15s ease;
}
.dash-panel__link:hover {
    background: var(--erp-tint-strong, rgba(198, 167, 94, 0.1));
    color: var(--erp-gold-soft, #e2c98a);
}
</style>
