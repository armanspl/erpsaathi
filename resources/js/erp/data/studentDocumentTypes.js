// Mirrors StudentController::DOCUMENT_TYPES on the backend — key, form-field name ("{key}_file"),
// display label, and the student.documents column that holds the stored path.
export const STUDENT_DOCUMENT_TYPES = [
    { key: 'photo', label: 'Photo', column: 'photo_path' },
    { key: 'aadhaar', label: 'Aadhaar', column: 'aadhaar_path' },
    { key: 'pan', label: 'PAN', column: 'pan_path' },
    { key: 'birth_certificate', label: 'Birth Certificate', column: 'birth_certificate_path' },
    { key: 'transfer_certificate', label: 'Transfer Certificate', column: 'transfer_certificate_path' },
    { key: 'marksheet', label: 'Marksheet', column: 'marksheet_path' },
    { key: 'father_aadhaar', label: "Father's Aadhaar", column: 'father_aadhaar_path' },
    { key: 'father_pan', label: "Father's PAN", column: 'father_pan_path' },
    { key: 'mother_aadhaar', label: "Mother's Aadhaar", column: 'mother_aadhaar_path' },
    { key: 'mother_pan', label: "Mother's PAN", column: 'mother_pan_path' },
];

// Father's/Mother's own photo — kept separate from STUDENT_DOCUMENT_TYPES (which Students.vue's
// Documents grid iterates over as-is) since these are only shown inline in Registration.vue's
// Father/Mother sections, not as two more tiles in the general Documents grid.
export const PARENT_PHOTO_DOCUMENT_TYPES = [
    { key: 'father_photo', label: "Father's Photo", column: 'father_photo_path' },
    { key: 'mother_photo', label: "Mother's Photo", column: 'mother_photo_path' },
];
