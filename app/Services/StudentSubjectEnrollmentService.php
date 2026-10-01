<?php

namespace App\Services;

use App\Models\StudentSubjectEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves which subjects actually apply to a given student, on top of the class-level catalog
 * (class_subject). A subject is compulsory unless class_subject.is_optional is true; an optional
 * subject only applies to a student when either:
 *   - they have an explicit enrollment row for the session (student_subject_enrollments), or
 *   - they already have a mark recorded against it (legacy/imported data — never silently drops
 *     a mark that already exists just because no enrollment row was ever created for it).
 * Every calculator/exports/marks-entry surface that walks "this class's subjects" per student
 * must run the result through applies()/filterSchedules() instead of using the class list as-is.
 */
class StudentSubjectEnrollmentService
{
    /**
     * Optional subject IDs per class, from the class-level catalog.
     *
     * @param  array<int, int>  $schoolClassIds
     * @return Collection<int, Collection<int, int>> keyed by school_class_id
     */
    public static function optionalSubjectIdsByClass(array $schoolClassIds): Collection
    {
        if ($schoolClassIds === []) {
            return collect();
        }

        return DB::table('class_subject')
            ->whereIn('school_class_id', $schoolClassIds)
            ->where('is_optional', true)
            ->get(['school_class_id', 'subject_id'])
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('subject_id'));
    }

    /**
     * Explicit optional-subject enrollments per student, for one academic session.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection<int, int>> keyed by student_id
     */
    public static function enrolledSubjectIdsByStudent(array $studentIds, ?int $academicSessionId): Collection
    {
        if ($studentIds === [] || ! $academicSessionId) {
            return collect();
        }

        return StudentSubjectEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_session_id', $academicSessionId)
            ->get(['student_id', 'subject_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('subject_id'));
    }

    /**
     * Whether a subject applies to a student: always true for compulsory subjects; for an
     * optional one, true only if enrolled or already has a mark against it.
     */
    public static function applies(int $subjectId, Collection $optionalIdsForClass, Collection $enrolledIdsForStudent, bool $hasExistingMark): bool
    {
        if (! $optionalIdsForClass->contains($subjectId)) {
            return true;
        }

        return $enrolledIdsForStudent->contains($subjectId) || $hasExistingMark;
    }

    /** Create the enrollment row if it doesn't exist yet — safe to call for compulsory subjects too (no-op there). */
    public static function ensureEnrolled(int $studentId, int $subjectId, int $schoolClassId, ?int $academicSessionId): void
    {
        if (! $academicSessionId) {
            return;
        }

        StudentSubjectEnrollment::firstOrCreate([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'academic_session_id' => $academicSessionId,
        ], [
            'school_class_id' => $schoolClassId,
        ]);
    }
}
