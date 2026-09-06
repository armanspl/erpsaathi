<?php

namespace App\Services;

/**
 * Single source of truth for ERP page-level permissions.
 *
 * Built from an audit of every sidebar module/page and the actions that
 * actually exist on those screens (buttons / API calls). Do not invent
 * actions that the page does not expose.
 *
 * Key format: {module}.{page}.{action}
 * Example: people.staff.create, fee-management.fee-receipt.download
 *
 * Extending later: add a page entry (or action) here — UI + middleware
 * pick it up automatically via PermissionCatalog::allKeys() / tree().
 */
class PermissionCatalog
{
    /**
     * @return list<array{
     *   module: string,
     *   module_label: string,
     *   page: string,
     *   page_label: string,
     *   path: string|null,
     *   actions: list<array{key: string, label: string}>
     * }>
     */
    public static function tree(): array
    {
        $out = [];
        foreach (self::definition() as $moduleKey => $module) {
            foreach ($module['pages'] as $pageKey => $page) {
                $actions = [];
                foreach ($page['actions'] as $actionKey => $actionLabel) {
                    $actions[] = [
                        'key' => "{$moduleKey}.{$pageKey}.{$actionKey}",
                        'action' => $actionKey,
                        'label' => $actionLabel,
                    ];
                }
                $out[] = [
                    'module' => $moduleKey,
                    'module_label' => $module['label'],
                    'page' => $pageKey,
                    'page_label' => $page['label'],
                    'path' => $page['path'] ?? null,
                    'actions' => $actions,
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::tree() as $page) {
            foreach ($page['actions'] as $action) {
                $keys[] = $action['key'];
            }
        }

        return $keys;
    }

    /** Flat list for role UI (includes legacy module.manage wildcards). */
    public static function legacyModuleKeys(): array
    {
        return [
            'academics.manage',
            'people.manage',
            'admissions.manage',
            'attendance.manage',
            'fee.manage',
            'finance.manage',
            'transport.manage',
            'exam.manage',
            'documents.manage',
            'import.manage',
            'reports.view',
            'settings.manage',
            'system.manage',
        ];
    }

    /**
     * Map a frontend route path to the page's "view" permission key.
     * Prefer viewKeyForMenu() for Import & Export (shared path + query).
     */
    public static function viewKeyForPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $normalized = '/'.ltrim($path, '/');
        if ($normalized === '/') {
            return 'dashboard.dashboard.view';
        }
        foreach (self::tree() as $page) {
            if (($page['path'] ?? null) === $normalized) {
                return $page['module'].'.'.$page['page'].'.view';
            }
        }

        return null;
    }

    /** Menu group key + child key → view permission (matches sidebar slugify). */
    public static function viewKeyForMenu(string $moduleKey, string $pageKey): string
    {
        if ($moduleKey === 'dashboard') {
            return 'dashboard.dashboard.view';
        }

        return "{$moduleKey}.{$pageKey}.view";
    }

    /**
     * @return array<string, array{label: string, pages: array<string, array{label: string, path?: string|null, actions: array<string, string>}>}>
     */
    public static function definition(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'pages' => [
                    'dashboard' => [
                        'label' => 'Dashboard',
                        'path' => '/',
                        'actions' => ['view' => 'View'],
                    ],
                ],
            ],
            'import-and-export' => [
                'label' => 'Import & Export',
                'pages' => [
                    'global-workbook-import' => [
                        'label' => 'All Workbook Import',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'import' => 'Import', 'upload' => 'Upload'],
                    ],
                    'global-workbook-export' => [
                        'label' => 'All Workbook Export',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'student-import' => [
                        'label' => 'Student Import',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'import' => 'Import', 'upload' => 'Upload'],
                    ],
                    'student-pen-import' => [
                        'label' => 'Student PEN Import',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'import' => 'Import', 'upload' => 'Upload'],
                    ],
                    'student-export' => [
                        'label' => 'Student Export',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'attendance-import' => [
                        'label' => 'Attendance Import',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'import' => 'Import', 'upload' => 'Upload'],
                    ],
                    'attendance-export' => [
                        'label' => 'Attendance Export',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'exam-marks-import' => [
                        'label' => 'Exam Marks Import',
                        'path' => '/import-export',
                        'actions' => ['view' => 'View', 'import' => 'Import', 'upload' => 'Upload'],
                    ],
                ],
            ],
            'academics' => [
                'label' => 'Academics',
                'pages' => [
                    'branches' => [
                        'label' => 'Branches',
                        'path' => '/academics/branches',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'academic-sessions' => [
                        'label' => 'Academic Sessions',
                        'path' => '/academics/academic-sessions',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'classes-and-sections' => [
                        'label' => 'Classes & Sections',
                        'path' => '/academics/classes-and-sections',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'assign' => 'Assign Subjects',
                        ],
                    ],
                    'subjects' => [
                        'label' => 'Subjects',
                        'path' => '/academics/subjects',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'homework' => [
                        'label' => 'Homework',
                        'path' => '/academics/homework',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'delete' => 'Delete',
                            'upload' => 'Upload Attachment',
                            'download' => 'Download Attachment',
                        ],
                    ],
                ],
            ],
            'admissions' => [
                'label' => 'Admissions',
                'pages' => [
                    'enquiry' => [
                        'label' => 'Enquiry',
                        'path' => '/admissions/enquiry',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'registration' => [
                        'label' => 'Registration',
                        'path' => '/admissions/registration',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'upload' => 'Upload Documents',
                            'delete' => 'Delete Documents',
                            'manage' => 'Promote to Admitted',
                        ],
                    ],
                    'admission' => [
                        'label' => 'Admission',
                        'path' => '/admissions/admission',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'upload' => 'Upload Documents',
                            'delete' => 'Delete Documents',
                        ],
                    ],
                    'admission-settings' => [
                        'label' => 'Admission Settings',
                        'path' => '/admissions/admission-settings',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                ],
            ],
            'people' => [
                'label' => 'People',
                'pages' => [
                    'users' => [
                        'label' => 'Users',
                        'path' => '/people/users',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'students' => [
                        'label' => 'Students',
                        'path' => '/people/students',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'export' => 'Export',
                            'upload' => 'Upload Documents',
                            'download' => 'Download Documents',
                            'assign' => 'Assign Transport',
                        ],
                    ],
                    'parents' => [
                        'label' => 'Parents',
                        'path' => '/people/parents',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'teachers' => [
                        'label' => 'Teachers',
                        'path' => '/people/teachers',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'export' => 'Export',
                            'assign' => 'Assign Classes',
                            'upload' => 'Upload Signature',
                        ],
                    ],
                    'staff' => [
                        'label' => 'Staff',
                        'path' => '/people/staff',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'drivers' => [
                        'label' => 'Drivers',
                        'path' => '/people/drivers',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'visitor-records' => [
                        'label' => 'Visitor Records',
                        'path' => '/people/visitor-records',
                        'actions' => ['view' => 'View', 'create' => 'Check In', 'edit' => 'Check Out', 'delete' => 'Delete'],
                    ],
                    'udise-plus' => [
                        'label' => 'UDISE+',
                        'path' => '/people/udise-plus',
                        'actions' => ['view' => 'View', 'download' => 'Download', 'export' => 'Download ZIP'],
                    ],
                    'employee-master-import' => [
                        'label' => 'Employee Master Import',
                        'path' => '/people/employee-master-import',
                        'actions' => ['view' => 'View', 'upload' => 'Upload', 'import' => 'Import'],
                    ],
                ],
            ],
            'attendance' => [
                'label' => 'Attendance',
                'pages' => [
                    'student-attendance' => [
                        'label' => 'Student Attendance',
                        'path' => '/attendance/student-attendance',
                        'actions' => ['view' => 'View', 'edit' => 'Mark / Save', 'export' => 'Export'],
                    ],
                    'staff-attendance' => [
                        'label' => 'Staff Attendance',
                        'path' => '/attendance/staff-attendance',
                        'actions' => ['view' => 'View', 'create' => 'Check In', 'upload' => 'Upload Photo', 'export' => 'Export'],
                    ],
                    'driver-attendance' => [
                        'label' => 'Driver Attendance',
                        'path' => '/attendance/driver-attendance',
                        'actions' => ['view' => 'View', 'create' => 'Check In', 'edit' => 'Mark Route', 'export' => 'Export'],
                    ],
                    'leave-management' => [
                        'label' => 'Leave Management',
                        'path' => '/attendance/leave-management',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Apply Leave',
                            'approve' => 'Approve',
                            'reject' => 'Reject',
                            'delete' => 'Delete',
                            'export' => 'Export',
                            'upload' => 'Upload Attachment',
                        ],
                    ],
                    'joining-after-leave' => [
                        'label' => 'Joining After Leave',
                        'path' => '/attendance/joining-after-leave',
                        'actions' => ['view' => 'View', 'edit' => 'Confirm Joining'],
                    ],
                ],
            ],
            'fee-management' => [
                'label' => 'Fee Management',
                'pages' => [
                    'fee-structure' => [
                        'label' => 'Fee Structure',
                        'path' => '/fee-management/fee-structure',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'duplicate' => 'Duplicate'],
                    ],
                    'fee-due' => [
                        'label' => 'Fee Due',
                        'path' => '/fee-management/fee-due',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'download' => 'Download Receipt',
                            'export' => 'Export',
                        ],
                    ],
                    'fee-history' => [
                        'label' => 'Fee History',
                        'path' => '/fee-management/fee-history',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'pay-fee' => [
                        'label' => 'Pay Fee',
                        'path' => '/fee-management/pay-fee',
                        'actions' => ['view' => 'View', 'create' => 'Collect Payment', 'download' => 'Download Receipt'],
                    ],
                    'fee-receipt' => [
                        'label' => 'Fee Receipt',
                        'path' => '/fee-management/fee-receipt',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Submit Fee',
                            'edit' => 'Edit',
                            'print' => 'Print',
                            'download' => 'Download',
                            'export' => 'Export',
                            'rollback' => 'Rollback',
                        ],
                    ],
                    'fee-settings' => [
                        'label' => 'Fee Settings',
                        'path' => '/fee-management/fee-settings',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'tally-accounting' => [
                        'label' => 'Tally Accounting',
                        'path' => '/fee-management/tally-accounting',
                        'actions' => ['view' => 'View', 'edit' => 'Save Mapping', 'export' => 'Export'],
                    ],
                ],
            ],
            'finance-and-payroll' => [
                'label' => 'Finance & Payroll',
                'pages' => [
                    'office-expenses' => [
                        'label' => 'Office Expenses',
                        'path' => '/finance-and-payroll/office-expenses',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'manage' => 'Manage Categories'],
                    ],
                    'salary-slips' => [
                        'label' => 'Salary Slips',
                        'path' => '/finance-and-payroll/salary-slips',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'download' => 'Download PDF',
                            'manage' => 'Mark Paid',
                        ],
                    ],
                    'salary-sheet' => [
                        'label' => 'Salary Sheet',
                        'path' => '/finance-and-payroll/salary-sheet',
                        'actions' => ['view' => 'View', 'upload' => 'Upload', 'import' => 'Import', 'export' => 'Export'],
                    ],
                    'book-store' => [
                        'label' => 'Book Store',
                        'path' => '/finance-and-payroll/book-store',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'book-expenses' => [
                        'label' => 'Book Expenses',
                        'path' => '/finance-and-payroll/book-expenses',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'download' => 'Download'],
                    ],
                    'bank-accounts' => [
                        'label' => 'Bank Accounts',
                        'path' => '/finance-and-payroll/bank-accounts',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'bank-transactions' => [
                        'label' => 'Bank Transactions',
                        'path' => '/finance-and-payroll/bank-transactions',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                ],
            ],
            'transport-management' => [
                'label' => 'Transport Management',
                'pages' => [
                    'routes' => [
                        'label' => 'Routes',
                        'path' => '/transport-management/routes',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'vehicles' => [
                        'label' => 'Vehicles',
                        'path' => '/transport-management/vehicles',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                ],
            ],
            'exam-management' => [
                'label' => 'Exam Management',
                'pages' => [
                    'exams' => [
                        'label' => 'Exams',
                        'path' => '/exam-management/exams',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'print' => 'Print'],
                    ],
                    'terms' => [
                        'label' => 'Terms',
                        'path' => '/exam-management/terms',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'generate' => 'Setup Terms'],
                    ],
                    'exam-schedule' => [
                        'label' => 'Exam Schedule',
                        'path' => '/exam-management/exam-schedule',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'print' => 'Print',
                            'download' => 'Download PDF',
                            'import' => 'Import',
                        ],
                    ],
                    'seat-planning' => [
                        'label' => 'Seat Planning',
                        'path' => '/exam-management/seat-planning',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'export' => 'Export'],
                    ],
                    'marks-management' => [
                        'label' => 'Marks Management',
                        'path' => '/exam-management/marks-management',
                        'actions' => [
                            'view' => 'View',
                            'edit' => 'Save Marks',
                            'delete' => 'Delete',
                            'download' => 'Download Template',
                            'import' => 'Import',
                            'export' => 'Export',
                        ],
                    ],
                    'exam-results' => [
                        'label' => 'Exam Results',
                        'path' => '/exam-management/exam-results',
                        'actions' => ['view' => 'View', 'download' => 'Download'],
                    ],
                    'annual-report-card' => [
                        'label' => 'Annual Report Card',
                        'path' => '/exam-management/annual-report-card',
                        'actions' => ['view' => 'View', 'download' => 'Download', 'edit' => 'Edit Remarks'],
                    ],
                    'admit-cards' => [
                        'label' => 'Admit Cards',
                        'path' => '/exam-management/admit-cards',
                        'actions' => ['view' => 'View', 'download' => 'Download', 'edit' => 'Edit Instructions'],
                    ],
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'pages' => [
                    'class-wise' => [
                        'label' => 'Class Wise',
                        'path' => '/reports/class-wise',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'area-wise' => [
                        'label' => 'Area Wise',
                        'path' => '/reports/area-wise',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'father-wise' => [
                        'label' => 'Father Wise',
                        'path' => '/reports/father-wise',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'vehicle-wise' => [
                        'label' => 'Vehicle Wise',
                        'path' => '/reports/vehicle-wise',
                        'actions' => ['view' => 'View', 'export' => 'Export'],
                    ],
                    'udise' => [
                        'label' => 'UDISE',
                        'path' => '/reports/udise',
                        'actions' => ['view' => 'View', 'edit' => 'Edit', 'export' => 'Export'],
                    ],
                ],
            ],
            'documents' => [
                'label' => 'Documents',
                'pages' => [
                    'id-cards' => [
                        'label' => 'ID Cards',
                        'path' => '/documents/id-cards',
                        'actions' => ['view' => 'View', 'create' => 'Issue', 'edit' => 'Reissue / Edit', 'delete' => 'Delete', 'download' => 'Download'],
                    ],
                    'certificates' => [
                        'label' => 'Certificates',
                        'path' => '/documents/certificates',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'delete' => 'Delete', 'download' => 'Download', 'edit' => 'Edit Details'],
                    ],
                    'transport-cards' => [
                        'label' => 'Transport Cards',
                        'path' => '/documents/transport-cards',
                        'actions' => ['view' => 'View', 'download' => 'Download', 'edit' => 'Edit Assignment'],
                    ],
                    'template-builder' => [
                        'label' => 'Template Builder',
                        'path' => '/documents/template-builder',
                        'actions' => [
                            'view' => 'View',
                            'create' => 'Create',
                            'edit' => 'Edit',
                            'delete' => 'Delete',
                            'download' => 'Download / Preview',
                            'import' => 'Import',
                            'manage' => 'Set Default',
                        ],
                    ],
                ],
            ],
            'settings' => [
                'label' => 'Settings',
                'pages' => [
                    'school-settings' => [
                        'label' => 'School Settings',
                        'path' => '/settings/school-settings',
                        'actions' => ['view' => 'View', 'edit' => 'Edit', 'upload' => 'Upload Assets'],
                    ],
                    'academic-sessions' => [
                        'label' => 'Academic Sessions',
                        'path' => '/settings/academic-sessions',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'],
                    ],
                    'roles-and-permissions' => [
                        'label' => 'Roles & Permissions',
                        'path' => '/settings/roles-and-permissions',
                        'actions' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'manage' => 'Manage Permissions'],
                    ],
                    'database-backup' => [
                        'label' => 'Database Backup',
                        'path' => '/settings/database-backup',
                        'actions' => ['view' => 'View', 'create' => 'Create Backup', 'download' => 'Download', 'delete' => 'Delete'],
                    ],
                ],
            ],
            'account' => [
                'label' => 'Account',
                'pages' => [
                    'profile' => [
                        'label' => 'Profile',
                        'path' => '/account/profile',
                        'actions' => ['view' => 'View', 'edit' => 'Edit'],
                    ],
                    'change-password' => [
                        'label' => 'Change Password',
                        'path' => '/account/change-password',
                        'actions' => ['edit' => 'Update Password'],
                    ],
                    'notifications' => [
                        'label' => 'Notifications',
                        'path' => '/account/notifications',
                        'actions' => ['view' => 'View'],
                    ],
                    'choose-template' => [
                        'label' => 'Choose Template',
                        'path' => '/account/choose-template',
                        'actions' => ['view' => 'View', 'edit' => 'Apply'],
                    ],
                ],
            ],
        ];
    }
}
