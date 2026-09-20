import { reactive, watch } from 'vue';

const seededUser = window.__STUDENT__ || {};
const seededSchool = window.__STUDENT_SCHOOL__ || {};
const seededVisibility = window.__STUDENT_PORTAL_VISIBILITY__ || {};

function initialDarkMode() {
    try {
        const saved = localStorage.getItem('student_dark_mode');
        return saved === null ? true : saved === '1';
    } catch {
        return true;
    }
}

function readFlag(key) {
    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

export const studentStore = reactive({
    user: seededUser,
    school: seededSchool,
    visibility: seededVisibility,
    darkMode: initialDarkMode(),
    sidebarCollapsed: true,
    /** Desktop: keep sidebar expanded (pinned) instead of hover-only. */
    sidebarPinned: readFlag('student_sidebar_pinned'),
    sidebarMobileOpen: false,
    hasTransport: null, // resolved lazily once Transport.vue (or the sidebar) checks — null = unknown yet
});

watch(
    () => studentStore.sidebarPinned,
    (val) => {
        try {
            localStorage.setItem('student_sidebar_pinned', val ? '1' : '0');
        } catch {
            // ignore
        }
    },
);

export function toggleSidebar() {
    studentStore.sidebarCollapsed = !studentStore.sidebarCollapsed;
}

export function toggleSidebarPin() {
    studentStore.sidebarPinned = !studentStore.sidebarPinned;
}

export function setSidebarPinned(pinned) {
    studentStore.sidebarPinned = !!pinned;
}

export function toggleDarkMode() {
    studentStore.darkMode = !studentStore.darkMode;
    try {
        localStorage.setItem('student_dark_mode', studentStore.darkMode ? '1' : '0');
    } catch {
        // ignore (private browsing / storage disabled)
    }
}

export function isModuleVisible(key) {
    if (!key) return true;
    return studentStore.visibility[key] !== false;
}

export function schoolInitials() {
    const name = studentStore.school.school_name || 'School';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}

export async function logout() {
    try {
        await fetch(window.__STUDENT_LOGOUT_URL__ || '/student/logout', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
    } finally {
        window.location.href = '/student/login';
    }
}
