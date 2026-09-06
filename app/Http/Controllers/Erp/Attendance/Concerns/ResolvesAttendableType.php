<?php

namespace App\Http\Controllers\Erp\Attendance\Concerns;

use App\Models\Driver;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesAttendableType
{
    private const TYPES = [
        'student' => Student::class,
        'teacher' => Teacher::class,
        'staff' => Staff::class,
        'driver' => Driver::class,
    ];

    private function attendableModelClass(string $type): string
    {
        return self::TYPES[$type] ?? throw new NotFoundHttpException("Unknown attendable type [{$type}].");
    }
}
