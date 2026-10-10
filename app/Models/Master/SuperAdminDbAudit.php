<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

/** One Super Admin Database Manager operation (record edit/delete, empty, truncate, drop, backup). */
class SuperAdminDbAudit extends Model
{
    protected $connection = 'master';

    public const UPDATED_AT = null;

    protected $fillable = [
        'super_admin_id',
        'super_admin_email',
        'school_id',
        'db_name',
        'action',
        'table_name',
        'record_key',
        'details',
        'affected_rows',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'record_key' => 'array',
            'details' => 'array',
        ];
    }
}
