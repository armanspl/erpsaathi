<template>
    <div class="tpl">
        <header class="tpl-hero">
            <div>
                <p class="tpl-kicker">Account</p>
                <h1 class="tpl-title">Choose Template</h1>
                <Breadcrumb :items="['Dashboard', 'Account', 'Choose Template']" class="mt-1" />
                <p class="tpl-sub">Pick a visual system for the whole ERP — sidebar, header, dashboard, and accents update together.</p>
            </div>
        </header>

        <div class="tpl-grid">
            <button
                v-for="t in UI_TEMPLATES"
                :key="t.id"
                type="button"
                class="tpl-card"
                :class="{ 'is-active': erpStore.uiTemplate === t.id }"
                @click="choose(t.id)"
            >
                <div class="tpl-preview" :style="previewStyle(t)">
                    <div class="tpl-preview__sidebar" />
                    <div class="tpl-preview__main">
                        <div class="tpl-preview__bar" />
                        <div class="tpl-preview__stats">
                            <span /><span /><span />
                        </div>
                        <div class="tpl-preview__panel" />
                    </div>
                    <span class="tpl-preview__dot" :style="{ background: t.preview.accent }" />
                </div>
                <div class="tpl-card__body">
                    <div class="tpl-card__row">
                        <h2>{{ t.name }}</h2>
                        <span v-if="erpStore.uiTemplate === t.id" class="tpl-badge">Active</span>
                    </div>
                    <p>{{ t.tagline }}</p>
                    <div class="tpl-swatches">
                        <span :style="{ background: t.preview.bg }" />
                        <span :style="{ background: t.preview.surface }" />
                        <span :style="{ background: t.preview.accent }" />
                        <span :style="{ background: t.preview.text }" />
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import { erpStore, setUiTemplate } from '../../store';
import { UI_TEMPLATES } from '../../utils/templates';
import { pushToast } from '../../utils/toast';

function previewStyle(t) {
    return {
        background: t.preview.bg,
        color: t.preview.text,
        '--p-surface': t.preview.surface,
        '--p-accent': t.preview.accent,
        '--p-muted': t.preview.muted,
    };
}

function choose(id) {
    if (erpStore.uiTemplate === id) return;
    setUiTemplate(id);
    const name = UI_TEMPLATES.find((t) => t.id === id)?.name || 'Template';
    pushToast(`${name} applied across the ERP.`, 'success');
}
</script>

<style scoped>
.tpl {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif;
    color: var(--erp-cream, #f3efe6);
}
.tpl-kicker {
    margin: 0 0 0.2rem;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--erp-gold, #c6a75e);
}
.tpl-title {
    margin: 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(1.85rem, 3vw, 2.25rem);
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}
.tpl-sub {
    margin: 0.55rem 0 0;
    max-width: 36rem;
    font-size: 0.9rem;
    color: var(--erp-muted, #9a958c);
}
.tpl-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 768px) {
    .tpl-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.tpl-card {
    text-align: left;
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface, rgba(18, 19, 24, 0.92));
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    color: inherit;
    padding: 0;
}
.tpl-card:hover {
    border-color: var(--erp-gold, #c6a75e);
    transform: translateY(-2px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.22);
}
.tpl-card.is-active {
    border-color: var(--erp-gold, #c6a75e);
    box-shadow: 0 0 0 1px var(--erp-gold, #c6a75e), 0 16px 36px rgba(0, 0, 0, 0.22);
}
.tpl-preview {
    position: relative;
    display: grid;
    grid-template-columns: 52px 1fr;
    gap: 8px;
    height: 128px;
    padding: 12px;
}
.tpl-preview__sidebar {
    border-radius: 8px;
    background: var(--p-surface);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--p-accent) 35%, transparent);
}
.tpl-preview__main {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tpl-preview__bar {
    height: 18px;
    border-radius: 6px;
    background: var(--p-surface);
}
.tpl-preview__stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.tpl-preview__stats span {
    height: 28px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--p-accent) 35%, var(--p-surface));
}
.tpl-preview__panel {
    flex: 1;
    border-radius: 8px;
    background: var(--p-surface);
}
.tpl-preview__dot {
    position: absolute;
    right: 18px;
    bottom: 18px;
    width: 14px;
    height: 14px;
    border-radius: 999px;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-accent) 25%, transparent);
}
.tpl-card__body {
    padding: 1rem 1.1rem 1.15rem;
    border-top: 1px solid var(--erp-border, rgba(198, 167, 94, 0.14));
}
.tpl-card__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.tpl-card__body h2 {
    margin: 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.35rem;
    font-weight: 600;
}
.tpl-card__body p {
    margin: 0.35rem 0 0;
    font-size: 0.82rem;
    color: var(--erp-muted, #9a958c);
}
.tpl-badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--erp-gold, #c6a75e) 22%, transparent);
    color: var(--erp-gold-soft, #e2c98a);
}
.tpl-swatches {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.85rem;
}
.tpl-swatches span {
    width: 1.1rem;
    height: 1.1rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}
</style>
