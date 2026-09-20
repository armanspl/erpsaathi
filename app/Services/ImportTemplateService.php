<?php

namespace App\Services;

use App\Models\AcademicCalendarEntry;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds sample Excel templates for every ERP import workflow so users can see
 * the exact columns / sheet layout required before uploading.
 */
class ImportTemplateService
{
    /** @var list<string> */
    public const TYPES = [
        'global-workbook',
        'student-pen',
        'attendance',
        'exam-marks',
        'academic-calendar',
        'employee-master',
        'salary-monthly',
        'exam-schedule',
    ];

    public function download(string $type): StreamedResponse
    {
        $type = strtolower(trim($type));
        if (! in_array($type, self::TYPES, true)) {
            abort(404, 'Unknown import template type.');
        }

        $spreadsheet = match ($type) {
            'global-workbook' => $this->globalWorkbook(),
            'student-pen' => $this->studentPen(),
            'attendance' => $this->attendance(),
            'exam-marks' => $this->examMarks(),
            'academic-calendar' => $this->academicCalendar(),
            'employee-master' => $this->employeeMaster(),
            'salary-monthly' => $this->salaryMonthly(),
            'exam-schedule' => $this->examSchedule(),
        };

        $filename = "import-template-{$type}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function globalWorkbook(): Spreadsheet
    {
        $ss = new Spreadsheet();

        // --- Student Master (required columns + sample) ---
        $master = $ss->getActiveSheet();
        $master->setTitle('Student Master 26-27');
        $masterHeaders = [
            'SESSION', 'Adm No.', 'Student Type', 'Aadhaar No.', 'Name', 'Mother Name', 'Father Name',
            'Address', 'Mobile', 'Class', 'Section', 'Roll', 'DOB', 'Gender', 'Bld Grp', 'Adm Date',
            'Stoppage', 'Vehicle', 'Adm Type', 'Transport', 'Social Category', 'Hostel', 'Minority Group',
            'BPL Beneficiary', 'Belongs to EWS/Disadvantaged Group?', 'CWSN', 'CLSL', 'Name as per Aadhaar',
            'Child is Indian National?', 'Guardian Name (Optional)', 'Alternate Mobile Number (Optional)',
            'Email ID (Student/Parent/Guardian) (Optional)', 'Mother Tongue', 'Pincode', 'Student State Code',
            'Student PEN', 'UDISE', 'Class Admitted',
        ];
        $master->fromArray($masterHeaders, null, 'A1');
        $this->styleHeaderRow($master, 'A1:'.Coordinate::stringFromColumnIndex(count($masterHeaders)).'1');
        $master->fromArray([
            '2026-27', '16701', 'Regular', '123456789012', 'SAMPLE STUDENT', 'Mother Name', 'Father Name',
            'Sample Address', '9876543210', 'Nur', 'A', '1', '2018-04-15', 'Male', 'B+', '2026-04-01',
            'Main Gate', 'Bus-1', 'New', 'Yes', 'General', 'No', 'No',
            'No', 'No', 'No', 'No', 'SAMPLE STUDENT',
            'Yes', '', '', '', 'Hindi', '800001', '10',
            '', 'In UDISE', 'Nursery',
        ], null, 'A2');

        // --- INCOME ---
        $income = $ss->createSheet();
        $income->setTitle('INCOME');
        $incomeHeaders = ['Year', 'Month', 'Date', 'Adm No', 'Name', 'Address', 'Class', 'Head', 'Fee', 'Session', 'Receipt No', 'Mode', 'Remarks'];
        $income->fromArray($incomeHeaders, null, 'A1');
        $this->styleHeaderRow($income, 'A1:M1');
        $income->fromArray([
            '2026', 'April', '2026-04-05', '16701', 'SAMPLE STUDENT', 'Sample Address', 'Nursery', 'TUI', '1500', '2026-27', 'R-1001', 'Cash', 'APR',
        ], null, 'A2');
        $income->fromArray([
            '2026', 'April', '2026-04-05', '', 'Misc Donor', '', '', 'Donation', '500', '2026-27', 'R-1002', 'UPI', 'Misc income (no Adm No)',
        ], null, 'A3');

        // --- EXPENSES ---
        $expenses = $ss->createSheet();
        $expenses->setTitle('EXPENSES');
        $expenseHeaders = ['Year', 'Month', 'Date', 'Description', 'Part-1', 'Part-2', 'Part-3', 'Amount', 'Remarks', 'Receipt No'];
        $expenses->fromArray($expenseHeaders, null, 'A1');
        $this->styleHeaderRow($expenses, 'A1:J1');
        $expenses->fromArray([
            '2026', 'April', '2026-04-10', 'Office stationery', 'Admin', 'Stationery', '', '2500', 'Sample', 'V-101',
        ], null, 'A2');

        // --- TRANSPORT ---
        $transport = $ss->createSheet();
        $transport->setTitle('TRANSPORT-26');
        $transport->fromArray(['S.NO', 'STOPPAGE', 'FARE'], null, 'A1');
        $this->styleHeaderRow($transport, 'A1:C1');
        $transport->fromArray([1, 'Main Gate', 800], null, 'A2');
        $transport->fromArray([2, 'City Center', 1200], null, 'A3');

        app(SalaryBankWorkbookService::class)->appendSampleSheet($ss);

        return $ss;
    }

    private function studentPen(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Students Details');
        $sheet->setCellValue('A1', 'List of All Students - Sample School (UDISE export shape)');
        $sheet->fromArray(['ENRL #', 'Class', 'Section', 'Name', 'Student PEN'], null, 'A2');
        $this->styleHeaderRow($sheet, 'A2:E2');
        $sheet->fromArray(['16701', 'Nursery', 'A', 'SAMPLE STUDENT', '1234567890123456'], null, 'A3');
        $sheet->fromArray(['16702', 'LKG', 'B', 'ANOTHER STUDENT', '9876543210987654'], null, 'A4');

        return $ss;
    }

    private function attendance(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('NUR');
        $sheet->setCellValue('A1', 'Attendence Report 2026-27');

        // Row 2 = working days per month column (D–J, M–R)
        $monthLabels = ['MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'TOT1', '%1', 'OCT', 'NOV', 'DEC', 'JAN', 'FEB', 'MAR2', 'TOT2', '%2'];
        $workingDays = [22, 20, 18, 22, 23, 21, 20, '', '', 22, 20, 18, 22, 20, 21, '', ''];
        $sheet->setCellValue('A2', '');
        $sheet->setCellValue('B2', '');
        $sheet->setCellValue('C2', 'Working Days →');
        $col = 4; // D
        foreach ($workingDays as $days) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'2', $days === '' ? null : $days);
            $col++;
        }

        $sheet->fromArray(array_merge(['ENROL', 'NAME', 'ROLL'], $monthLabels), null, 'A3');
        $this->styleHeaderRow($sheet, 'A3:T3');
        $sheet->fromArray(array_merge(
            ['16701', 'SAMPLE STUDENT', '1'],
            [20, 18, 16, 20, 21, 19, 18, '', '', 20, 18, 16, 20, 18, 19, '', '']
        ), null, 'A4');

        // Extra empty class sheets so users see naming convention
        foreach (['LKG', 'UKG', '1st'] as $name) {
            $extra = $ss->createSheet();
            $extra->setTitle($name);
            $extra->setCellValue('A1', 'Attendence Report 2026-27');
            $extra->fromArray(array_merge(['ENROL', 'NAME', 'ROLL'], $monthLabels), null, 'A3');
            $this->styleHeaderRow($extra, 'A3:T3');
        }

        return $ss;
    }

    /**
     * One workbook covering every class: Adm. No./Name/Class/Roll No + a per-subject block
     * of PT1/NB1/SEA1/TOT(20)/HY(80)/TOT(100) columns — the client's current marksheet shape.
     * TOT columns are computed sums and are ignored on import; only PT1/NB1/SEA1/HY are read.
     */
    private function examMarks(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('MARKSHEET');

        $subjects = ['ENG', 'HINDI', 'MATHS', 'EVS'];
        $components = ['PT1 (10)', 'NB1 (05)', 'SEA1 (05)', 'TOT (20)', 'HY (80)', 'TOT (100)'];

        $sheet->setCellValue('A1', 'Adm. No.');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Class');
        $sheet->setCellValue('D1', 'Roll No');

        $col = 5; // E
        foreach ($subjects as $subject) {
            $startLetter = Coordinate::stringFromColumnIndex($col);
            $endLetter = Coordinate::stringFromColumnIndex($col + count($components) - 1);
            $sheet->setCellValue($startLetter.'1', $subject);
            $sheet->mergeCells("{$startLetter}1:{$endLetter}1");
            $c = $col;
            foreach ($components as $label) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c).'2', $label);
                $c++;
            }
            $col += count($components);
        }
        $lastCol = Coordinate::stringFromColumnIndex($col - 1);
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');
        $this->styleHeaderRow($sheet, "A1:{$lastCol}2");

        $samples = [
            ['16701', 'SAMPLE STUDENT ONE', 'Nursery', 1],
            ['16702', 'SAMPLE STUDENT TWO', '1', 1],
        ];
        $row = 3;
        foreach ($samples as $s) {
            $sheet->fromArray($s, null, 'A'.$row);
            $row++;
        }

        $sheet->setCellValue('A'.($row + 1), 'Fill PT1 / NB1 / SEA1 / HY per subject for every class — TOT columns are calculated and ignored on import.');
        $sheet->setCellValue('A'.($row + 2), 'Term-2 workbooks use the same layout with ANNU (80) in place of HY (80).');

        return $ss;
    }

    private function academicCalendar(): Spreadsheet
    {
        $year = (int) now()->year;
        $entries = [
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_HOLIDAY,
                'title' => 'Summer Vacation',
                'month_label' => 'MAY-JUN',
                'date_label' => '15 May – 15 Jun',
                'start_date' => "{$year}-05-15",
                'end_date' => "{$year}-06-15",
                'sort_order' => 0,
            ],
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_HOLIDAY,
                'title' => 'Independence Day',
                'month_label' => 'AUG',
                'date_label' => '15 Aug',
                'start_date' => "{$year}-08-15",
                'end_date' => "{$year}-08-15",
                'sort_order' => 1,
            ],
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_EXAMINATION,
                'title' => 'PT-1',
                'month_label' => null,
                'date_label' => '10–15 Jul',
                'start_date' => "{$year}-07-10",
                'end_date' => "{$year}-07-15",
                'sort_order' => 0,
            ],
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_EXAMINATION,
                'title' => 'Half Yearly',
                'month_label' => null,
                'date_label' => '01–10 Oct',
                'start_date' => "{$year}-10-01",
                'end_date' => "{$year}-10-10",
                'sort_order' => 1,
            ],
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_PROGRAMME,
                'title' => 'Annual Day',
                'month_label' => 'DEC',
                'date_label' => '20 Dec',
                'start_date' => "{$year}-12-20",
                'end_date' => "{$year}-12-20",
                'sort_order' => 0,
            ],
            (object) [
                'category' => AcademicCalendarEntry::CATEGORY_DEADLINE,
                'title' => 'Fee payment last date',
                'month_label' => 'HY',
                'date_label' => '30 Sep',
                'start_date' => "{$year}-09-30",
                'end_date' => "{$year}-09-30",
                'sort_order' => 0,
            ],
        ];

        $title = sprintf('ACADEMIC CALENDER %d-%s AT A GLANCE', $year, substr((string) ($year + 1), -2));

        return app(AcademicCalendarWorkbookService::class)->buildSpreadsheet($entries, $title, $year, $year + 1);
    }

    private function employeeMaster(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $salary = $ss->getActiveSheet();
        $salary->setTitle('SALARY DETAILS');
        $salaryHeaders = ['EMP_CODE', 'NAME', 'POST', 'SUBJECT', 'SECTION', 'STATUS', 'BASIC SALARY AT JOINING', 'BASIC SALARY PRESENT'];
        $salary->fromArray($salaryHeaders, null, 'A1');
        $this->styleHeaderRow($salary, 'A1:H1');
        $salary->fromArray(['T-1001', 'SAMPLE TEACHER', 'ASST TEACHER', 'ENG', 'A', 'Active', 18000, 22000], null, 'A2');
        $salary->fromArray(['S-2001', 'SAMPLE STAFF', 'OFFICE ASST', '', '', 'Active', 12000, 14000], null, 'A3');
        $salary->fromArray(['D-3001', 'SAMPLE DRIVER', 'DRIVER', '', '', 'Active', 10000, 11000], null, 'A4');

        $staff = $ss->createSheet();
        $staff->setTitle('STAFF DETAILS');
        $staff->setCellValue('A1', 'Staff bio-data (header must be on row 3)');
        $staff->setCellValue('A2', '');
        $staffHeaders = ['EMPL_CODE', 'NAME', 'DOB', 'CATEG', 'HS YEAR', 'INTER YEAR', 'GRAD YEAR', 'PHONE', 'JOIN DATE', 'ADDRESS', 'BASIC SALARY', 'REMARKS'];
        $staff->fromArray($staffHeaders, null, 'A3');
        $this->styleHeaderRow($staff, 'A3:L3');
        $staff->fromArray(['T-1001', 'SAMPLE TEACHER', '1990-05-12', 'GEN', '2006', '2008', '2011', '9876500001', '2018-04-01', 'Sample Address', 22000, ''], null, 'A4');
        $staff->fromArray(['S-2001', 'SAMPLE STAFF', '1988-08-20', 'OBC', '2004', '2006', '', '9876500002', '2019-06-01', 'Sample Address', 14000, ''], null, 'A5');
        $staff->fromArray(['D-3001', 'SAMPLE DRIVER', '1985-01-10', 'GEN', '', '', '', '9876500003', '2020-01-15', 'Sample Address', 11000, ''], null, 'A6');

        return $ss;
    }

    private function salaryMonthly(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('JULY-2026');

        // Rows 1–3 unused padding; row 4 = title + days; row 5 = headers; row 6+ = data
        $sheet->setCellValue('A4', 'SALARY PAYMENT DETAILS JULY-2026');
        $sheet->setCellValue('J4', 31); // Days in Month
        $headers = ['EMPL_CODE', 'Name', 'Basic Salary', 'Days in Month', 'Absent', 'Present', 'CL', 'ADV'];
        $sheet->fromArray($headers, null, 'A5');
        $this->styleHeaderRow($sheet, 'A5:H5');
        $sheet->fromArray(['T-1001', 'SAMPLE TEACHER', 22000, 31, 0, 29, 2, 0], null, 'A6');
        $sheet->fromArray(['S-2001', 'SAMPLE STAFF', 14000, 31, 1, 28, 2, 500], null, 'A7');

        $designation = $ss->createSheet();
        $designation->setTitle('STAFF DETAIL SALARY');
        // Positional: A=EMPL_CODE, B=NAME, C=DESIGNATION, D=GRADE, E=STATUS (no header row)
        $designation->fromArray(['T-1001', 'SAMPLE TEACHER', 'ASST TEACHER', 'GRADE I', 'Active'], null, 'A1');
        $designation->fromArray(['S-2001', 'SAMPLE STAFF', 'OFFICE ASST', 'GRADE III', 'Active'], null, 'A2');

        return $ss;
    }

    private function examSchedule(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Schedule');
        $headers = ['Date', 'Sitting', 'NUR', 'LKG', 'UKG', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:M1');
        $sheet->fromArray(['2026-09-15', '1st', 'ENG', 'ENG', 'ENG', 'ENG', 'HIN', 'MATH', 'SCI', 'SST', 'ENG', 'MATH', 'SCI'], null, 'A2');
        $sheet->fromArray(['2026-09-15', '2nd', 'MATH', 'MATH', 'MATH', 'HIN', 'ENG', 'ENG', 'MATH', 'ENG', 'HIN', 'SCI', 'MATH'], null, 'A3');
        $sheet->fromArray(['2026-09-16', '1st', 'EVS', 'EVS', 'EVS', 'MATH', 'MATH', 'HIN', 'ENG', 'MATH', 'SCI', 'ENG', 'ENG'], null, 'A4');

        return $ss;
    }

    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');
    }
}
