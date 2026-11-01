<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Working days the school was open in one calendar month (school-wide, not per student). */
class SchoolWorkingDay extends Model
{
    protected $fillable = [
        'year',
        'month',
        'working_days',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'working_days' => 'integer',
        ];
    }

    /** @var array<string, bool> database name => table exists */
    private static array $tableExists = [];

    /** Whether this school's database has the table yet (its migration may not have run). */
    public static function tableReady(): bool
    {
        $model = new static;
        $db = (string) $model->getConnection()->getDatabaseName();

        return self::$tableExists[$db] ??= Schema::connection($model->getConnectionName())->hasTable($model->getTable());
    }
}
