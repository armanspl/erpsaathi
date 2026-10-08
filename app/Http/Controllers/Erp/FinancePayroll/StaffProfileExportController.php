<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Staff;
use App\Models\Teacher;
use App\Support\EmployeeCustomFields;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports Teacher/Staff/Driver profiles in the same shape as the school's
 * "SALARY DETAILS 2026-27.xlsx" — a `SALARY DETAILS` sheet (grouped under GRADE I / GRADE III /
 * TRANSPORT ... label rows) and a `STAFF DETAILS` bio-data sheet with its header on row 3 — plus
 * PHONE and EMAIL columns. The file can be edited and uploaded again through the Staff Profile
 * import (EmployeeMasterImportController), which matches everyone back by EMP_CODE.
 */
class StaffProfileExportController extends Controller
{
    private const SALARY_HEADERS = [
        'EMP_CODE', 'NAME', 'POST', 'SUBJECT', 'SECTION', 'STATUS',
        'BASIC SALARY AT THE TIME OF JOINING', 'BASIC SALARY PRESENT', 'PHONE', 'EMAIL',
    ];

    private const STAFF_HEADERS = [
        'S.NO', 'EMPL_CODE', 'NAME', 'POST', 'DOB', 'CATEG', 'HS YEAR', 'INTER YEAR', 'GRAD YEAR',
        'PHONE', 'EMAIL', 'JOIN DATE', 'ADDRESS', 'BASIC SALARY', 'REMARKS', 'STATUS',
    ];

    /** Group label used when a profile has no "Grade" (created by hand, not by import). */
    private const DEFAULT_GROUPS = [
        'teacher' => 'TEACHING STAFF',
        'staff' => 'NON TEACHING STAFF',
        'driver' => 'TRANSPORT',
    ];

    private const GROUP_ORDER = [
        'GRADE I' => 1, 'GRADE II' => 2, 'GRADE III' => 3, 'GRADE IV' => 4, 'GRADE V' => 5,
        'TEACHING STAFF' => 6, 'NON TEACHING STAFF' => 7, 'TRANSPORT' => 9,
    ];

    private const TYPE_ORDER = ['teacher' => 0, 'staff' => 1, 'driver' => 2];

    public function export(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|in:all,teacher,staff,driver',
            'status' => 'nullable|in:all,active,inactive',
        ]);
        $type = $data['type'] ?? 'all';
        $status = $data['status'] ?? 'all';

        $people = $this->collect($type, $status);

        $spreadsheet = new Spreadsheet();
        $this->writeSalarySheet($spreadsheet->getActiveSheet(), $people);
        $this->writeStaffSheet($spreadsheet->createSheet(), $people);
        $spreadsheet->setActiveSheetIndex(0);

        $suffix = $type === 'all' ? '' : "-{$type}s";

        return response()->streamDownload(function () use ($spreadsheet) {
            \App\Support\ExcelBorders::applyThinGrid($spreadsheet);
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'staff-profiles'.$suffix.'-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return list<array<string, mixed>> one flat record per employee, sorted for printing */
    private function collect(string $type, string $status): array
    {
        $sources = [
            'teacher' => fn () => Teacher::with('subjects:id,name')->get(),
            'staff' => fn () => Staff::query()->get(),
            'driver' => fn () => Driver::query()->get(),
        ];

        $people = [];
        foreach ($sources as $t => $load) {
            if ($type !== 'all' && $type !== $t) {
                continue;
            }
            foreach ($load() as $model) {
                if ($status !== 'all' && $model->status !== $status) {
                    continue;
                }
                $people[] = $this->record($t, $model);
            }
        }

        usort($people, function ($a, $b) {
            return [$this->groupRank($a['group']), $a['group'], self::TYPE_ORDER[$a['type']], $a['code']]
                <=> [$this->groupRank($b['group']), $b['group'], self::TYPE_ORDER[$b['type']], $b['code']];
        });

        return $people;
    }

    /** @return array<string, mixed> */
    private function record(string $type, $model): array
    {
        $fields = [];
        foreach (EmployeeCustomFields::normalize($model->custom_field_values) as $f) {
            $fields[mb_strtolower($f['label'])] = $f['value'];
        }
        $field = fn (string $label) => $fields[mb_strtolower($label)] ?? '';

        $post = $field('Designation');
        if ($post === '') {
            $post = match ($type) {
                'teacher' => 'TEACHER',
                'driver' => 'DRIVER',
                default => trim((string) $model->department) !== '' ? mb_strtoupper($model->department) : 'STAFF',
            };
        }

        $subject = $field('Subject');
        if ($subject === '' && $type === 'teacher') {
            $subject = mb_strtoupper($model->subjects->pluck('name')->implode(', '));
        }

        $grade = mb_strtoupper($field('Grade'));
        $joining = $this->number($field('Basic Salary at Joining'));
        $salary = $model->salary !== null ? (float) $model->salary : null;

        return [
            'type' => $type,
            'group' => $grade !== '' ? $grade : self::DEFAULT_GROUPS[$type],
            'code' => (string) $model->employee_id,
            'name' => (string) $model->name,
            'post' => $post,
            'subject' => $subject,
            'section' => $field('Section'),
            'status' => $model->status === 'inactive' ? 'INACTIVE' : 'ACTIVE',
            'joining' => $joining,
            'salary' => $salary,
            'phone' => (string) ($model->phone ?? ''),
            'email' => (string) ($model->email ?? ''),
            'dob' => $field('Date of Birth'),
            'categ' => $field('Category'),
            'hs_year' => $field('HS Year'),
            'inter_year' => $field('Inter Year'),
            'grad_year' => $field('Grad Year'),
            'join_date' => $field('Join Date'),
            'address' => $field('Address'),
            'remarks' => $field('Remarks'),
        ];
    }

    /** @param  list<array<string, mixed>>  $people */
    private function writeSalarySheet(Worksheet $sheet, array $people): void
    {
        $sheet->setTitle('SALARY DETAILS');
        $sheet->fromArray(self::SALARY_HEADERS, null, 'A1');
        $this->styleHeader($sheet, 'A1:J1');

        $row = 2;
        $group = null;
        foreach ($people as $p) {
            if ($p['group'] !== $group) {
                if ($group !== null) {
                    $row++; // blank spacer row between groups, like the school's file
                }
                $group = $p['group'];
                $sheet->setCellValue("A{$row}", $group);
                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                ]);
                $row++;
            }

            $this->setCode($sheet, "A{$row}", $p['code']);
            $sheet->setCellValue("B{$row}", $p['name']);
            $sheet->setCellValue("C{$row}", $p['post']);
            $sheet->setCellValue("D{$row}", $p['subject']);
            $sheet->setCellValue("E{$row}", $p['section']);
            $sheet->setCellValue("F{$row}", $p['status']);
            if ($p['joining'] !== null) {
                $sheet->setCellValue("G{$row}", $p['joining']);
            }
            if ($p['salary'] !== null) {
                $sheet->setCellValue("H{$row}", $p['salary']);
            }
            $sheet->setCellValueExplicit("I{$row}", $p['phone'], DataType::TYPE_STRING);
            $sheet->setCellValue("J{$row}", $p['email']);
            $row++;
        }

        $sheet->getStyle("G2:H{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->colorStatus($sheet, 'F', 2, $row);
        $this->autoWidth($sheet, 'J');
        $sheet->freezePane('A2');
    }

    /** @param  list<array<string, mixed>>  $people */
    private function writeStaffSheet(Worksheet $sheet, array $people): void
    {
        $sheet->setTitle('STAFF DETAILS');
        $sheet->setCellValue('A1', 'STAFF DETAILS');
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->fromArray(self::STAFF_HEADERS, null, 'A3');
        $this->styleHeader($sheet, 'A3:P3');

        $row = 4;
        foreach ($people as $i => $p) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $this->setCode($sheet, "B{$row}", $p['code']);
            $sheet->setCellValue("C{$row}", $p['name']);
            $sheet->setCellValue("D{$row}", $p['post']);
            $sheet->setCellValueExplicit("E{$row}", $p['dob'], DataType::TYPE_STRING);
            $sheet->setCellValue("F{$row}", $p['categ']);
            $sheet->setCellValue("G{$row}", $p['hs_year']);
            $sheet->setCellValue("H{$row}", $p['inter_year']);
            $sheet->setCellValue("I{$row}", $p['grad_year']);
            $sheet->setCellValueExplicit("J{$row}", $p['phone'], DataType::TYPE_STRING);
            $sheet->setCellValue("K{$row}", $p['email']);
            $sheet->setCellValueExplicit("L{$row}", $p['join_date'], DataType::TYPE_STRING);
            $sheet->setCellValue("M{$row}", $p['address']);
            $basic = $p['joining'] ?? $p['salary'];
            if ($basic !== null) {
                $sheet->setCellValue("N{$row}", $basic);
            }
            $sheet->setCellValue("O{$row}", $p['remarks']);
            $sheet->setCellValue("P{$row}", $p['status']);
            $row++;
        }

        $sheet->getStyle("N4:N{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->colorStatus($sheet, 'P', 4, $row);
        $this->autoWidth($sheet, 'P');
        $sheet->freezePane('A4');
    }

    /** ACTIVE in green, INACTIVE in red, so left staff stand out. */
    private function colorStatus(Worksheet $sheet, string $col, int $from, int $to): void
    {
        for ($r = $from; $r < $to; $r++) {
            $value = (string) $sheet->getCell("{$col}{$r}")->getValue();
            if ($value === 'ACTIVE' || $value === 'INACTIVE') {
                $sheet->getStyle("{$col}{$r}")->getFont()->setBold(true)->getColor()->setRGB($value === 'ACTIVE' ? '15803D' : 'B91C1C');
            }
        }
    }

    /** Numeric codes stay numbers (as in the school's file); anything else is written as text. */
    private function setCode(Worksheet $sheet, string $cell, string $code): void
    {
        if (preg_match('/^[1-9]\d{0,14}$/', $code)) {
            $sheet->setCellValue($cell, (int) $code);
        } else {
            $sheet->setCellValueExplicit($cell, $code, DataType::TYPE_STRING);
        }
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
        ]);
    }

    private function autoWidth(Worksheet $sheet, string $lastColumn): void
    {
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function groupRank(string $group): int
    {
        return self::GROUP_ORDER[$group] ?? 8;
    }

    private function number(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);

        return is_numeric($clean) && (float) $clean > 0 ? (float) $clean : null;
    }
}
