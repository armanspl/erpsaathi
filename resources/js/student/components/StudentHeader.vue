<template>
    <header class="erp-topbar sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b px-3 backdrop-blur sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="header-icon-btn lg:hidden" title="Open menu" @click="onToggleSidebar">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                <span class="erp-brand-name truncate">{{ studentStore.school.school_name || 'School' }}</span>
                <span class="erp-brand-tag">Student Portal</span>
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="header-icon-btn" title="Toggle dark / light mode" @click="toggleDarkMode">
                <svg v-if="!studentStore.darkMode" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>
                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg>
            </button>

            <div class="relative">
                <button type="button" class="user-pill flex items-center gap-2 rounded-full px-2.5 py-1.5" @click="menuOpen = !menuOpen">
                    <span class="user-avatar">{{ initials }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block truncate text-sm font-semibold">{{ studentStore.user.name }}</span>
                        <span class="block text-[11px] opacity-70">{{ studentStore.user.admission_no }}</span>
                    </span>
                </button>
                <transition name="fade">
                    <div v-if="menuOpen" class="user-menu absolute right-0 mt-2 w-52 rounded-xl border p-2 shadow-lg" @click.self="menuOpen = false">
                        <RouterLink to="/profile" class="user-menu-item" @click="menuOpen = false">My Profile</RouterLink>
                        <button type="button" class="user-menu-item user-menu-item--danger" @click="logout">Log out</button>
                    </div>
                </transition>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, ref } from 'vue';
import { studentStore, toggleDarkMode, logout } from '../store';

const emit = defineEmits(['toggle-mobile-sidebar']);
const menuOpen = ref(false);

const initials = computed(() => (studentStore.user.name || 'S').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join(''));

function onToggleSidebar() {
    // Desktop uses hover-expand rail; hamburger only opens the mobile drawer.
    emit('toggle-mobile-sidebar');
}
</script>

<style scoped>
.erp-topbar { background: color-mix(in srgb, var(--erp-surface) 92%, transparent); border-color: var(--erp-border); color: var(--erp-cream); }
.header-icon-btn { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; color: var(--erp-muted); transition: background .15s ease, color .15s ease; }
.header-icon-btn:hover { background: color-mix(in srgb, var(--erp-gold) 10%, transparent); color: var(--erp-cream); }
.erp-brand-name { font-weight: 700; font-size: 14px; color: var(--erp-cream); }
.erp-brand-tag { font-size: 11px; color: var(--erp-muted); }
.user-pill { color: var(--erp-cream); transition: background .15s ease; }
.user-pill:hover { background: color-mix(in srgb, var(--erp-gold) 10%, transparent); }
.user-avatar {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--erp-brand-from), var(--erp-brand-to));
    color: var(--erp-brand-ink); font-weight: 700; font-size: 12px;
}
.user-menu { background: var(--erp-surface-solid); border-color: var(--erp-border); }
.user-menu-item { display: block; width: 100%; text-align: left; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: var(--erp-cream); }
.user-menu-item:hover { background: color-mix(in srgb, var(--erp-gold) 10%, transparent); }
.user-menu-item--danger { color: #f6c1c1; }
.fade-enter-active, .fade-leave-active { transition: opacity .12s ease, transform .12s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
