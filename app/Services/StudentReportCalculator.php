<?php

namespace App\Services;

use App\Models\Student;

class StudentReportCalculator
{
    /** Student demographics snapshot, computed live from the students table. */
    public static function summary(): array
    {
        $students = Student::with('schoolClass:id,name')->get();
        $active = $students->where('status', 'Active');

        $classWise = $active->groupBy(fn (Student $s) => $s->schoolClass->name ?? 'Unassigned')
            ->map(fn ($rows) => $rows->count())
            ->sortKeys();

        $ym = now()->format('Y-m');

        return [
            'total_students' => $students->count(),
            'active_students' => $active->count(),
            'inactive_students' => $students->count() - $active->count(),
            'gender_breakdown' => $active->groupBy('gender')->map(fn ($rows) => $rows->count()),
            'class_wise_strength' => $classWise->map(fn ($count, $name) => ['class' => $name, 'count' => $count])->values(),
            'new_admissions_this_month' => $active->filter(fn (Student $s) => $s->admission_date && $s->admission_date->format('Y-m') === $ym)->count(),
        ];
    }
}
