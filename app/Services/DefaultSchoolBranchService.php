<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Support\AcademicsCache;

/**
 * Ensures a default branch named after the school exists and can be assigned to students.
 */
class DefaultSchoolBranchService
{
    public static function ensure(): Branch
    {
        $school = SchoolSetting::current();
        $name = trim((string) ($school->school_name ?: 'Main Campus'));

        $branch = Branch::firstOrCreate(
            ['name' => $name],
            [
                'principal' => 'Principal',
                'phone' => trim((string) ($school->phone ?: '')) ?: '0000000000',
                'address' => $school->formatted_address ?: ($school->address ?: null),
                'status' => 'active',
            ]
        );

        if ($branch->wasRecentlyCreated) {
            AcademicsCache::forget();
        }

        return $branch;
    }

    /** Assign every student without a branch to the default school branch. */
    public static function assignUnassignedStudents(?Branch $branch = null): int
    {
        $branch ??= self::ensure();

        return Student::query()
            ->whereNull('branch_id')
            ->update(['branch_id' => $branch->id]);
    }
}
