import { slugify } from './menu';

// Per-group sensible defaults for the "Common Module Layout" (section 5 of the spec):
// title, breadcrumb, action buttons, filter bar, stat cards, table columns, pagination.
const GROUP_PRESETS = {
    academics: {
        columns: [{ key: 'name', label: 'Name', type: 'name' }, { key: 'code', label: 'Code', type: 'code' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Updated', type: 'date' }],
        stats: [{ label: 'Total', color: 'indigo' }, { label: 'Active', color: 'emerald' }, { label: 'Inactive', color: 'rose' }, { label: 'New', color: 'amber' }],
        extraFilters: [],
    },
    admissions: {
        columns: [{ key: 'code', label: 'Enquiry No', type: 'code', prefix: 'ENQ' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'phone', label: 'Mobile', type: 'phone' }, { key: 'class', label: 'Class Applied', type: 'class' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Pending', color: 'amber' }, { label: 'Confirmed', color: 'emerald' }, { label: 'Rejected', color: 'rose' }, { label: 'Total', color: 'indigo' }],
        extraFilters: ['class'],
    },
    people: {
        columns: [{ key: 'photo', label: 'Photo', type: 'photo' }, { key: 'code', label: 'ID No', type: 'code', prefix: 'ID' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'phone', label: 'Mobile', type: 'phone' }, { key: 'city', label: 'City', type: 'city' }, { key: 'status', label: 'Status', type: 'status' }],
        stats: [{ label: 'Total', color: 'indigo' }, { label: 'Active', color: 'emerald' }, { label: 'Inactive', color: 'rose' }, { label: 'New', color: 'amber' }],
        extraFilters: [],
    },
    attendance: {
        columns: [{ key: 'photo', label: 'Photo', type: 'photo' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'class', label: 'Class', type: 'class' }, { key: 'section', label: 'Section', type: 'section' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Present', color: 'emerald' }, { label: 'Absent', color: 'rose' }, { label: 'Leave', color: 'amber' }, { label: 'Late', color: 'sky' }],
        extraFilters: ['class', 'section'],
    },
    'fee-management': {
        columns: [{ key: 'code', label: 'Receipt No', type: 'code', prefix: 'RCPT' }, { key: 'name', label: 'Student', type: 'name' }, { key: 'class', label: 'Class', type: 'class' }, { key: 'amount', label: 'Amount', type: 'amount' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: "Today's Collection", color: 'emerald' }, { label: 'Pending', color: 'amber' }, { label: 'Discount', color: 'sky' }, { label: 'Fine', color: 'rose' }],
        extraFilters: ['class'],
    },
    'finance-and-payroll': {
        columns: [{ key: 'code', label: 'Voucher No', type: 'code', prefix: 'FIN' }, { key: 'name', label: 'Particulars', type: 'name' }, { key: 'amount', label: 'Amount', type: 'amount' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Income', color: 'emerald' }, { label: 'Expense', color: 'rose' }, { label: 'Balance', color: 'indigo' }, { label: 'Cash', color: 'amber' }, { label: 'Bank', color: 'sky' }],
        extraFilters: [],
    },
    'transport-management': {
        columns: [{ key: 'code', label: 'Code', type: 'code', prefix: 'TRP' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'city', label: 'Area', type: 'city' }, { key: 'phone', label: 'Contact', type: 'phone' }, { key: 'status', label: 'Status', type: 'status' }],
        stats: [{ label: 'Vehicles', color: 'indigo' }, { label: 'Routes', color: 'emerald' }, { label: 'Students', color: 'sky' }, { label: 'Fuel Cost', color: 'amber' }],
        extraFilters: [],
    },
    'exam-management': {
        columns: [{ key: 'code', label: 'Code', type: 'code', prefix: 'EXM' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'class', label: 'Class', type: 'class' }, { key: 'section', label: 'Section', type: 'section' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Pass %', color: 'emerald' }, { label: 'Fail %', color: 'rose' }, { label: 'Topper', color: 'indigo' }, { label: 'Average', color: 'amber' }],
        extraFilters: ['class', 'section'],
    },
    library: {
        columns: [{ key: 'code', label: 'Code', type: 'code', prefix: 'LIB' }, { key: 'name', label: 'Title / Name', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Books', color: 'indigo' }, { label: 'Issued', color: 'amber' }, { label: 'Returned', color: 'emerald' }, { label: 'Fine', color: 'rose' }],
        extraFilters: [],
    },
    inventory: {
        columns: [{ key: 'code', label: 'Item Code', type: 'code', prefix: 'INV' }, { key: 'name', label: 'Item Name', type: 'name' }, { key: 'number', label: 'Stock Qty', type: 'number' }, { key: 'amount', label: 'Value', type: 'amount' }, { key: 'status', label: 'Status', type: 'status' }],
        stats: [{ label: 'Items', color: 'indigo' }, { label: 'Stock', color: 'sky' }, { label: 'Low Stock', color: 'rose' }, { label: 'Purchase', color: 'amber' }],
        extraFilters: [],
    },
    hostel: {
        columns: [{ key: 'code', label: 'Code', type: 'code', prefix: 'HST' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'phone', label: 'Contact', type: 'phone' }, { key: 'status', label: 'Status', type: 'status' }],
        stats: [{ label: 'Rooms', color: 'indigo' }, { label: 'Occupied', color: 'amber' }, { label: 'Vacant', color: 'emerald' }, { label: 'Visitors', color: 'sky' }],
        extraFilters: [],
    },
    documents: {
        columns: [{ key: 'code', label: 'Ref No', type: 'code', prefix: 'DOC' }, { key: 'name', label: 'Student', type: 'name' }, { key: 'class', label: 'Class', type: 'class' }, { key: 'section', label: 'Section', type: 'section' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Generated', color: 'emerald' }, { label: 'Pending', color: 'amber' }, { label: 'Templates', color: 'indigo' }, { label: 'Printed', color: 'sky' }],
        extraFilters: ['class', 'section'],
    },
    communication: {
        columns: [{ key: 'code', label: 'Ref No', type: 'code', prefix: 'MSG' }, { key: 'name', label: 'Title', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }],
        stats: [{ label: 'Sent', color: 'emerald' }, { label: 'Pending', color: 'amber' }, { label: 'Scheduled', color: 'sky' }, { label: 'Total', color: 'indigo' }],
        extraFilters: [],
    },
    meetings: {
        columns: [{ key: 'code', label: 'Ref No', type: 'code', prefix: 'MTG' }, { key: 'name', label: 'Title', type: 'name' }, { key: 'date', label: 'Date', type: 'date' }, { key: 'status', label: 'Status', type: 'status' }],
        stats: [{ label: 'Scheduled', color: 'sky' }, { label: 'Completed', color: 'emerald' }, { label: 'Cancelled', color: 'rose' }, { label: 'Total', color: 'indigo' }],
        extraFilters: [],
    },
    reports: {
        columns: [{ key: 'code', label: 'Report No', type: 'code', prefix: 'RPT' }, { key: 'name', label: 'Report Name', type: 'name' }, { key: 'class', label: 'Class', type: 'class' }, { key: 'section', label: 'Section', type: 'section' }, { key: 'date', label: 'Generated On', type: 'date' }],
        stats: [],
        extraFilters: ['class', 'section'],
        hideAddButton: true,
    },
    system: {
        columns: [{ key: 'code', label: 'Ref', type: 'code', prefix: 'SYS' }, { key: 'name', label: 'Description', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Timestamp', type: 'date' }],
        stats: [{ label: 'Errors', color: 'rose' }, { label: 'Jobs', color: 'indigo' }, { label: 'Import', color: 'amber' }, { label: 'Export', color: 'sky' }],
        extraFilters: [],
        hideAddButton: true,
    },
    settings: {
        columns: [{ key: 'code', label: 'Key', type: 'code', prefix: 'CFG' }, { key: 'name', label: 'Setting', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Updated', type: 'date' }],
        stats: [{ label: 'School', color: 'indigo' }, { label: 'Session', color: 'emerald' }, { label: 'Users', color: 'sky' }, { label: 'Backup', color: 'amber' }],
        extraFilters: [],
    },
    account: {
        columns: [{ key: 'code', label: 'Ref', type: 'code', prefix: 'ACC' }, { key: 'name', label: 'Item', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Updated', type: 'date' }],
        stats: [],
        extraFilters: [],
        hideAddButton: true,
    },
};

// Hand-tailored overrides for the leaves the spec calls out with exact columns/cards.
const OVERRIDES = {};

function baseFilters(extra) {
    const base = [
        { key: 'search', label: 'Search', type: 'search' },
        { key: 'session', label: 'Session', type: 'select', options: ['2026-2027', '2025-2026', '2024-2025'] },
        { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
    ];
    const map = {
        class: { key: 'class', label: 'Class', type: 'select', options: ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'] },
        section: { key: 'section', label: 'Section', type: 'select', options: ['A', 'B', 'C', 'D'] },
        gender: { key: 'gender', label: 'Gender', type: 'select', options: ['Male', 'Female', 'Other'] },
        transport: { key: 'transport', label: 'Transport', type: 'select', options: ['Yes', 'No'] },
    };
    return [...base, ...extra.map((k) => map[k]).filter(Boolean)];
}

export function getModuleConfig(path, label, groupLabel) {
    const groupKey = slugify(groupLabel);
    const preset = GROUP_PRESETS[groupKey] || { columns: [{ key: 'code', label: 'Code', type: 'code' }, { key: 'name', label: 'Name', type: 'name' }, { key: 'status', label: 'Status', type: 'status' }, { key: 'date', label: 'Date', type: 'date' }], stats: [{ label: 'Total', color: 'indigo' }, { label: 'Active', color: 'emerald' }, { label: 'Inactive', color: 'rose' }, { label: 'New', color: 'amber' }], extraFilters: [] };
    const override = OVERRIDES[path] || {};

    return {
        title: label,
        breadcrumb: ['Dashboard', groupLabel, label],
        columns: override.columns || preset.columns,
        stats: override.stats || preset.stats,
        filters: baseFilters(preset.extraFilters || []),
        hideAddButton: !!preset.hideAddButton,
        actions: override.actions || (preset.hideAddButton ? ['export', 'print'] : ['import', 'export', 'template', 'print', 'add']),
    };
}
