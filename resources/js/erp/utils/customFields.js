/**
 * Excel profile columns stored in custom_field_values — form key -> label
 * (mirrors App\Support\EmployeeCustomFields::PROFILE_FIELDS).
 */
export const PROFILE_FIELDS = {
    designation: 'Designation',
    grade: 'Grade',
    subject: 'Subject',
    section: 'Section',
    joining_salary: 'Basic Salary at Joining',
    dob: 'Date of Birth',
    category: 'Category',
    hs_year: 'HS Year',
    inter_year: 'Inter Year',
    grad_year: 'Grad Year',
    join_date: 'Join Date',
    address: 'Address',
    remarks: 'Remarks',
};

export function emptyProfile() {
    return Object.fromEntries(Object.keys(PROFILE_FIELDS).map((k) => [k, '']));
}

/** custom_field_values -> { designation, grade, dob, ... } for the add/edit forms. */
export function profileFromFields(fields) {
    const byLabel = {};
    normalizeCustomFields(fields).forEach((f) => { byLabel[f.label.toLowerCase()] = f.value; });
    const profile = emptyProfile();
    Object.entries(PROFILE_FIELDS).forEach(([key, label]) => { profile[key] = byLabel[label.toLowerCase()] || ''; });
    return profile;
}

/**
 * Teacher/Staff/Driver `custom_field_values` as a list of { label, value }.
 * Older imports stored a plain object ({ Designation: 'ASST TCHR' }) — both shapes are accepted
 * (mirrors App\Support\EmployeeCustomFields::normalize()).
 */
export function normalizeCustomFields(fields) {
    if (!fields || typeof fields !== 'object') return [];
    const entries = Array.isArray(fields) ? fields.map((f, i) => [i, f]) : Object.entries(fields);
    const seen = new Set();
    const list = [];
    entries.forEach(([key, field]) => {
        let label;
        let value;
        if (field && typeof field === 'object' && 'label' in field) {
            label = String(field.label ?? '').trim();
            value = field.value;
        } else if (typeof key === 'string' && (field === null || typeof field !== 'object')) {
            label = key.trim();
            value = field;
        } else {
            return;
        }
        const id = label.toLowerCase();
        if (!label || seen.has(id)) return;
        seen.add(id);
        list.push({ label, value: String(value ?? '').trim() });
    });
    return list;
}
