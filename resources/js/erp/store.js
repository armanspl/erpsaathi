import { reactive, watch } from 'vue';
import { applyTheme, isValidHex, loadStoredTheme, resetTheme } from './utils/theme';
import { DEFAULT_UI_TEMPLATE, getUiTemplate, isValidUiTemplate } from './utils/templates';
import client from './api/client';

const savedDark = localStorage.getItem('erp_dark_mode');

// Account-saved prefs follow the user; localStorage is the per-browser fallback.
const accountThemeColor = window.__ERP_USER__?.theme_color;
const accountUiTemplate = window.__ERP_USER__?.ui_template;
const storedUiTemplate = localStorage.getItem('erp_ui_template');
const initialUiTemplate = isValidUiTemplate(accountUiTemplate)
    ? accountUiTemplate
    : (isValidUiTemplate(storedUiTemplate) ? storedUiTemplate : DEFAULT_UI_TEMPLATE);

const initialTemplate = getUiTemplate(initialUiTemplate);
const initialThemeColor = (accountThemeColor && isValidHex(accountThemeColor))
    ? accountThemeColor
    : (initialTemplate.primary || loadStoredTheme());

applyTheme(initialThemeColor);

/** Sentinel label for the header session picker — show data across every academic session. */
export const ALL_SESSIONS = 'All Sessions';

const state = reactive({
    user: window.__ERP_USER__ || { name: 'Guest', email: '', role: 'staff' },
    logoutUrl: window.__ERP_LOGOUT_URL__ || '/erp/logout',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

    // Prefer an explicit dark-mode choice; otherwise follow the active UI template.
    darkMode: savedDark !== null ? savedDark === '1' : !!initialTemplate.dark,
    sidebarCollapsed: localStorage.getItem('erp_sidebar_collapsed') === '1',
    sidebarMobileOpen: false,

    /** Option labels for the header picker, always starting with {@link ALL_SESSIONS}. */
    sessions: [ALL_SESSIONS],
    /** Full academic-session records from the API (id, name, is_current, ...). */
    sessionRecords: [],
    currentSession: localStorage.getItem('erp_current_session') || '',

    /** Option labels for the header picker. */
    branches: ['Main Campus'],
    /** Full branch records from the API (id, name, status, ...). */
    branchRecords: [],
    currentBranch: 'Main Campus',

    themeColor: initialThemeColor,
    uiTemplate: initialUiTemplate,

    /** Branding from Settings → School Settings (name, logo, code). */
    school: window.__ERP_SCHOOL__ || {
        school_name: 'Global School',
        school_code: '',
        logo_url: null,
        favicon_url: null,
        browser_title: '',
        watermark_text: '',
        compact_sidebar: false,
        is_demo: false,
        demo_hidden_import_export: [],
    },

    /** Public Try Demo school. */
    isDemoSchool: !!(window.__ERP_SCHOOL__?.is_demo),
    /** Menu keys hidden for demo via Super Admin. */
    demoHiddenImportExport: Array.isArray(window.__ERP_SCHOOL__?.demo_hidden_import_export)
        ? window.__ERP_SCHOOL__.demo_hidden_import_export
        : [],

    /** Effective permission keys for the logged-in user (* = full access). */
    permissions: (window.__ERP_USER__?.role === 'admin') ? ['*'] : [],
    permissionsLoaded: window.__ERP_USER__?.role === 'admin',
});

watch(
    () => state.darkMode,
    (val) => {
        document.documentElement.classList.toggle('dark', val);
        localStorage.setItem('erp_dark_mode', val ? '1' : '0');
    },
    { immediate: true },
);

watch(
    () => state.uiTemplate,
    (val) => {
        localStorage.setItem('erp_ui_template', val);
        document.documentElement.setAttribute('data-erp-template', val);
    },
    { immediate: true },
);

watch(
    () => state.sidebarCollapsed,
    (val) => localStorage.setItem('erp_sidebar_collapsed', val ? '1' : '0'),
);

watch(
    () => state.currentSession,
    (val) => {
        if (val) localStorage.setItem('erp_current_session', val);
    },
);

export function toggleDarkMode() {
    state.darkMode = !state.darkMode;
}

export function toggleSidebar() {
    state.sidebarCollapsed = !state.sidebarCollapsed;
}

export function setThemeColor(hex) {
    applyTheme(hex);
    state.themeColor = hex;
    client.put('/account/theme', { theme_color: hex }).catch(() => {});
}

export function resetThemeColor() {
    state.themeColor = resetTheme();
    client.put('/account/theme', { theme_color: null }).catch(() => {});
}

/** Apply a full-ERP visual template (shell vars, primary palette, preferred dark mode). */
export function setUiTemplate(id, { persist = true } = {}) {
    if (!isValidUiTemplate(id)) return;
    const tpl = getUiTemplate(id);
    state.uiTemplate = tpl.id;
    state.darkMode = !!tpl.dark;
    applyTheme(tpl.primary);
    state.themeColor = tpl.primary;
    // Clear sticky dark-mode override so template preference sticks until user toggles again.
    localStorage.setItem('erp_dark_mode', tpl.dark ? '1' : '0');

    if (persist) {
        client.put('/account/theme', {
            ui_template: tpl.id,
            theme_color: tpl.primary,
        }).catch(() => {});
    }
}

export function isAllSessions() {
    return !state.currentSession || state.currentSession === ALL_SESSIONS || state.currentSession === 'All';
}

export function selectedSessionRecord() {
    if (isAllSessions()) return null;
    return state.sessionRecords.find((s) => s.name === state.currentSession) ?? null;
}

export function selectedSessionId() {
    return selectedSessionRecord()?.id ?? null;
}

/** Prefer the header selection when it is a concrete session; otherwise the is_current row. */
export function defaultSessionId() {
    return selectedSessionId()
        ?? state.sessionRecords.find((s) => s.is_current)?.id
        ?? state.sessionRecords[0]?.id
        ?? null;
}

/**
 * Whether a record's session name matches the header selection.
 * Handles both "2026-2027" and short "2026-27" shapes.
 */
export function matchesSelectedSession(sessionName) {
    if (isAllSessions() || !sessionName) return true;
    const selected = state.currentSession;
    if (sessionName === selected) return true;

    const toShort = (name) => {
        const m = String(name).match(/^(\d{4})-(\d{4})$/);
        return m ? `${m[1]}-${m[2].slice(-2)}` : name;
    };
    const toLong = (name) => {
        const m = String(name).match(/^(\d{4})-(\d{2})$/);
        return m ? `${m[1]}-${m[1].slice(0, 2)}${m[2]}` : name;
    };

    return toShort(sessionName) === toShort(selected) || toLong(sessionName) === toLong(selected);
}

export async function loadSessions() {
    const { data } = await client.get('/settings/academic-sessions');
    state.sessionRecords = data;
    state.sessions = [ALL_SESSIONS, ...data.map((s) => s.name)];

    const saved = localStorage.getItem('erp_current_session');
    if (saved === ALL_SESSIONS || saved === 'All' || data.some((s) => s.name === saved)) {
        state.currentSession = saved === 'All' ? ALL_SESSIONS : saved;
    } else {
        const current = data.find((s) => s.is_current);
        state.currentSession = current?.name || ALL_SESSIONS;
    }

    return data;
}

/** Load branches for the header picker. */
export async function loadBranches() {
    const { data } = await client.get('/academics/branches');
    state.branchRecords = data;

    const active = data.filter((b) => b.status !== 'inactive');
    state.branches = (active.length ? active : data).map((b) => b.name);

    if (!state.branches.includes(state.currentBranch)) {
        state.currentBranch = state.branches[0] || state.currentBranch;
    }

    return data;
}

/** Load school branding for sidebar/header; also applies document title / favicon. */
export async function loadSchoolSettings() {
    const { data } = await client.get('/settings/school');
    applySchoolSettings(data);
    return data;
}

export function applySchoolSettings(data) {
    if (!data) return;
    state.school = {
        school_name: data.school_name || state.school.school_name,
        school_code: data.school_code || '',
        logo_url: data.logo_url || null,
        favicon_url: data.favicon_url || data.logo_url || null,
        browser_title: data.browser_title || '',
        watermark_text: data.watermark_text || '',
        compact_sidebar: !!data.compact_sidebar,
    };
    if (data.current_branch) state.currentBranch = data.current_branch;
    if (data.compact_sidebar && localStorage.getItem('erp_sidebar_collapsed') === null) {
        state.sidebarCollapsed = true;
    }

    const title = data.browser_title || data.school_name;
    if (title) document.title = title;

    const favicon = data.favicon_url || data.logo_url;
    if (favicon) {
        let link = document.querySelector("link[rel='icon']");
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.href = favicon;
    }
}

/** Initials for brand mark: school_code, else first letters of school name. */
export function schoolInitials() {
    const code = (state.school.school_code || '').trim();
    if (code) return code.slice(0, 3).toUpperCase();
    const name = (state.school.school_name || 'GS').trim();
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

/** Effective permissions for menu/button gating. */
export async function loadPermissions() {
    try {
        const { data } = await client.get('/settings/permissions/me');
        state.permissions = Array.isArray(data.permissions) ? data.permissions : [];
        if (typeof data.is_demo === 'boolean') {
            state.isDemoSchool = data.is_demo;
            state.school = { ...state.school, is_demo: data.is_demo };
        }
        if (Array.isArray(data.demo_hidden_import_export)) {
            state.demoHiddenImportExport = data.demo_hidden_import_export;
            state.school = { ...state.school, demo_hidden_import_export: data.demo_hidden_import_export };
        }
        state.permissionsLoaded = true;
    } catch {
        // Fail closed for non-admins; keep prior value if any.
        if (state.user?.role === 'admin') {
            state.permissions = ['*'];
        } else {
            state.permissions = [];
        }
        state.permissionsLoaded = true;
    }
    return state.permissions;
}

export function logout() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = state.logoutUrl;
    form.innerHTML = `<input type="hidden" name="_token" value="${state.csrfToken}">`;
    document.body.appendChild(form);
    form.submit();
}

export const erpStore = state;
