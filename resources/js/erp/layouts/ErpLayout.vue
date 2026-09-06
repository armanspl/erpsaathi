<template>
    <div
        class="erp-shell flex min-h-screen"
        :class="[isPrintPage && 'print-page-shell', erpStore.darkMode ? 'erp-shell-dark' : 'erp-shell-light']"
        :data-template="erpStore.uiTemplate"
    >
        <AppSidebar v-if="!isPrintPage" />

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <AppHeader v-if="!isPrintPage" @toggle-mobile-sidebar="erpStore.sidebarMobileOpen = !erpStore.sidebarMobileOpen" />

            <main class="relative flex-1" :class="isPrintPage ? 'p-0' : 'px-3 py-5 sm:px-5 lg:px-6'">
                <router-view v-slot="{ Component, route }">
                    <!-- Remount on session change so every module reloads with X-Academic-Session -->
                    <component :is="Component" :key="`${route.fullPath}::${erpStore.currentSession}`" />
                </router-view>
            </main>

            <AppFooter v-if="!isPrintPage" />
        </div>

        <ToastHost />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from '../components/header/AppHeader.vue';
import AppSidebar from '../components/sidebar/AppSidebar.vue';
import AppFooter from '../components/AppFooter.vue';
import ToastHost from '../components/common/ToastHost.vue';
import { erpStore } from '../store';

const route = useRoute();
const isPrintPage = computed(() => !!route.meta.printPage);
</script>

<style>
/* Shared shell typography */
.erp-shell {
    font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif;
    color: var(--erp-cream);
    background:
        radial-gradient(ellipse 80% 50% at 10% -10%, color-mix(in srgb, var(--erp-gold) 12%, transparent), transparent 55%),
        radial-gradient(ellipse 60% 40% at 100% 0%, color-mix(in srgb, var(--erp-gold) 8%, transparent), transparent 50%),
        var(--erp-bg);
}

/* ── Midnight Gold (default classy) ── */
.erp-shell[data-template='midnight-gold'].erp-shell-dark {
    --erp-bg: #07080c;
    --erp-surface: rgba(18, 19, 24, 0.92);
    --erp-surface-solid: #121318;
    --erp-border: rgba(198, 167, 94, 0.18);
    --erp-gold: #c6a75e;
    --erp-gold-soft: #e2c98a;
    --erp-cream: #f3efe6;
    --erp-muted: #9a958c;
    --erp-brand-from: #e2c98a;
    --erp-brand-to: #8a6d2f;
    --erp-brand-ink: #14120e;
}
.erp-shell[data-template='midnight-gold'].erp-shell-light {
    --erp-bg: #ebe6dc;
    --erp-surface: rgba(255, 252, 246, 0.94);
    --erp-surface-solid: #fffcf6;
    --erp-border: rgba(40, 36, 28, 0.1);
    --erp-gold: #8a6d2f;
    --erp-gold-soft: #6f5622;
    --erp-cream: #1a1814;
    --erp-muted: #6b655c;
    --erp-brand-from: #e2c98a;
    --erp-brand-to: #8a6d2f;
    --erp-brand-ink: #14120e;
}

/* ── Harbor Navy ── */
.erp-shell[data-template='harbor-navy'].erp-shell-dark {
    --erp-bg: #06101c;
    --erp-surface: rgba(13, 26, 43, 0.94);
    --erp-surface-solid: #0d1a2b;
    --erp-border: rgba(91, 141, 239, 0.22);
    --erp-gold: #5b8def;
    --erp-gold-soft: #9bb8f5;
    --erp-cream: #e8eef8;
    --erp-muted: #8a9bb0;
    --erp-brand-from: #9bb8f5;
    --erp-brand-to: #3a6fd6;
    --erp-brand-ink: #06101c;
}
.erp-shell[data-template='harbor-navy'].erp-shell-light {
    --erp-bg: #eef3f9;
    --erp-surface: rgba(255, 255, 255, 0.96);
    --erp-surface-solid: #ffffff;
    --erp-border: rgba(47, 74, 110, 0.14);
    --erp-gold: #2f5fad;
    --erp-gold-soft: #1e447f;
    --erp-cream: #102038;
    --erp-muted: #5f7188;
    --erp-brand-from: #5b8def;
    --erp-brand-to: #2f5fad;
    --erp-brand-ink: #ffffff;
}

/* ── Forest Ledger ── */
.erp-shell[data-template='forest-ledger'].erp-shell-dark {
    --erp-bg: #07110c;
    --erp-surface: rgba(15, 26, 20, 0.94);
    --erp-surface-solid: #0f1a14;
    --erp-border: rgba(111, 175, 142, 0.22);
    --erp-gold: #6faf8e;
    --erp-gold-soft: #a7d4bb;
    --erp-cream: #e9f2ec;
    --erp-muted: #8fa396;
    --erp-brand-from: #a7d4bb;
    --erp-brand-to: #3f7d5f;
    --erp-brand-ink: #07110c;
}
.erp-shell[data-template='forest-ledger'].erp-shell-light {
    --erp-bg: #eef3ef;
    --erp-surface: rgba(255, 255, 255, 0.96);
    --erp-surface-solid: #ffffff;
    --erp-border: rgba(40, 70, 52, 0.12);
    --erp-gold: #3f7d5f;
    --erp-gold-soft: #2c5c44;
    --erp-cream: #122018;
    --erp-muted: #5f7466;
    --erp-brand-from: #6faf8e;
    --erp-brand-to: #3f7d5f;
    --erp-brand-ink: #ffffff;
}

/* ── Ivory Studio ── */
.erp-shell[data-template='ivory-studio'].erp-shell-dark {
    --erp-bg: #12161d;
    --erp-surface: rgba(22, 28, 38, 0.94);
    --erp-surface-solid: #161c26;
    --erp-border: rgba(140, 164, 196, 0.2);
    --erp-gold: #8ca4c4;
    --erp-gold-soft: #c2d0e2;
    --erp-cream: #edf1f6;
    --erp-muted: #93a0b2;
    --erp-brand-from: #c2d0e2;
    --erp-brand-to: #2f4a6e;
    --erp-brand-ink: #12161d;
}
.erp-shell[data-template='ivory-studio'].erp-shell-light {
    --erp-bg: #f2f4f7;
    --erp-surface: rgba(255, 255, 255, 0.97);
    --erp-surface-solid: #ffffff;
    --erp-border: rgba(21, 32, 51, 0.1);
    --erp-gold: #2f4a6e;
    --erp-gold-soft: #1c314d;
    --erp-cream: #152033;
    --erp-muted: #6b7585;
    --erp-brand-from: #4a6a92;
    --erp-brand-to: #2f4a6e;
    --erp-brand-ink: #ffffff;
}

/* ── Terracotta Sand ── */
.erp-shell[data-template='terracotta-sand'].erp-shell-dark {
    --erp-bg: #1a0f0a;
    --erp-surface: rgba(32, 20, 14, 0.94);
    --erp-surface-solid: #20140e;
    --erp-border: rgba(224, 138, 86, 0.2);
    --erp-gold: #e08a56;
    --erp-gold-soft: #f0ad83;
    --erp-cream: #f5ece4;
    --erp-muted: #a89584;
    --erp-brand-from: #e08a56;
    --erp-brand-to: #954a20;
    --erp-brand-ink: #1a0f0a;
}
.erp-shell[data-template='terracotta-sand'].erp-shell-light {
    --erp-bg: #faf6f0;
    --erp-surface: rgba(255, 255, 255, 0.97);
    --erp-surface-solid: #ffffff;
    --erp-border: rgba(43, 35, 32, 0.1);
    --erp-gold: #c1622d;
    --erp-gold-soft: #954a20;
    --erp-cream: #2b2320;
    --erp-muted: #8a7d6e;
    --erp-brand-from: #e08a56;
    --erp-brand-to: #954a20;
    --erp-brand-ink: #ffffff;
}

/* ── Velvet Plum ── */
.erp-shell[data-template='velvet-plum'].erp-shell-dark {
    --erp-bg: #120a14;
    --erp-surface: rgba(29, 18, 32, 0.94);
    --erp-surface-solid: #1d1220;
    --erp-border: rgba(201, 123, 152, 0.22);
    --erp-gold: #c97b98;
    --erp-gold-soft: #e3a8c0;
    --erp-cream: #f3e9ef;
    --erp-muted: #9c8a96;
    --erp-brand-from: #e3a8c0;
    --erp-brand-to: #8a4c68;
    --erp-brand-ink: #120a14;
}
.erp-shell[data-template='velvet-plum'].erp-shell-light {
    --erp-bg: #f7eef2;
    --erp-surface: rgba(255, 255, 255, 0.96);
    --erp-surface-solid: #ffffff;
    --erp-border: rgba(50, 20, 34, 0.12);
    --erp-gold: #8a4c68;
    --erp-gold-soft: #6e3a52;
    --erp-cream: #2a121d;
    --erp-muted: #7d6672;
    --erp-brand-from: #c97b98;
    --erp-brand-to: #8a4c68;
    --erp-brand-ink: #ffffff;
}

/* Fallbacks if data-template missing */
.erp-shell-dark:not([data-template]),
.erp-shell[data-template=''].erp-shell-dark {
    --erp-bg: #07080c;
    --erp-surface: rgba(18, 19, 24, 0.92);
    --erp-surface-solid: #121318;
    --erp-border: rgba(198, 167, 94, 0.18);
    --erp-gold: #c6a75e;
    --erp-gold-soft: #e2c98a;
    --erp-cream: #f3efe6;
    --erp-muted: #9a958c;
    --erp-brand-from: #e2c98a;
    --erp-brand-to: #8a6d2f;
    --erp-brand-ink: #14120e;
}
</style>
