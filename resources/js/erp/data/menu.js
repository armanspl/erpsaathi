export function slugify(text) {
    return String(text)
        .toLowerCase()
        .replace(/&/g, 'and')
        .replace(/\+/g, 'plus')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

// Single source of truth for the sidebar tree, breadcrumbs and generated routes.
// Groups with `path` and empty `children` are direct links (e.g. Dashboard → "/").
// Other children navigate to `/${groupSlug}/${childSlug}` ("Logout" triggers logout).
const rawMenu = [
    {
        label: 'Dashboard',
        icon: '📊',
        path: '/',
        children: [],
    },
    {
        label: 'Import & Export',
        icon: '📥',
        children: [
            'Global Workbook Import', 'Global Workbook Export', 'Student PEN Import', 'Student Export',
            'Student UDISE Export',
            'Attendance Import', 'Attendance Export', 'Exam Marks Import',
        ],
    },
    {
        label: 'Academics',
        icon: '🎓',
        children: ['Branches', 'Academic Sessions', 'Classes & Sections', 'Subjects', 'Homework'],
    },
    {
        label: 'Admissions',
        icon: '📝',
        children: ['Enquiry', 'Registration', 'Admission', 'Admission Settings'],
    },
    {
        label: 'People',
        icon: '👥',
        children: ['Users', 'Students', 'Parents', 'Teachers', 'Staff', 'Drivers', 'Visitor Records', 'UDISE+ S02', 'UDISE+ S03', 'Employee Master Import'],
    },
    {
        label: 'Attendance',
        icon: '📅',
        children: [
            'Student Attendance', 'Staff Attendance', 'Driver Attendance',
            'Leave Management', 'Joining After Leave',
        ],
    },
    {
        label: 'Fee Management',
        icon: '💰',
        children: ['Fee Structure', 'Fee Due', 'Fee Paid', 'Fee History', 'Pay Fee', 'Fee Receipt', 'Fee Settings', 'Tally Accounting'],
    },
    {
        label: 'Finance & Payroll',
        icon: '🏦',
        children: ['Office Expenses', 'Salary Slips', 'Salary Sheet', 'Book Store', 'Book Expenses', 'Bank Accounts', 'Bank Transactions'],
    },
    {
        label: 'Transport Management',
        icon: '🚌',
        children: ['Routes', 'Vehicles'],
    },
    {
        label: 'Exam Management',
        icon: '📚',
        children: [
            'Exams', 'Terms', 'Exam Schedule', 'Seat Planning', 'Marks Management',
            'Exam Results', 'Annual Report Card', 'Admit Cards',
        ],
    },
    {
        label: 'Reports',
        icon: '📈',
        children: ['Class Wise', 'Area Wise', 'Father Wise', 'Vehicle Wise', 'UDISE'],
    },
    {
        label: 'Documents',
        icon: '📄',
        children: [
            'ID Cards', 'Certificates', 'Transport Cards', 'Template Builder',
        ],
    },
    {
        label: 'Settings',
        icon: '⚙️',
        children: ['School Settings', 'Academic Sessions', 'Roles & Permissions', 'Database Backup'],
    },
    {
        label: 'Account',
        icon: '👤',
        children: [
            'Profile', 'Change Password', 'Notifications', 'Choose Template', 'Logout',
            // 'API Tokens' intentionally hidden: the token vault (generate/reveal-once/revoke)
            // is fully implemented, but no auth guard in the app consumes these tokens yet, so
            // exposing it would imply a capability that doesn't exist. Backend is untouched —
            // re-add here once a real consumer is built.
        ],
    },
];

export const menu = rawMenu.map((group) => {
    const groupSlug = slugify(group.label);
    const children = (group.children || []).map((label, index) => {
        const isDashboardRoot = groupSlug === 'dashboard' && index === 0;
        const isImportExport = groupSlug === 'import-and-export';
        const isLogout = label === 'Logout';
        return {
            label,
            key: slugify(label),
            path: isLogout ? null : isImportExport ? '/import-export' : isDashboardRoot ? '/' : `/${groupSlug}/${slugify(label)}`,
            query: isImportExport ? { type: slugify(label) } : null,
            action: isLogout ? 'logout' : null,
            group: group.label,
            groupIcon: group.icon,
        };
    });

    return {
        ...group,
        key: groupSlug,
        path: group.path || null,
        children,
    };
});

export function findMenuItemByPath(path) {
    for (const group of menu) {
        if (group.path && group.path === path) {
            return { group, child: { label: group.label, path: group.path, key: group.key } };
        }
        const child = group.children.find((c) => c.path === path);
        if (child) return { group, child };
    }
    return null;
}
