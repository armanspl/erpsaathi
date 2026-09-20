<?php

namespace App\Services;

use App\Models\SchoolSetting;

/**
 * Canonical list of student-portal modules and their admin-configurable visibility.
 * Settings live as a single JSON map on SchoolSetting::student_portal_visibility —
 * null/missing keys default to visible (true), so a school doesn't need to touch
 * anything for the portal to work at full capability out of the box.
 */
class StudentPortalCatalog
{
    /** @var array<string, string> module key => label */
    public const MODULES = [
        'profile' => 'My Profile',
        'attendance' => 'Attendance',
        'exam_schedule' => 'Exam Schedule',
        'admit_card' => 'Admit Card',
        'marks' => 'Marks / Grades',
        'report_card' => 'Report Card',
        'subject_performance' => 'Subject-wise Performance',
        'previous_results' => 'Previous Results',
        'fee_summary' => 'Fee Summary',
        'paid_fees' => 'Paid Fees',
        'pending_fees' => 'Pending Fees',
        'fee_history' => 'Fee History',
        'receipts' => 'Receipts',
        'transport' => 'Transport',
        'events_calendar' => 'Events & Calendar',
        'notices' => 'Notices & Circulars',
        'homework' => 'Homework',
        'id_card' => 'ID Card',
        'documents' => 'My Documents',
        'library' => 'Library',
    ];

    /** @return array<string, bool> every module key => enabled */
    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::MODULES), true);
    }

    /** @return array<string, bool> */
    public static function resolve(?SchoolSetting $school = null): array
    {
        $school ??= SchoolSetting::current();
        $stored = is_array($school->student_portal_visibility) ? $school->student_portal_visibility : [];

        $resolved = self::defaults();
        foreach ($resolved as $key => $default) {
            if (array_key_exists($key, $stored)) {
                $resolved[$key] = (bool) $stored[$key];
            }
        }

        return $resolved;
    }

    public static function isEnabled(string $key, ?SchoolSetting $school = null): bool
    {
        return self::resolve($school)[$key] ?? true;
    }
}
