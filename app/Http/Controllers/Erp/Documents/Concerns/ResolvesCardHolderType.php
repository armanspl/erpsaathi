<?php

namespace App\Http\Controllers\Erp\Documents\Concerns;

use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesCardHolderType
{
    private const TYPES = [
        'student' => Student::class,
        'teacher' => Teacher::class,
        'staff' => Staff::class,
    ];

    private function cardHolderModelClass(string $type): string
    {
        return self::TYPES[$type] ?? throw new NotFoundHttpException("Unknown holder type [{$type}].");
    }
}
