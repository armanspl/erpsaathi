<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Central catalog for the Template Builder: which categories exist, which data fields each
 * category can show, sample values used to preview a template (canvas + PDF), and the starter
 * layout seeded when a new template is created. Only one starter per category is implemented
 * (not the full set of named variants a real mockup might offer) — see the Template Builder
 * completion notes for that disclosed scope cut.
 */
class TemplateCatalog
{
    public const CATEGORIES = [
        'admit_card' => 'Admit Card',
        'id_card' => 'ID Card',
        'transport_card' => 'Transport Card',
        'library_card' => 'Library Card',
        'certificate' => 'Certificate',
        'report_card' => 'Report Card / Exam Results',
        'exam_schedule' => 'Exam Schedule',
        'fee_receipt' => 'Fee Receipt',
        'fee_due_receipt' => 'Fee Due Receipt',
        'salary_slip' => 'Salary Slip',
        'book_expense' => 'Book Expense',
    ];

    /** Common page size presets (mm) for the visual editor. */
    public const PAGE_PRESETS = [
        'A4 Portrait' => [210, 297],
        'A4 Landscape' => [297, 210],
        'A5 Portrait' => [148, 210],
        'A5 Landscape' => [210, 148],
        'ID Card' => [85.6, 54],
        'Letter Portrait' => [215.9, 279.4],
    ];

    /** [key, label, locked] per category. Locked fields ship on the starter and can only be hidden, never removed. */
    public const FIELDS = [
        'certificate' => [
            ['school_name', 'School name', true],
            ['school_address', 'School address', false],
            ['certificate_title', 'Certificate title', true],
            ['recipient_name', 'Recipient name', true],
            ['father_name', 'Father name', false],
            ['mother_name', 'Mother name', false],
            ['admission_id', 'Admission ID', false],
            ['roll_number', 'Roll number', false],
            ['class', 'Class', false],
            ['section', 'Section', false],
            ['branch', 'Branch', false],
            ['session_year', 'Session year', false],
            ['certificate_body', 'Certificate body', true],
            ['dob', 'Date of birth', false],
            ['dob_words', 'DOB in words', false],
            ['purpose', 'Purpose', false],
            ['reference_no', 'Reference no.', false],
            ['issue_date', 'Issue date', true],
            ['conduct', 'Conduct', false],
            ['character', 'Character', false],
            ['school_logo', 'School logo', false],
            ['school_banner', 'School banner', false],
            ['principal_signature_image', 'Principal signature image', false],
            ['school_stamp', 'School stamp', false],
            ['photo', 'Student photo', false],
            ['nationality', 'Nationality', false],
            ['category', 'Category (SC/ST/OBC)', false],
            ['admission_date', 'Date of first admission', false],
            ['dob_numeric', 'Date of birth (DD-MM-YYYY)', false],
            ['issue_date_numeric', 'Issue date (DD-MM-YYYY)', false],
            ['pen_no', 'PEN no.', false],
            ['sr_no', 'SR / certificate no.', false],
            ['book_no', 'Book no.', false],
            ['remarks', 'Remarks', false],
            ['registration_no', 'School registration no.', false],
            ['udise_code', 'U-DISE code', false],
            ['last_exam_result', 'Last exam taken & result', false],
            ['failed_status', 'Whether failed (Yes/No)', false],
            ['subjects_studied', 'Subjects studied', false],
            ['promotion_status', 'Qualified for promotion (Yes/No)', false],
            ['promoted_class', 'Promoted to class', false],
            ['working_days', 'Total working days', false],
            ['presence_days', 'Total days present', false],
            ['fee_paid_upto', 'Fees paid upto (month)', false],
            ['fee_concession', 'Fee concession availed', false],
            ['ncc_activities', 'NCC / Scout / Guide', false],
            ['games_activities', 'Games / extra-curricular', false],
            ['general_conduct', 'General conduct', false],
            ['application_date', 'Date of application', false],
        ],
        'admit_card' => [
            ['school_name', 'School name', true],
            ['accent_color', 'PDF accent colour', true],
            ['accent_dark', 'PDF accent colour (dark)', true],
            ['accent_light', 'PDF accent colour (light tint)', true],
            ['accent_border', 'PDF accent colour (border tint)', true],
            ['accent_zebra', 'PDF accent colour (zebra tint)', true],
            ['accent_bar_bg', 'PDF accent bar background', true],
            ['accent_bar_text', 'PDF accent bar text', true],
            ['accent_bar_text_soft', 'PDF accent bar soft text', true],
            ['accent_bar_text_softer', 'PDF accent bar softer text', true],
            ['mono_class', 'Greyscale mode CSS class ("mono" or blank)', false],
            ['school_address', 'School address', false],
            ['exam_title', 'Exam title', true],
            ['student_name', 'Student name', true],
            ['photo', 'Student photo', false],
            ['photo_html', 'Student photo HTML', true],
            ['admission_id', 'Admission ID', false],
            ['roll_number', 'Roll number', false],
            ['class', 'Class', false],
            ['section', 'Section', false],
            ['gender', 'Gender', false],
            ['blood_group', 'Blood group', false],
            ['father_name', 'Father name', false],
            ['mother_name', 'Mother name', false],
            ['dob', 'Date of birth', false],
            ['schedule_table', 'Exam schedule table (plain text)', false],
            ['schedule_table_html', 'Exam schedule table', true],
            ['general_instructions', 'General instructions', true],
            ['school_meta_line', 'Registration no. / U-DISE code line', false],
            ['school_contact_line', 'School address & contact line', false],
            ['signature_line', 'Signature line', false],
            ['principal_signature', 'Principal signature label', false],
            ['principal_signature_image', 'Principal signature image', false],
            ['principal_sign_html', 'Principal signature HTML', true],
            ['signature_image', 'Examination controller signature image', false],
            ['exam_controller_sign_html', 'Examination controller signature HTML', true],
            ['class_teacher_signature_image', 'Class teacher signature image', false],
            ['class_teacher_sign_html', 'Class teacher signature HTML', true],
            ['school_logo', 'School logo', false],
        ],
        'id_card' => [
            ['school_name', 'School name', true],
            ['card_title', 'Card title', true],
            ['holder_name', 'Holder name', true],
            ['photo', 'Photo', false],
            ['id_number', 'ID number', true],
            ['role_type', 'Role / Type', false],
            ['class_section', 'Class / Section', false],
            ['blood_group', 'Blood group', false],
            ['contact_no', 'Contact no.', false],
            ['valid_until', 'Valid until', false],
            ['school_logo', 'School logo', false],
            ['barcode', 'Barcode / QR', false],
            ['signature', 'Authorized signature', false],
            ['principal_signature_image', 'Principal signature image', false],
        ],
        'transport_card' => [
            ['school_name', 'School name', true],
            ['card_title', 'Card title', true],
            ['student_name', 'Student name', true],
            ['photo', 'Photo', false],
            ['admission_id', 'Admission ID', false],
            ['class_section', 'Class / Section', false],
            ['route_name', 'Route name', true],
            ['pickup_stop', 'Pickup stop', true],
            ['driver_name', 'Driver name', false],
            ['vehicle_no', 'Vehicle no.', false],
            ['valid_until', 'Valid until', false],
            ['school_logo', 'School logo', false],
        ],
        'library_card' => [
            ['school_name', 'School name', true],
            ['card_title', 'Card title', true],
            ['member_name', 'Member name', true],
            ['photo', 'Photo', false],
            ['member_id', 'Membership ID', true],
            ['member_type', 'Member type', false],
            ['class_section', 'Class / Section', false],
            ['valid_from', 'Valid from', false],
            ['valid_until', 'Valid until', true],
            ['school_logo', 'School logo', false],
            ['barcode', 'Barcode / QR', false],
        ],
        'report_card' => [
            ['school_name', 'School name', true],
            ['accent_color', 'PDF accent colour', true],
            ['accent_dark', 'PDF accent colour (dark)', true],
            ['accent_light', 'PDF accent colour (light tint)', true],
            ['accent_border', 'PDF accent colour (border tint)', true],
            ['accent_zebra', 'PDF accent colour (zebra tint)', true],
            ['accent_bar_bg', 'PDF accent bar background', true],
            ['accent_bar_text', 'PDF accent bar text', true],
            ['accent_bar_text_soft', 'PDF accent bar soft text', true],
            ['accent_bar_text_softer', 'PDF accent bar softer text', true],
            ['mono_class', 'Greyscale mode CSS class ("mono" or blank)', false],
            ['school_address', 'School address', false],
            ['school_phone_line', 'School phone line', false],
            ['registration_no', 'Registration no.', false],
            ['udise_code', 'U-DISE code', false],
            ['session_year', 'Session year', false],
            ['exam_title', 'Exam title', true],
            ['exam_band_title', 'Scholastic band title', false],
            ['student_name', 'Student name', true],
            ['father_name', 'Father name', false],
            ['mother_name', 'Mother name', false],
            ['dob', 'Date of birth', false],
            ['admission_id', 'Admission ID', false],
            ['roll_number', 'Roll number', false],
            ['class', 'Class', false],
            ['section', 'Section', false],
            ['class_section', 'Class / Section', false],
            ['logo_html', 'Logo HTML', false],
            ['marks_table', 'Marks table (plain text)', false],
            ['marks_table_class', 'Marks table CSS class (annual/blank)', false],
            ['marks_thead_html', 'Marks table header HTML', true],
            ['marks_html', 'Marks table HTML rows', true],
            ['marks_summary_html', 'Marks summary strip HTML (annual only)', false],
            ['total_marks', 'Total marks', false],
            ['percentage', 'Percentage', false],
            ['grade', 'Grade', false],
            ['result', 'Result', false],
            ['rank', 'Class rank', false],
            ['attendance', 'Attendance', false],
            ['remarks', 'Remarks', false],
            ['co_scholastic_html', 'Co-scholastic area grid HTML', true],
            ['grading_html', 'Grading system HTML', false],
            ['chart_html', 'Subject chart HTML', false],
            ['class_teacher_signature', 'Class teacher signature', false],
            ['principal_signature', 'Principal signature', false],
            ['principal_signature_image', 'Principal signature image', false],
            ['principal_sign_html', 'Principal signature HTML', false],
            ['stamp_html', 'School stamp HTML', false],
            ['school_logo', 'School logo', false],
            ['school_stamp', 'School stamp', false],
        ],
        'exam_schedule' => [
            ['school_name', 'School name', true],
            ['accent_color', 'PDF accent colour', true],
            ['accent_dark', 'PDF accent colour (dark)', true],
            ['accent_light', 'PDF accent colour (light tint)', true],
            ['accent_border', 'PDF accent colour (border tint)', true],
            ['accent_zebra', 'PDF accent colour (zebra tint)', true],
            ['accent_bar_bg', 'PDF accent bar background', true],
            ['accent_bar_text', 'PDF accent bar text', true],
            ['accent_bar_text_soft', 'PDF accent bar soft text', true],
            ['accent_bar_text_softer', 'PDF accent bar softer text', true],
            ['mono_class', 'Greyscale mode CSS class ("mono" or blank)', false],
            ['school_address', 'School address', false],
            ['school_meta_line', 'School meta line', false],
            ['school_contact_line', 'School contact line', false],
            ['session_year', 'Session year', false],
            ['branch', 'Branch', false],
            ['schedule_title', 'Schedule title', true],
            ['exam_title', 'Exam title', true],
            ['sittings_note', 'Sittings note', false],
            ['logo_html', 'Logo HTML', false],
            ['schedule_header_html', 'Schedule header HTML', true],
            ['schedule_body_html', 'Schedule body HTML', true],
            ['schedule_table', 'Schedule table (plain text)', false],
            ['invigilator_signature', 'Invigilator signature label', false],
            ['signature_line', 'Exam controller label', false],
            ['principal_signature', 'Principal signature label', false],
            ['exam_controller_sign_html', 'Exam controller signature HTML', false],
            ['principal_sign_html', 'Principal signature HTML', false],
            ['generated_at', 'Generated at', false],
            ['school_logo', 'School logo', false],
        ],
        'fee_receipt' => [
            ['school_name', 'School name', true],
            ['school_address', 'School address', false],
            ['school_phone', 'School phone', false],
            ['school_logo', 'School logo', false],
            ['receipt_title', 'Receipt title', true],
            ['receipt_no', 'Receipt no.', false],
            ['receipt_date', 'Receipt date', true],
            ['student_name', 'Student name', true],
            ['father_name', 'Father name', false],
            ['city', 'City / Village', false],
            ['admission_id', 'Admission ID', false],
            ['class', 'Class', false],
            ['section', 'Section', false],
            ['fee_for_months', 'Fee for month(s)', false],
            ['items_table', 'Fee items table', true],
            ['amount_in_words', 'Amount in words', false],
            ['total_fees', 'Total fees', true],
            ['back_dues', 'Back dues', false],
            ['late_fee', 'Late fee', false],
            ['adjustment', 'Adjustment', false],
            ['grand_total', 'Grand total', true],
            ['paid_amount', 'Paid amount', true],
            ['balance_dues', 'Total balance dues', true],
            ['payment_mode', 'Payment mode', false],
            ['received_by', 'Received by', false],
            ['remarks', 'Remarks', false],
        ],
        'fee_due_receipt' => [
            ['school_name', 'School name', true],
            ['school_address', 'School address', false],
            ['school_logo', 'School logo', false],
            ['receipt_title', 'Receipt title', true],
            ['receipt_no', 'Receipt no.', false],
            ['receipt_date', 'Receipt date', false],
            ['student_name', 'Student name', true],
            ['father_name', 'Father name', false],
            ['mother_name', 'Mother name', false],
            ['roll_number', 'Roll number', false],
            ['admission_id', 'Admission ID', false],
            ['class', 'Class', false],
            ['section', 'Section', false],
            ['due_table', 'Due items table', true],
            ['total_due', 'Total due', true],
            ['due_date', 'Due date', false],
            ['payment_history_table', 'Payment history table', false],
            ['remarks', 'Remarks', false],
        ],
        'salary_slip' => [
            ['school_name', 'School name', true],
            ['school_address', 'School address', false],
            ['school_phone', 'School phone', false],
            ['slip_title', 'Slip title', true],
            ['slip_no', 'Slip no.', true],
            ['employee_name', 'Employee name', true],
            ['employee_type', 'Employee type', false],
            ['employee_code', 'Employee code', false],
            ['period', 'Pay period', true],
            ['earnings_table', 'Earnings table', true],
            ['deductions_table', 'Deductions table', true],
            ['net_salary', 'Net salary', true],
            ['payment_mode', 'Payment mode', false],
            ['status', 'Status', false],
            ['remarks', 'Remarks', false],
            ['school_logo', 'School logo', false],
            ['days_in_month', 'Days in month', false],
            ['present', 'Present days', false],
            ['absent', 'Absent days', false],
            ['cl', 'Casual leave days', false],
            ['total_days', 'Total days', false],
            ['per_day_rate', 'Per-day rate', false],
            ['this_month_salary', 'This month salary (pre-advance)', false],
            ['advance', 'Advance paid', false],
        ],
        'book_expense' => [
            ['school_name', 'School name', true],
            ['school_address', 'School address', false],
            ['receipt_title', 'Receipt title', true],
            ['expense_no', 'Expense no.', true],
            ['expense_date', 'Expense date', true],
            ['student_name', 'Student name', true],
            ['admission_id', 'Admission ID', false],
            ['class_section', 'Class / Section', false],
            ['items_table', 'Items table', true],
            ['total_amount', 'Total amount', true],
            ['school_logo', 'School logo', false],
        ],
    ];

    /**
     * Sample data can't be a class const (date()/strtotime() aren't compile-time constant
     * expressions), so this is a method instead — called the same way, just with parens.
     */
    public static function sampleData(string $category): array
    {
        $data = self::ALL_SAMPLE_DATA[$category] ?? [];

        foreach (['issue_date', 'receipt_date', 'expense_date'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = date('d M Y');
            }
        }
        if (array_key_exists('generated_at', $data)) {
            $data['generated_at'] = date('d M Y, h:i A');
        }
        if (array_key_exists('due_date', $data)) {
            $data['due_date'] = date('d M Y', strtotime('+15 days'));
        }
        if (array_key_exists('issue_date_numeric', $data)) {
            $data['issue_date_numeric'] = date('d-m-Y');
        }

        // Overlay live School Settings so Template Builder previews match printed docs.
        try {
            $live = app(DocumentDataBuilder::class)->schoolContext();
            foreach ($live as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $data[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // Keep catalog samples if school settings aren't available yet.
        }

        // Every registered field must exist (even if blank) — Blade-based designs reference these
        // as real variables and throw "Undefined variable" if a key is simply missing, which
        // happens whenever a school hasn't uploaded an optional image (stamp, signature, banner...).
        foreach (self::FIELDS[$category] ?? [] as [$key]) {
            if (! array_key_exists($key, $data)) {
                $data[$key] = '';
            }
        }

        return $data;
    }

    private const ALL_SAMPLE_DATA = [
        'certificate' => [
            'school_name' => 'Springfield Public School', 'certificate_title' => 'Bonafide Certificate',
            'recipient_name' => 'Aarav Sharma', 'father_name' => 'Rajesh Sharma', 'mother_name' => 'Sunita Sharma',
            'admission_id' => 'ADM/2026/0142', 'roll_number' => '23', 'class' => '8', 'section' => 'A',
            'branch' => 'Main Campus', 'session_year' => '2026-2027',
            'certificate_body' => 'This is to certify that Aarav Sharma, son of Rajesh Sharma, is a bonafide student of this school studying in Class 8, Section A during the academic session 2026-2027. His conduct during this period has been good.',
            'dob' => '10 Mar 2015', 'dob_words' => 'Tenth March Two Thousand Fifteen', 'purpose' => 'Scholarship application',
            'reference_no' => 'CER/1/2026/0231', 'issue_date' => '', 'conduct' => 'good', 'character' => 'excellent',
            'school_logo' => '', 'school_banner' => '', 'photo' => '',
            'nationality' => 'Indian', 'category' => 'N.A.', 'admission_date' => '01-03-2020',
            'dob_numeric' => '10-03-2015', 'issue_date_numeric' => '',
            'pen_no' => '22455045913', 'sr_no' => 'GAS/TC/2026/0231', 'book_no' => '2', 'remarks' => 'Best of luck',
            'registration_no' => '', 'udise_code' => '',
            'last_exam_result' => 'School Annual Exam', 'failed_status' => 'No',
            'subjects_studied' => 'English, Hindi, Maths, Science, Social Science, Computer',
            'promotion_status' => 'Yes', 'promoted_class' => '9', 'working_days' => '222', 'presence_days' => '208',
            'fee_paid_upto' => 'March 2026', 'fee_concession' => 'No', 'ncc_activities' => 'No', 'games_activities' => 'No',
            'general_conduct' => 'Good', 'application_date' => '27-03-2026',
        ],
        'admit_card' => [
            'school_name' => 'Springfield Public School',
            'accent_color' => '#1e3a5f', 'accent_dark' => '#132638', 'accent_light' => '#e2e8ee', 'accent_border' => '#a9b9cb', 'accent_zebra' => '#f4f6f9',
            'accent_bar_bg' => '#1e3a5f', 'accent_bar_text' => '#ffffff', 'accent_bar_text_soft' => 'rgba(255,255,255,0.85)', 'accent_bar_text_softer' => 'rgba(255,255,255,0.7)',
            'mono_class' => '',
            'exam_title' => 'Annual Examination 2026-2027',
            'student_name' => 'Aarav Sharma', 'photo' => '', 'photo_html' => '', 'admission_id' => 'ADM/2026/0142', 'roll_number' => '23',
            'class' => '8', 'section' => 'A', 'gender' => 'Male', 'blood_group' => 'O+',
            'father_name' => 'Rajesh Sharma', 'mother_name' => 'Sunita Sharma',
            'dob' => '10 Mar 2015',
            'schedule_table' => "Date         Day        Sitting  Subject  Sitting  Subject\n"
                ."12 Sep 2026  Saturday   1        ENG      2        Oral",
            'schedule_table_html' => '<table class="sched"><thead><tr>'
                .'<th>Date</th><th>Day</th><th class="center">Sitting</th><th>Subject</th><th class="center">Sitting</th><th>Subject</th>'
                .'</tr></thead><tbody><tr>'
                .'<td class="dt">12 Sep 2026</td><td class="day">Saturday</td>'
                .'<td class="center sit">1</td><td class="sub">ENG</td>'
                .'<td class="center sit">2</td><td class="sub">Oral</td>'
                .'</tr></tbody></table>',
            'general_instructions' => '<ol><li>Students must carry this admit card to the examination hall every day; entry will not be allowed without it.</li>'
                .'<li>Reach the examination centre at least 30 minutes before the scheduled time.</li>'
                .'<li>Any form of malpractice or misconduct during the examination will lead to strict disciplinary action.</li></ol>',
            'school_meta_line' => 'Registration No: 123456  |  U-DISE Code: 09071234567',
            'school_contact_line' => '12 Park Avenue, Springfield  |  Phone: 98765 43210  |  Email: info@school.edu',
            'signature_line' => 'Controller of Examinations', 'principal_signature' => 'Principal', 'school_logo' => '',
            'signature_image' => '', 'principal_signature_image' => '', 'class_teacher_signature_image' => '',
            'class_teacher_sign_html' => '', 'exam_controller_sign_html' => '', 'principal_sign_html' => '',
        ],
        'id_card' => [
            'school_name' => 'Springfield Public School', 'card_title' => 'Identity Card', 'holder_name' => 'Aarav Sharma',
            'photo' => '', 'id_number' => 'ADM/2026/0142', 'role_type' => 'Student', 'class_section' => 'Class 8 (A)',
            'blood_group' => 'O+', 'contact_no' => '98765 43210', 'valid_until' => '31 Mar 2027', 'school_logo' => '',
            'barcode' => 'ADM/2026/0142', 'signature' => 'Principal', 'principal_signature_image' => '',
        ],
        'transport_card' => [
            'school_name' => 'Springfield Public School', 'card_title' => 'Transport Card', 'student_name' => 'Aarav Sharma',
            'photo' => '', 'admission_id' => 'ADM/2026/0142', 'class_section' => 'Class 8 (A)', 'route_name' => 'Route 4 - North Loop',
            'pickup_stop' => 'Green Park Stop', 'driver_name' => 'Suresh Kumar', 'vehicle_no' => 'DL 1A 4521',
            'valid_until' => '31 Mar 2027', 'school_logo' => '',
        ],
        'library_card' => [
            'school_name' => 'Springfield Public School', 'card_title' => 'Library Card', 'member_name' => 'Aarav Sharma',
            'photo' => '', 'member_id' => 'LIB-00231', 'member_type' => 'Student', 'class_section' => 'Class 8 (A)',
            'valid_from' => '01 Apr 2026', 'valid_until' => '31 Mar 2027', 'school_logo' => '', 'barcode' => 'LIB-00231',
        ],
        'report_card' => [
            'school_name' => 'Springfield Public School',
            'accent_color' => '#1e3a5f', 'accent_dark' => '#132638', 'accent_light' => '#e2e8ee', 'accent_border' => '#a9b9cb', 'accent_zebra' => '#f4f6f9',
            'accent_bar_bg' => '#1e3a5f', 'accent_bar_text' => '#ffffff', 'accent_bar_text_soft' => 'rgba(255,255,255,0.85)', 'accent_bar_text_softer' => 'rgba(255,255,255,0.7)',
            'mono_class' => '',
            'school_address' => '12 Park Avenue, Springfield',
            'school_phone_line' => 'MOB. 98765 43210',
            'registration_no' => 'REG-2020-0142',
            'udise_code' => '09071234567',
            'session_year' => '2026-2027',
            'exam_title' => 'Annual Examination Report Card',
            'exam_band_title' => 'ANNUAL EXAMINATION (500 Marks)',
            'student_name' => 'Aarav Sharma',
            'father_name' => 'Rajesh Sharma',
            'mother_name' => 'Sunita Sharma',
            'dob' => '10-03-2015',
            'admission_id' => 'ADM/2026/0142',
            'roll_number' => '23',
            'class' => '8',
            'section' => 'A',
            'class_section' => 'Class 8 (A)',
            'logo_html' => '<div class="logo-fallback">SP</div>',
            'marks_table' => "Subject            Obt / Max\nEnglish               92 / 100\nHindi                 88 / 100\nMathematics           95 / 100\nScience               90 / 100\nSocial Science        87 / 100",
            'marks_table_class' => '',
            'marks_thead_html' => '<tr><th class="band" colspan="1">Scholastic Area</th><th class="band" colspan="4">ANNUAL EXAMINATION (500 Marks)</th></tr>'
                .'<tr><th>Subject</th><th>Obtained</th><th>Max</th><th>%</th><th>Grade</th></tr>',
            'marks_html' => '<tr><td class="subj">ENGLISH</td><td class="num">92</td><td class="num">100</td><td class="num">92</td><td class="grade">A1</td></tr>'
                .'<tr><td class="subj">HINDI</td><td class="num">88</td><td class="num">100</td><td class="num">88</td><td class="grade">A2</td></tr>'
                .'<tr><td class="subj">MATHEMATICS</td><td class="num">95</td><td class="num">100</td><td class="num">95</td><td class="grade">A1</td></tr>'
                .'<tr><td class="subj">SCIENCE</td><td class="num">90</td><td class="num">100</td><td class="num">90</td><td class="grade">A2</td></tr>'
                .'<tr><td class="subj">SOCIAL SCIENCE</td><td class="num">87</td><td class="num">100</td><td class="num">87</td><td class="grade">A2</td></tr>',
            'marks_summary_html' => '',
            'total_marks' => '452 / 500',
            'percentage' => '90.4 %',
            'grade' => 'A1',
            'result' => 'Pass',
            'rank' => '2',
            'attendance' => '208 / 222',
            'remarks' => 'GOOD / VERY GOOD / EXCELLENT',
            'co_scholastic_html' => '<table class="co-grid"><tr><th class="area-head" rowspan="2">Co-Scholastic Area</th><th class="remarks-head" colspan="2">Remarks</th></tr><tr><th class="term-head">Term-1</th><th class="term-head">Term-2</th></tr>'
                .'<tr><td class="area">WORK EDUCATION</td><td class="grade">A</td><td class="grade">A</td></tr>'
                .'<tr><td class="area">DRAWING &amp; ART</td><td class="grade">A</td><td class="grade">A</td></tr>'
                .'<tr><td class="area">SPORTS</td><td class="grade">A</td><td class="grade">A</td></tr></table>',
            'grading_html' => '<table class="grade-grid"><tr><td class="g-range">91-100</td><td class="g-letter">A1</td><td style="width:10pt"></td><td class="g-range">51-60</td><td class="g-letter">C1</td></tr>'
                .'<tr><td class="g-range">81-90</td><td class="g-letter">A2</td><td style="width:10pt"></td><td class="g-range">41-50</td><td class="g-letter">C2</td></tr>'
                .'<tr><td class="g-range">71-80</td><td class="g-letter">B1</td><td style="width:10pt"></td><td class="g-range">33-40</td><td class="g-letter">D</td></tr>'
                .'<tr><td class="g-range">61-70</td><td class="g-letter">B2</td><td style="width:10pt"></td><td class="g-range">32 BELOW</td><td class="g-letter">E</td></tr></table>',
            'chart_html' => '<div style="text-align:center;color:#888;font-size:8.5pt;padding:16pt 0">Sample chart preview</div>',
            'class_teacher_signature' => "Class Teacher's Sign",
            'principal_signature' => "Principal's Sign",
            'principal_sign_html' => '<div class="sig-space"></div>',
            'stamp_html' => '',
            'school_logo' => '',
            'school_stamp' => '',
        ],
        'exam_schedule' => [
            'school_name' => 'Springfield Public School',
            'accent_color' => '#1e3a5f', 'accent_dark' => '#132638', 'accent_light' => '#e2e8ee', 'accent_border' => '#a9b9cb', 'accent_zebra' => '#f4f6f9',
            'accent_bar_bg' => '#1e3a5f', 'accent_bar_text' => '#ffffff', 'accent_bar_text_soft' => 'rgba(255,255,255,0.85)', 'accent_bar_text_softer' => 'rgba(255,255,255,0.7)',
            'mono_class' => '',
            'school_address' => '12 Park Avenue, Springfield',
            'school_meta_line' => 'Reg No.: REG-2020-0142  |  U-DISE: 09071234567',
            'school_contact_line' => 'Ph: 98765 43210  ·  info@springfield.edu',
            'session_year' => '2026-2027',
            'branch' => 'Main Campus',
            'schedule_title' => 'Examination Schedule',
            'exam_title' => 'Annual Examination 2026-2027',
            'sittings_note' => ' · Morning (09:00–12:00)',
            'logo_html' => '<div class="logo-fallback">SP</div>',
            'schedule_header_html' => '<th class="col-date">Date</th><th>Class 8 (A)</th><th>Class 8 (B)</th>',
            'schedule_body_html' => '<tr><td class="col-date">10 Apr 2026<div class="dow">Friday</div></td><td><div class="subject">English<span class="time">09:00–12:00</span></div></td><td><div class="subject">English<span class="time">09:00–12:00</span></div></td></tr>'
                .'<tr><td class="col-date">12 Apr 2026<div class="dow">Sunday</div></td><td class="holiday" colspan="1">Holiday</td><td class="holiday">Holiday</td></tr>'
                .'<tr><td class="col-date">14 Apr 2026<div class="dow">Tuesday</div></td><td><div class="subject">Mathematics<span class="time">09:00–12:00</span></div></td><td><div class="subject">Mathematics<span class="time">09:00–12:00</span></div></td></tr>',
            'schedule_table' => "Date | Class 8 (A) | Class 8 (B)\n10 Apr 2026 English | English\n14 Apr 2026 Mathematics | Mathematics",
            'invigilator_signature' => 'Class Teacher / Invigilator',
            'signature_line' => 'Controller of Examinations',
            'principal_signature' => 'Principal',
            'exam_controller_sign_html' => '<div class="sig-space"></div>',
            'principal_sign_html' => '<div class="sig-space"></div>',
            'generated_at' => '',
            'school_logo' => '',
        ],
        'fee_receipt' => [
            'school_name' => 'Global Access School', 'receipt_title' => 'Fee Receipt', 'receipt_no' => 'FR-2026-0142',
            'receipt_date' => '', 'student_name' => 'MOHAMMAD ISRAR ALI', 'father_name' => 'DR MOHAMMAD IZHAR ALI',
            'city' => 'SIWAN', 'admission_id' => '251395', 'class' => '3', 'section' => 'A', 'fee_for_months' => 'Jun Jul',
            'items_table' => "S.N Particulars      Duration      Amount       Paid\n"
                ."1   TUITION FEE       Jun 2026      2,000.00   1,000.00\n"
                ."2   Transport         Jun 2026      1,500.00     500.00",
            'total_fees' => '2,000.00', 'back_dues' => '0.00', 'late_fee' => '0.00', 'adjustment' => '0.00',
            'grand_total' => '2,000.00', 'paid_amount' => '2,000.00', 'balance_dues' => '0.00',
            'amount_in_words' => 'Rupees Two Thousand Only', 'payment_mode' => 'CASH', 'received_by' => 'Accounts Office',
            'remarks' => 'TUI 2000 PAY',
            'school_logo' => '',
        ],
        'fee_due_receipt' => [
            'school_name' => 'Springfield Public School', 'receipt_title' => 'Fee Due Receipt',
            'receipt_no' => 'DUE-ADM20260142-20260731', 'receipt_date' => '',
            'student_name' => 'Aarav Sharma', 'father_name' => 'Rajesh Sharma', 'mother_name' => 'Sunita Sharma',
            'roll_number' => '23', 'admission_id' => 'ADM/2026/0142', 'class' => '8', 'section' => 'A',
            'due_table' => '<div class="dues-flow">'
                .'<div class="dues-group"><span class="dues-month">APR 2026</span><span class="dues-fees"><span class="dues-chip"><span class="dues-name">Admission</span> <span class="dues-amt">₹3,500</span></span><span class="dues-sep">|</span><span class="dues-chip"><span class="dues-name">Session</span> <span class="dues-amt">₹4,500</span></span></span></div>'
                .'<div class="dues-group"><span class="dues-month">JUL 2026</span><span class="dues-fees"><span class="dues-chip"><span class="dues-name">Tuition</span> <span class="dues-amt">₹1,500</span></span></span></div>'
                .'<div class="dues-group"><span class="dues-month">AUG 2026</span><span class="dues-fees"><span class="dues-chip"><span class="dues-name">Tuition</span> <span class="dues-amt">₹1,500</span></span></span></div>'
                .'<div class="dues-group"><span class="dues-month">SEP 2026</span><span class="dues-fees"><span class="dues-chip"><span class="dues-name">Tuition</span> <span class="dues-amt">₹1,500</span></span></span></div>'
                .'</div>',
            'total_due' => '₹12,500', 'due_date' => '',
            'payment_history_table' => '<div class="pay-flow">'
                .'<div class="pay-group"><span class="pay-date">21 Jul 2026</span><span class="pay-pipe">|</span><span class="pay-mode">Bank Transfer</span><span class="pay-pipe">|</span><span class="pay-amt">₹4,900</span></div>'
                .'<div class="pay-group"><span class="pay-date">04 Apr 2026</span><span class="pay-pipe">|</span><span class="pay-mode">Cash</span><span class="pay-pipe">|</span><span class="pay-amt">₹500</span></div>'
                .'</div>',
            'remarks' => 'Please clear dues before the due date.',
            'school_logo' => '',
        ],
        'salary_slip' => [
            'school_name' => 'Springfield Public School',
            'school_address' => '12 Park Avenue · 98765 43210',
            'school_phone' => '98765 43210',
            'slip_title' => 'Salary Slip',
            'slip_no' => 'SS-2026-0142',
            'employee_name' => 'Priya Verma',
            'employee_type' => 'Teacher',
            'employee_code' => 'TCH-014',
            'period' => 'July 2026',
            'earnings_table' => "Earnings                 Amount\nBasic                   28,000.00\nHRA                      4,000.00",
            'deductions_table' => "Deductions               Amount\nPF                       1,800.00\nTDS                       500.00",
            'net_salary' => '29,700.00',
            'payment_mode' => 'Bank',
            'status' => 'Paid',
            'remarks' => '',
            'school_logo' => '',
            'days_in_month' => '31',
            'present' => '27.00',
            'absent' => '1.00',
            'cl' => '2.00',
            'total_days' => '29.00',
            'per_day_rate' => '903.23',
            'this_month_salary' => '26,193.67',
            'advance' => '2,000.00',
        ],
        'book_expense' => [
            'school_name' => 'Springfield Public School',
            'school_address' => '12 Park Avenue · 98765 43210',
            'receipt_title' => 'Book Expense Receipt',
            'expense_no' => 'BE-2026-0031',
            'expense_date' => '',
            'student_name' => 'Aarav Sharma',
            'admission_id' => 'ADM/2026/0142',
            'class_section' => 'Class 8 (A)',
            'items_table' => "Book / Item               Amount\nMathematics Textbook      450.00\nScience Lab Manual        320.00",
            'total_amount' => '770.00',
            'school_logo' => '',
        ],
    ];

    /** @return array{page_width_mm: float, page_height_mm: float, background_color: string, elements: array} */
    public static function starter(string $category): array
    {
        return match ($category) {
            'certificate' => self::certificateStarter(),
            'admit_card' => self::admitCardStarter(),
            'id_card' => self::idCardStarter(),
            'transport_card' => self::transportCardStarter(),
            'library_card' => self::libraryCardStarter(),
            'report_card' => self::reportCardStarter(),
            'exam_schedule' => self::examScheduleStarter(),
            'fee_receipt' => self::feeReceiptStarter(),
            'fee_due_receipt' => self::feeDueReceiptStarter(),
            'salary_slip' => self::salarySlipStarter(),
            'book_expense' => self::bookExpenseStarter(),
            default => throw new \InvalidArgumentException("Unknown template category [{$category}]."),
        };
    }

    private static function certificateStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 20, 'y' => 18, 'width' => 257, 'height' => 12, 'font_size' => 20, 'bold' => true, 'align' => 'center', 'text_color' => '#7c2d12'], $i++),
            self::el('certificate_title', ['content' => '{{certificate_title}}', 'x' => 20, 'y' => 32, 'width' => 257, 'height' => 9, 'font_size' => 13, 'italic' => true, 'align' => 'center'], $i++),
            self::freeform(['content' => 'This is proudly presented to', 'x' => 20, 'y' => 48, 'width' => 257, 'height' => 6, 'font_size' => 9, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('recipient_name', ['content' => '{{recipient_name}}', 'x' => 20, 'y' => 56, 'width' => 257, 'height' => 11, 'font_size' => 17, 'bold' => true, 'align' => 'center'], $i++),
            self::el('certificate_body', ['content' => '{{certificate_body}}', 'x' => 35, 'y' => 78, 'width' => 227, 'height' => 30, 'font_size' => 10.5, 'align' => 'center', 'line_height' => 1.6], $i++),
            self::el('reference_no', ['content' => 'Ref No: {{reference_no}}', 'x' => 20, 'y' => 12, 'width' => 90, 'height' => 6, 'font_size' => 8, 'text_color' => '#94a3b8'], $i++),
            self::el('issue_date', ['content' => 'Issue date: {{issue_date}}', 'x' => 187, 'y' => 12, 'width' => 90, 'height' => 6, 'font_size' => 8, 'align' => 'right', 'text_color' => '#94a3b8'], $i++),
            self::freeform(['type' => 'line', 'x' => 40, 'y' => 150, 'width' => 60, 'height' => 0, 'border_color' => '#1e293b', 'border_width_mm' => 0.3], $i++),
            self::freeform(['content' => 'Class Teacher', 'x' => 40, 'y' => 152, 'width' => 60, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::freeform(['type' => 'line', 'x' => 197, 'y' => 150, 'width' => 60, 'height' => 0, 'border_color' => '#1e293b', 'border_width_mm' => 0.3], $i++),
            self::freeform(['content' => 'Principal', 'x' => 197, 'y' => 152, 'width' => 60, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 297, 'page_height_mm' => 210, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function admitCardStarter(): array
    {
        // Canvas starter sized for A5 (client 2-up print). Signature stays within 210 mm height.
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 8, 'y' => 6, 'width' => 132, 'height' => 7, 'font_size' => 11, 'bold' => true, 'align' => 'center'], $i++),
            self::el('exam_title', ['content' => '{{exam_title}}', 'x' => 8, 'y' => 14, 'width' => 132, 'height' => 5, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('student_name', ['content' => 'Name: {{student_name}}', 'x' => 8, 'y' => 24, 'width' => 100, 'height' => 5.5, 'font_size' => 8.5, 'bold' => true], $i++),
            self::el('admission_id', ['content' => 'Admission ID: {{admission_id}}', 'x' => 8, 'y' => 31, 'width' => 100, 'height' => 5, 'font_size' => 7.5], $i++),
            self::el('class', ['content' => 'Class: {{class}} Section: {{section}}', 'x' => 8, 'y' => 37, 'width' => 100, 'height' => 5, 'font_size' => 7.5], $i++),
            self::el('exam_center', ['content' => 'Center: {{exam_center}}', 'x' => 8, 'y' => 43, 'width' => 100, 'height' => 5, 'font_size' => 7.5], $i++),
            self::freeform(['type' => 'rectangle', 'x' => 112, 'y' => 24, 'width' => 24, 'height' => 28, 'border_color' => '#cbd5e1', 'border_width_mm' => 0.3], $i++),
            self::freeform(['content' => 'Photo', 'x' => 112, 'y' => 35, 'width' => 24, 'height' => 5, 'font_size' => 7, 'align' => 'center', 'text_color' => '#94a3b8'], $i++),
            self::el('schedule_table', ['content' => '{{schedule_table}}', 'x' => 8, 'y' => 56, 'width' => 132, 'height' => 70, 'font_size' => 7, 'font_family' => 'Courier'], $i++),
            self::el('general_instructions', ['content' => '{{general_instructions}}', 'x' => 8, 'y' => 130, 'width' => 132, 'height' => 28, 'font_size' => 6.5, 'text_color' => '#64748b'], $i++),
            self::el('signature_line', ['content' => '{{signature_line}}', 'x' => 78, 'y' => 192, 'width' => 60, 'height' => 5, 'font_size' => 7, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 148, 'page_height_mm' => 210, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function idCardStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 5, 'y' => 4, 'width' => 76, 'height' => 6, 'font_size' => 7.5, 'bold' => true, 'align' => 'center'], $i++),
            self::el('card_title', ['content' => '{{card_title}}', 'x' => 5, 'y' => 10, 'width' => 76, 'height' => 5, 'font_size' => 6, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::freeform(['type' => 'ellipse', 'x' => 30, 'y' => 17, 'width' => 26, 'height' => 26, 'border_color' => '#cbd5e1', 'border_width_mm' => 0.3], $i++),
            self::el('holder_name', ['content' => '{{holder_name}}', 'x' => 5, 'y' => 45, 'width' => 76, 'height' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'center'], $i++),
            self::el('id_number', ['content' => '{{id_number}}', 'x' => 5, 'y' => 50, 'width' => 76, 'height' => 5, 'font_size' => 6.5, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 85.6, 'page_height_mm' => 54, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function transportCardStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 5, 'y' => 4, 'width' => 76, 'height' => 6, 'font_size' => 7.5, 'bold' => true, 'align' => 'center'], $i++),
            self::el('card_title', ['content' => '{{card_title}}', 'x' => 5, 'y' => 10, 'width' => 76, 'height' => 5, 'font_size' => 6, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('student_name', ['content' => '{{student_name}}', 'x' => 5, 'y' => 18, 'width' => 76, 'height' => 5.5, 'font_size' => 8, 'bold' => true, 'align' => 'center'], $i++),
            self::el('route_name', ['content' => 'Route: {{route_name}}', 'x' => 5, 'y' => 26, 'width' => 76, 'height' => 5, 'font_size' => 6.5, 'align' => 'center'], $i++),
            self::el('pickup_stop', ['content' => 'Stop: {{pickup_stop}}', 'x' => 5, 'y' => 31, 'width' => 76, 'height' => 5, 'font_size' => 6.5, 'align' => 'center'], $i++),
        ];

        return ['page_width_mm' => 85.6, 'page_height_mm' => 54, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function libraryCardStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 5, 'y' => 4, 'width' => 76, 'height' => 6, 'font_size' => 7.5, 'bold' => true, 'align' => 'center'], $i++),
            self::el('card_title', ['content' => '{{card_title}}', 'x' => 5, 'y' => 10, 'width' => 76, 'height' => 5, 'font_size' => 6, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('member_name', ['content' => '{{member_name}}', 'x' => 5, 'y' => 18, 'width' => 76, 'height' => 5.5, 'font_size' => 8, 'bold' => true, 'align' => 'center'], $i++),
            self::el('member_id', ['content' => '{{member_id}}', 'x' => 5, 'y' => 25, 'width' => 76, 'height' => 5, 'font_size' => 6.5, 'align' => 'center'], $i++),
            self::el('valid_until', ['content' => 'Valid until: {{valid_until}}', 'x' => 5, 'y' => 31, 'width' => 76, 'height' => 5, 'font_size' => 6, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 85.6, 'page_height_mm' => 54, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function reportCardStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 15, 'y' => 12, 'width' => 180, 'height' => 9, 'font_size' => 15, 'bold' => true, 'align' => 'center'], $i++),
            self::el('exam_title', ['content' => '{{exam_title}}', 'x' => 15, 'y' => 22, 'width' => 180, 'height' => 7, 'font_size' => 10, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('student_name', ['content' => 'Name: {{student_name}}', 'x' => 15, 'y' => 36, 'width' => 90, 'height' => 6, 'font_size' => 9.5], $i++),
            self::el('class_section', ['content' => 'Class: {{class_section}}', 'x' => 105, 'y' => 36, 'width' => 90, 'height' => 6, 'font_size' => 9.5], $i++),
            self::el('marks_table', ['content' => '{{marks_table}}', 'x' => 15, 'y' => 48, 'width' => 180, 'height' => 90, 'font_size' => 9, 'font_family' => 'Courier'], $i++),
            self::el('total_marks', ['content' => 'Total: {{total_marks}} ({{percentage}})', 'x' => 15, 'y' => 142, 'width' => 90, 'height' => 6, 'font_size' => 9.5, 'bold' => true], $i++),
            self::el('grade', ['content' => 'Grade: {{grade}} · Result: {{result}}', 'x' => 105, 'y' => 142, 'width' => 90, 'height' => 6, 'font_size' => 9.5, 'bold' => true], $i++),
            self::el('class_teacher_signature', ['content' => '{{class_teacher_signature}}', 'x' => 15, 'y' => 270, 'width' => 80, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('principal_signature', ['content' => '{{principal_signature}}', 'x' => 115, 'y' => 270, 'width' => 80, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 210, 'page_height_mm' => 297, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function examScheduleStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 15, 'y' => 10, 'width' => 200, 'height' => 9, 'font_size' => 15, 'bold' => true], $i++),
            self::el('schedule_title', ['content' => '{{schedule_title}}', 'x' => 15, 'y' => 22, 'width' => 267, 'height' => 8, 'font_size' => 12, 'bold' => true, 'align' => 'center'], $i++),
            self::el('exam_title', ['content' => '{{exam_title}}{{sittings_note}}', 'x' => 15, 'y' => 32, 'width' => 267, 'height' => 6, 'font_size' => 9, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('schedule_table', ['content' => '{{schedule_table}}', 'x' => 15, 'y' => 44, 'width' => 267, 'height' => 120, 'font_size' => 8, 'font_family' => 'Courier'], $i++),
            self::el('signature_line', ['content' => '{{signature_line}}', 'x' => 100, 'y' => 185, 'width' => 90, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('principal_signature', ['content' => '{{principal_signature}}', 'x' => 200, 'y' => 185, 'width' => 80, 'height' => 6, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 297, 'page_height_mm' => 210, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    /**
     * Mirrors a real printed fee-receipt slip: dual school seal + blue title band, a bordered
     * info card split into a wide left column (Student/Father/City/Fee-months) and a narrow
     * right column (Date/Adm No/Class/Section), the item table, and a running totals sidebar
     * (Total Fees, Back Dues, Late Fee, Adjustment, Grand Total, highlighted Paid Amount,
     * Balance Dues) — approximated with the builder's rectangle/line primitives rather than a
     * true per-cell grid, since the canvas has no native table element.
     */
    private static function feeReceiptStarter(): array
    {
        $i = 0;
        $elements = [
            // Header: dual seal, badge, school identity.
            self::el('school_logo', ['type' => 'image', 'content' => '', 'x' => 8, 'y' => 4, 'width' => 26, 'height' => 26], $i++),
            self::el('school_logo', ['type' => 'image', 'content' => '', 'x' => 176, 'y' => 4, 'width' => 26, 'height' => 26], $i++),
            self::el('receipt_title', ['content' => '{{receipt_title}}', 'x' => 90, 'y' => 2, 'width' => 30, 'height' => 7, 'font_size' => 8, 'bold' => true, 'align' => 'center', 'text_color' => '#ffffff', 'fill_color' => '#1e3a8a', 'border_radius_mm' => 1], $i++),
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 36, 'y' => 8, 'width' => 138, 'height' => 13, 'font_size' => 21, 'bold' => true, 'align' => 'center', 'text_color' => '#1d4ed8'], $i++),
            self::el('school_address', ['content' => 'Address : {{school_address}}', 'x' => 36, 'y' => 21, 'width' => 138, 'height' => 6, 'font_size' => 8.5, 'align' => 'center', 'text_color' => '#334155'], $i++),
            self::el('school_phone', ['content' => 'Contact No. {{school_phone}}', 'x' => 36, 'y' => 27, 'width' => 138, 'height' => 6, 'font_size' => 8.5, 'bold' => true, 'align' => 'center', 'text_color' => '#334155'], $i++),
            self::freeform(['type' => 'line', 'x' => 6, 'y' => 36, 'width' => 198, 'height' => 0, 'border_color' => '#1e293b', 'border_width_mm' => 0.4], $i++),

            // Outer card + column divider.
            self::freeform(['type' => 'rectangle', 'x' => 6, 'y' => 38, 'width' => 198, 'height' => 100, 'border_color' => '#1e293b', 'border_width_mm' => 0.35], $i++),
            self::freeform(['type' => 'rectangle', 'x' => 146, 'y' => 39, 'width' => 0.4, 'height' => 98, 'fill_color' => '#1e293b'], $i++),
            self::freeform(['type' => 'line', 'x' => 6, 'y' => 68, 'width' => 198, 'height' => 0, 'border_color' => '#1e293b', 'border_width_mm' => 0.25], $i++),

            // Left column detail rows.
            self::el('student_name', ['content' => 'Student: {{student_name}}', 'x' => 10, 'y' => 40, 'width' => 134, 'height' => 7, 'font_size' => 9, 'bold' => true], $i++),
            self::el('father_name', ['content' => 'Father: {{father_name}}', 'x' => 10, 'y' => 47, 'width' => 134, 'height' => 7, 'font_size' => 9], $i++),
            self::el('city', ['content' => 'City / Village: {{city}}', 'x' => 10, 'y' => 54, 'width' => 134, 'height' => 7, 'font_size' => 9], $i++),
            self::el('fee_for_months', ['content' => 'Fee For Month(s): {{fee_for_months}}', 'x' => 10, 'y' => 61, 'width' => 134, 'height' => 7, 'font_size' => 9], $i++),

            // Right column detail rows.
            self::el('receipt_date', ['content' => 'DATE: {{receipt_date}}', 'x' => 149, 'y' => 40, 'width' => 52, 'height' => 7, 'font_size' => 9, 'bold' => true], $i++),
            self::el('admission_id', ['content' => 'Adm. No. {{admission_id}}', 'x' => 149, 'y' => 47, 'width' => 52, 'height' => 7, 'font_size' => 9], $i++),
            self::el('class', ['content' => 'Class: {{class}}', 'x' => 149, 'y' => 54, 'width' => 52, 'height' => 7, 'font_size' => 9], $i++),
            self::el('section', ['content' => 'Section: {{section}}', 'x' => 149, 'y' => 61, 'width' => 52, 'height' => 7, 'font_size' => 9], $i++),

            // Item table (left, under the divider).
            self::el('items_table', ['content' => '{{items_table}}', 'x' => 10, 'y' => 70, 'width' => 134, 'height' => 34, 'font_size' => 9, 'font_family' => 'Courier'], $i++),

            // Totals sidebar (right column, from the divider down to the card bottom).
            self::freeform(['content' => 'Total Fees', 'x' => 149, 'y' => 70, 'width' => 32, 'height' => 6, 'font_size' => 8], $i++),
            self::el('total_fees', ['content' => '{{total_fees}}', 'x' => 179, 'y' => 70, 'width' => 23, 'height' => 6, 'font_size' => 8, 'align' => 'right'], $i++),
            self::freeform(['content' => '+ Back Dues', 'x' => 149, 'y' => 77, 'width' => 32, 'height' => 6, 'font_size' => 8], $i++),
            self::el('back_dues', ['content' => '{{back_dues}}', 'x' => 179, 'y' => 77, 'width' => 23, 'height' => 6, 'font_size' => 8, 'align' => 'right'], $i++),
            self::freeform(['content' => '+ Late Fee', 'x' => 149, 'y' => 84, 'width' => 32, 'height' => 6, 'font_size' => 8], $i++),
            self::el('late_fee', ['content' => '{{late_fee}}', 'x' => 179, 'y' => 84, 'width' => 23, 'height' => 6, 'font_size' => 8, 'align' => 'right'], $i++),
            self::freeform(['content' => '- Adjustment', 'x' => 149, 'y' => 91, 'width' => 32, 'height' => 6, 'font_size' => 8], $i++),
            self::el('adjustment', ['content' => '{{adjustment}}', 'x' => 179, 'y' => 91, 'width' => 23, 'height' => 6, 'font_size' => 8, 'align' => 'right'], $i++),
            self::freeform(['type' => 'line', 'x' => 148, 'y' => 98, 'width' => 54, 'height' => 0, 'border_color' => '#94a3b8', 'border_width_mm' => 0.2], $i++),
            self::freeform(['content' => 'GRAND TOTAL', 'x' => 149, 'y' => 99, 'width' => 32, 'height' => 6, 'font_size' => 8, 'bold' => true], $i++),
            self::el('grand_total', ['content' => '{{grand_total}}', 'x' => 179, 'y' => 99, 'width' => 23, 'height' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'right'], $i++),
            self::freeform(['type' => 'rectangle', 'x' => 147, 'y' => 106, 'width' => 55, 'height' => 8, 'fill_color' => '#16a34a'], $i++),
            self::freeform(['content' => 'Paid Amount', 'x' => 149, 'y' => 107.5, 'width' => 32, 'height' => 6, 'font_size' => 8.5, 'bold' => true, 'text_color' => '#ffffff'], $i++),
            self::el('paid_amount', ['content' => '{{paid_amount}}', 'x' => 179, 'y' => 107.5, 'width' => 23, 'height' => 6, 'font_size' => 8.5, 'bold' => true, 'align' => 'right', 'text_color' => '#ffffff'], $i++),
            self::freeform(['content' => 'Total Balance Dues', 'x' => 149, 'y' => 117, 'width' => 32, 'height' => 6, 'font_size' => 8], $i++),
            self::el('balance_dues', ['content' => '{{balance_dues}}', 'x' => 179, 'y' => 117, 'width' => 23, 'height' => 6, 'font_size' => 8, 'align' => 'right'], $i++),

            // Left column footer: amount in words, remarks, signature, payment mode.
            self::el('amount_in_words', ['content' => 'Amount In Words: {{amount_in_words}}', 'x' => 10, 'y' => 106, 'width' => 134, 'height' => 6, 'font_size' => 8, 'text_color' => '#64748b'], $i++),
            self::el('remarks', ['content' => 'Remarks: {{remarks}}', 'x' => 10, 'y' => 114, 'width' => 134, 'height' => 6, 'font_size' => 8, 'text_color' => '#64748b'], $i++),
            self::el('received_by', ['content' => 'Received by: {{received_by}}', 'x' => 10, 'y' => 124, 'width' => 70, 'height' => 6, 'font_size' => 8], $i++),
            self::freeform(['content' => 'Accountant Signature', 'x' => 84, 'y' => 124, 'width' => 60, 'height' => 6, 'font_size' => 8, 'align' => 'right', 'text_color' => '#64748b'], $i++),
            self::el('payment_mode', ['content' => 'Mode Of Payment: {{payment_mode}}', 'x' => 10, 'y' => 132, 'width' => 134, 'height' => 6, 'font_size' => 8.5, 'bold' => true], $i++),
        ];

        return ['page_width_mm' => 210, 'page_height_mm' => 148, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function feeDueReceiptStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 15, 'y' => 12, 'width' => 180, 'height' => 9, 'font_size' => 15, 'bold' => true, 'align' => 'center'], $i++),
            self::el('receipt_title', ['content' => '{{receipt_title}}', 'x' => 15, 'y' => 22, 'width' => 180, 'height' => 7, 'font_size' => 10, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('student_name', ['content' => 'Student: {{student_name}} ({{admission_id}})', 'x' => 15, 'y' => 36, 'width' => 180, 'height' => 6, 'font_size' => 9.5], $i++),
            self::el('due_table', ['content' => '{{due_table}}', 'x' => 15, 'y' => 48, 'width' => 180, 'height' => 60, 'font_size' => 9, 'font_family' => 'Courier'], $i++),
            self::el('total_due', ['content' => 'Total Due: Rs. {{total_due}}', 'x' => 15, 'y' => 112, 'width' => 90, 'height' => 6, 'font_size' => 10, 'bold' => true, 'text_color' => '#b91c1c'], $i++),
            self::el('due_date', ['content' => 'Due date: {{due_date}}', 'x' => 105, 'y' => 112, 'width' => 90, 'height' => 6, 'font_size' => 9, 'align' => 'right'], $i++),
            self::el('remarks', ['content' => '{{remarks}}', 'x' => 15, 'y' => 122, 'width' => 180, 'height' => 8, 'font_size' => 8, 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 210, 'page_height_mm' => 148, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function salarySlipStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 15, 'y' => 12, 'width' => 180, 'height' => 9, 'font_size' => 15, 'bold' => true, 'align' => 'center'], $i++),
            self::el('school_address', ['content' => '{{school_address}}', 'x' => 15, 'y' => 21, 'width' => 180, 'height' => 5, 'font_size' => 8, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('slip_title', ['content' => '{{slip_title}}', 'x' => 15, 'y' => 30, 'width' => 180, 'height' => 7, 'font_size' => 11, 'bold' => true, 'align' => 'center'], $i++),
            self::el('slip_no', ['content' => 'Slip No: {{slip_no}}', 'x' => 15, 'y' => 42, 'width' => 90, 'height' => 6, 'font_size' => 9], $i++),
            self::el('period', ['content' => 'Period: {{period}}', 'x' => 105, 'y' => 42, 'width' => 90, 'height' => 6, 'font_size' => 9, 'align' => 'right'], $i++),
            self::el('employee_name', ['content' => '{{employee_name}} ({{employee_type}})', 'x' => 15, 'y' => 52, 'width' => 180, 'height' => 6, 'font_size' => 10, 'bold' => true], $i++),
            self::el('earnings_table', ['content' => '{{earnings_table}}', 'x' => 15, 'y' => 64, 'width' => 180, 'height' => 45, 'font_size' => 9, 'font_family' => 'Courier'], $i++),
            self::el('deductions_table', ['content' => '{{deductions_table}}', 'x' => 15, 'y' => 114, 'width' => 180, 'height' => 35, 'font_size' => 9, 'font_family' => 'Courier'], $i++),
            self::el('net_salary', ['content' => 'Net salary: Rs. {{net_salary}}', 'x' => 15, 'y' => 155, 'width' => 180, 'height' => 7, 'font_size' => 11, 'bold' => true], $i++),
            self::el('status', ['content' => 'Status: {{status}} · {{payment_mode}}', 'x' => 15, 'y' => 165, 'width' => 180, 'height' => 6, 'font_size' => 8, 'text_color' => '#64748b'], $i++),
        ];

        return ['page_width_mm' => 210, 'page_height_mm' => 297, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function bookExpenseStarter(): array
    {
        $i = 0;
        $elements = [
            self::el('school_name', ['content' => '{{school_name}}', 'x' => 15, 'y' => 12, 'width' => 180, 'height' => 9, 'font_size' => 15, 'bold' => true, 'align' => 'center'], $i++),
            self::el('receipt_title', ['content' => '{{receipt_title}}', 'x' => 15, 'y' => 24, 'width' => 180, 'height' => 7, 'font_size' => 10, 'align' => 'center', 'text_color' => '#64748b'], $i++),
            self::el('expense_no', ['content' => 'No: {{expense_no}}', 'x' => 15, 'y' => 36, 'width' => 90, 'height' => 6, 'font_size' => 9], $i++),
            self::el('expense_date', ['content' => 'Date: {{expense_date}}', 'x' => 105, 'y' => 36, 'width' => 90, 'height' => 6, 'font_size' => 9, 'align' => 'right'], $i++),
            self::el('student_name', ['content' => 'Student: {{student_name}} ({{admission_id}})', 'x' => 15, 'y' => 46, 'width' => 180, 'height' => 6, 'font_size' => 9.5], $i++),
            self::el('items_table', ['content' => '{{items_table}}', 'x' => 15, 'y' => 58, 'width' => 180, 'height' => 50, 'font_size' => 9, 'font_family' => 'Courier'], $i++),
            self::el('total_amount', ['content' => 'Total: Rs. {{total_amount}}', 'x' => 15, 'y' => 114, 'width' => 180, 'height' => 7, 'font_size' => 11, 'bold' => true], $i++),
        ];

        return ['page_width_mm' => 210, 'page_height_mm' => 148, 'background_color' => '#ffffff', 'elements' => $elements];
    }

    private static function el(string $fieldKey, array $overrides, int $zIndex): array
    {
        return self::base(array_merge(['field_key' => $fieldKey, 'locked' => self::isLocked($fieldKey)], $overrides), $zIndex);
    }

    private static function freeform(array $overrides, int $zIndex): array
    {
        return self::base($overrides, $zIndex);
    }

    private static function isLocked(string $fieldKey): bool
    {
        // Locked-ness is looked up from FIELDS by whichever category is building right now;
        // callers only invoke el() from within a single category's *Starter() method, so the
        // simplest correct source of truth is to search every category's field list for this key.
        foreach (self::FIELDS as $fields) {
            foreach ($fields as [$key, , $locked]) {
                if ($key === $fieldKey) {
                    return $locked;
                }
            }
        }

        return false;
    }

    private static function base(array $overrides, int $zIndex): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'text',
            'field_key' => null,
            'locked' => false,
            'hidden' => false,
            'label_prefix' => null,
            'content' => '',
            'x' => 10, 'y' => 10, 'width' => 60, 'height' => 8, 'rotation' => 0,
            'font_family' => 'Helvetica', 'font_size' => 10, 'bold' => false, 'italic' => false,
            'align' => 'left', 'line_height' => 1.25, 'text_color' => '#1e293b',
            'fill_color' => null, 'border_color' => null, 'border_width_mm' => 0, 'border_radius_mm' => 0,
            'padding_mm' => 1, 'opacity' => 100, 'image_path' => null,
        ], $overrides, ['z_index' => $zIndex]);
    }
}
