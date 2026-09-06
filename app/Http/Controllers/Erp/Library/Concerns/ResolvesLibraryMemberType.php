<?php

namespace App\Http\Controllers\Erp\Library\Concerns;

use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesLibraryMemberType
{
    private const TYPES = [
        'student' => Student::class,
        'teacher' => Teacher::class,
        'staff' => Staff::class,
    ];

    private function libraryMemberModelClass(string $type): string
    {
        return self::TYPES[$type] ?? throw new NotFoundHttpException("Unknown member type [{$type}].");
    }
}
