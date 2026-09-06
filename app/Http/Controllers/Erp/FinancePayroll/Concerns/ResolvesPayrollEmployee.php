<?php

namespace App\Http\Controllers\Erp\FinancePayroll\Concerns;

use App\Models\Staff;
use App\Models\Teacher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesPayrollEmployee
{
    private const TYPES = [
        'teacher' => Teacher::class,
        'staff' => Staff::class,
    ];

    private function payrollEmployeeModelClass(string $type): string
    {
        return self::TYPES[$type] ?? throw new NotFoundHttpException("Unknown employee type [{$type}].");
    }
}
