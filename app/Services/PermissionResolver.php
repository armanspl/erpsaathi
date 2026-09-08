<?php

namespace App\Services;

use App\Models\ErpDepartment;
use App\Models\ErpRole;
use App\Models\ErpUser;
use App\Models\Staff;

/**
 * Resolves the effective permission key list for an ERP user.
 *
 * Admin → full access (*)
 * Staff with a matching department → department permissions
 * Everyone else → role permissions (erp_roles.permissions)
 * Account + Dashboard view are always granted for any authenticated user.
 */
class PermissionResolver
{
    /** Always available once logged in. */
    public const ALWAYS = [
        'dashboard.dashboard.view',
        'account.profile.view',
        'account.profile.edit',
        'account.change-password.edit',
        'account.notifications.view',
        'account.choose-template.view',
        'account.choose-template.edit',
    ];

    /** @return list<string> */
    public function forUser(?ErpUser $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->role === 'admin') {
            return ['*'];
        }

        $fromDepartment = $this->departmentPermissions($user);
        if ($fromDepartment !== null) {
            return $this->normalize(array_merge(self::ALWAYS, $fromDepartment));
        }

        $role = ErpRole::where('slug', $user->role)->first();
        $perms = $role->permissions ?? [];

        return $this->normalize(array_merge(self::ALWAYS, $perms));
    }

    public function allows(?ErpUser $user, string $permission): bool
    {
        $perms = $this->forUser($user);
        if (in_array('*', $perms, true)) {
            return true;
        }
        if (in_array($permission, $perms, true)) {
            return true;
        }

        // Legacy UDISE+ keys still grant UDISE+ S03 after rename.
        $legacyUdise = preg_replace('/^people\.udiseplus-s03\./', 'people.udise-plus.', $permission);
        $legacyUdise = preg_replace('/^people\.udiseplus\./', 'people.udise-plus.', $legacyUdise);
        if ($legacyUdise !== $permission && in_array($legacyUdise, $perms, true)) {
            return true;
        }

        // Legacy module write gates (routes still use fee.manage etc.):
        // any non-view page action under that module satisfies the gate.
        $legacyWrite = [
            'admissions.manage' => 'admissions.',
            'attendance.manage' => 'attendance.',
            'fee.manage' => 'fee-management.',
            'finance.manage' => 'finance-and-payroll.',
            'transport.manage' => 'transport-management.',
            'exam.manage' => 'exam-management.',
            'documents.manage' => 'documents.',
            'import.manage' => 'import-and-export.',
            'reports.view' => 'reports.',
            'library.manage' => 'library.',
            'inventory.manage' => 'inventory.',
            'hostel.manage' => 'hostel.',
            'communication.manage' => 'communication.',
            'meetings.manage' => 'meetings.',
            'system.manage' => 'settings.database-backup.',
        ];
        if (isset($legacyWrite[$permission])) {
            $prefix = $legacyWrite[$permission];
            foreach ($perms as $held) {
                if (! is_string($held) || ! str_starts_with($held, $prefix)) {
                    continue;
                }
                if ($permission === 'reports.view') {
                    return true;
                }
                if (! str_ends_with($held, '.view')) {
                    return true;
                }
            }
        }

        // Page-level key (people.staff.create): legacy people.manage / fee.manage still grants it.
        $parts = explode('.', $permission);
        if (count($parts) >= 2) {
            $moduleKey = $parts[0];
            if (in_array($moduleKey.'.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'fee-management' && in_array('fee.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'finance-and-payroll' && in_array('finance.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'transport-management' && in_array('transport.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'exam-management' && in_array('exam.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'import-and-export' && in_array('import.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'reports' && in_array('reports.view', $perms, true) && ($parts[2] ?? '') === 'view') {
                return true;
            }
            if ($moduleKey === 'settings' && in_array('settings.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'documents' && in_array('documents.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'admissions' && in_array('admissions.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'attendance' && in_array('attendance.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'academics' && in_array('academics.manage', $perms, true)) {
                return true;
            }
            if ($moduleKey === 'people' && in_array('people.manage', $perms, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string>|null null = no department binding (fall back to role) */
    private function departmentPermissions(ErpUser $user): ?array
    {
        if (! $user->email) {
            return null;
        }

        $staff = Staff::where('email', $user->email)->first();
        if (! $staff || ! $staff->department) {
            // Staff role without department: deny module access (only ALWAYS keys).
            if ($user->role === 'staff') {
                return [];
            }

            return null;
        }

        $department = ErpDepartment::query()
            ->where('is_active', true)
            ->where(function ($q) use ($staff) {
                $q->where('name', $staff->department)
                    ->orWhere('slug', $staff->department);
            })
            ->first();

        if (! $department) {
            return $user->role === 'staff' ? [] : null;
        }

        return $department->permissions ?? [];
    }

    /** @param  list<string>  $perms
     *  @return list<string>
     */
    private function normalize(array $perms): array
    {
        return array_values(array_unique(array_filter($perms, fn ($p) => is_string($p) && $p !== '')));
    }
}
