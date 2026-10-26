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
            'Global Workbook Import', 'Global Workbook Export', 'Student Export',
            'Student UDISE Export',
            'Attendance Import', 'Attendance Export', 'Exam Marks Import', 'Exam Marks Export',
            'Exam Cross List Export',
            'Academic Calendar Import', 'Academic Calendar Export',
            'Class Routine Import', 'Class Routine Export',
        ],
    },
    {
        label: 'Academics',
        icon: '🎓',
        // One sidebar link; the children open as tabs on the Academics page (AcademicsHub).
        // The list is still used for permission checks and the active-section highlight.
        tabbed: true,
        children: ['Branches', 'Academic Sessions', 'Classes & Sections', 'Subjects', 'Subject Enrollment', 'Class Routine', 'Homework', 'Academic Calendar'],
    },
    {
        label: 'Admissions',
        icon: '📝',
        tabbed: true, // one sidebar link; pages open as tabs on AdmissionsHub
        children: ['Enquiry', 'Registration', 'Admission', 'Admission Settings'],
    },
    {
        label: 'People',
        icon: '👥',
        children: ['Users', 'Students', 'Parents', 'Teachers', 'Staff', 'Drivers', 'Visitor Records', 'UDISE+ S02', 'UDISE+ S03', 'Employee Master Import'],
        // What the sidebar shows (children above still drive permissions / route guards).
        // An object entry is one link covering several pages, shown as tabs on that page.
        sidebarItems: [
            { label: 'People Directory', children: ['Students', 'Parents', 'Teachers', 'Staff', 'Drivers'] },
            'Users',
            'Visitor Records',
            { label: 'UDISE+', children: ['UDISE+ S02', 'UDISE+ S03'] },
            'Employee Master Import',
        ],
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
        children: ['Fee Structure', 'Fee Due', 'Fee Paid', 'Fee History', 'Month-wise Fee Collection', 'Demand Slip', 'Fee Receipt', 'Fee Settings', 'Tally Accounting'],
        // Sidebar grouping only (children above still drive permissions / route guards).
        sidebarItems: [
            { label: 'Fee Setup', children: ['Fee Structure', 'Fee Settings'] },
            { label: 'Fee Collection', children: ['Fee Due', 'Fee Paid', 'Fee Receipt'] },
            { label: 'Fee Reports', children: ['Fee History', 'Month-wise Fee Collection', 'Demand Slip'] },
            'Tally Accounting',
        ],
    },
    {
        label: 'Finance & Payroll',
        icon: '🏦',
        children: ['Office Expenses', 'Salary Slips', 'Salary Sheet', 'Salary History', 'Book Store', 'Book Expenses', 'Bank Accounts', 'Bank Transactions'],
        // Sidebar grouping only (children above still drive permissions / route guards).
        sidebarItems: [
            { label: 'Salary & Payroll', children: ['Salary Sheet', 'Salary Slips', 'Salary History'] },
            { label: 'Expenses', children: ['Office Expenses', 'Book Expenses'] },
            'Book Store',
            { label: 'Banking', children: ['Bank Accounts', 'Bank Transactions'] },
        ],
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
            'Exam Results', 'Annual Report Card', 'Co-Scholastic Areas', 'Admit Cards',
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
        children: ['School Settings', 'Academic Sessions', 'Roles & Permissions', 'Database Backup', 'Student Portal', 'WhatsApp'],
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

// Line icon per sidebar section (AppIcon names); the emoji `icon` stays as a fallback.
const GROUP_ICONS = {
    dashboard: 'dashboard',
    'import-and-export': 'transfer',
    academics: 'graduation',
    admissions: 'clipboard',
    people: 'users',
    attendance: 'calendar-check',
    'fee-management': 'wallet',
    'finance-and-payroll': 'bank',
    'transport-management': 'bus',
    'exam-management': 'book',
    reports: 'chart',
    documents: 'file',
    settings: 'settings',
    account: 'user',
};

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
        svg: GROUP_ICONS[groupSlug] || null,
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
