import { computed } from 'vue';
import { erpStore } from '../store';

/**
 * Permission helpers backed by erpStore.permissions (loaded from /settings/permissions/me).
 * Keys match PermissionCatalog: module.page.action
 */
export function usePermissions() {
    const permissions = computed(() => erpStore.permissions || []);
    const isAdmin = computed(() => erpStore.user?.role === 'admin' || permissions.value.includes('*'));

    function can(permission) {
        if (!permission) return true;
        if (isAdmin.value) return true;
        const perms = permissions.value;
        if (perms.includes('*') || perms.includes(permission)) return true;

        // Legacy UDISE+ keys (people.udise-plus.*) still grant UDISE+ S03 (people.udiseplus-s03.*)
        const legacyUdise = permission
            .replace(/^people\.udiseplus-s03\./, 'people.udise-plus.')
            .replace(/^people\.udiseplus\./, 'people.udise-plus.');
        if (legacyUdise !== permission && perms.includes(legacyUdise)) return true;

        const parts = permission.split('.');
        if (parts.length >= 2) {
            const moduleKey = parts[0];
            if (perms.includes(`${moduleKey}.manage`)) return true;
            if (moduleKey === 'fee-management' && perms.includes('fee.manage')) return true;
            if (moduleKey === 'finance-and-payroll' && perms.includes('finance.manage')) return true;
            if (moduleKey === 'transport-management' && perms.includes('transport.manage')) return true;
            if (moduleKey === 'exam-management' && perms.includes('exam.manage')) return true;
            if (moduleKey === 'import-and-export' && perms.includes('import.manage')) return true;
            if (moduleKey === 'reports' && parts[2] === 'view' && perms.includes('reports.view')) return true;
        }
        return false;
    }

    function canViewPage(moduleKey, pageKey) {
        return can(`${moduleKey}.${pageKey}.view`) || can(`${moduleKey}.${pageKey}.edit`);
    }

    return { permissions, isAdmin, can, canViewPage };
}
