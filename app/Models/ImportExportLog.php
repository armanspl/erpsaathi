<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportExportLog extends Model
{
    protected $fillable = [
        'direction', 'entity', 'filename', 'total_rows', 'success_count', 'failed_count',
        'classes_created', 'sections_created', 'academic_sessions_created', 'vehicles_created', 'rooms_created', 'beds_created',
        'ignored_columns', 'performed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'ignored_columns' => 'array',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'performed_by_id');
    }

    public function failedRows(): HasMany
    {
        return $this->hasMany(ImportFailedRow::class);
    }

    public function rowLogs(): HasMany
    {
        return $this->hasMany(ImportRowLog::class);
    }
}
