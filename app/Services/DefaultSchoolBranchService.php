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
    /** Names created by placeholders / demo seed — safe to rename to the real school name. */
    private const PLACEHOLDER_NAMES = [
        'Global Access School',
        'Main Campus',
    ];

    public static function ensure(): Branch
    {
        $school = SchoolSetting::current();
        $name = trim((string) ($school->school_name ?: 'Main Campus')) ?: 'Main Campus';

        $named = Branch::query()->where('name', $name)->first();
        if ($named) {
            return $named;
        }

        // Single-campus tenants: reuse the only existing branch instead of creating a duplicate.
        $existing = Branch::query()->orderBy('id')->get();
        if ($existing->count() === 1) {
            return self::renameIfPlaceholder($existing->first(), $name);
        }

        $branch = Branch::query()->create([
            'name' => $name,
            'principal' => 'Principal',
            'phone' => trim((string) ($school->phone ?: '')) ?: '0000000000',
            'address' => $school->formatted_address ?: ($school->address ?: null),
            'status' => 'active',
        ]);

        AcademicsCache::forget();

        return $branch;
    }

    /**
     * Write the real school name into settings, then ensure the default branch matches it.
     * Used after Super Admin provisions a tenant so we never leave "Global Access School".
     */
    public static function ensureNamed(string $schoolName): Branch
    {
        $schoolName = trim($schoolName) ?: 'Main Campus';

        $settings = SchoolSetting::current();
        $settings->fill([
            'school_name' => $schoolName,
            'browser_title' => (! $settings->browser_title || $settings->browser_title === 'Global Access School')
                ? $schoolName
                : $settings->browser_title,
            'current_branch' => $schoolName,
            'watermark_text' => (! $settings->watermark_text || $settings->watermark_text === 'GAS')
                ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $schoolName) ?: 'SCH', 0, 8))
                : $settings->watermark_text,
        ]);
        $settings->save();

        $branch = self::ensure();

        if ($branch->name !== $schoolName) {
            // Force-align the sole/auto branch to the school name (covers migration-created placeholders).
            if (Branch::query()->count() === 1 || in_array($branch->name, self::PLACEHOLDER_NAMES, true)) {
                $branch->update([
                    'name' => $schoolName,
                    'address' => $settings->formatted_address ?: ($settings->address ?: $branch->address),
                ]);
                AcademicsCache::forget();
                $branch = $branch->fresh();
            }
        }

        return $branch;
    }

    private static function renameIfPlaceholder(Branch $branch, string $desiredName): Branch
    {
        if ($branch->name === $desiredName || ! in_array($branch->name, self::PLACEHOLDER_NAMES, true)) {
            return $branch;
        }

        $branch->update(['name' => $desiredName]);
        AcademicsCache::forget();

        return $branch->fresh();
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
