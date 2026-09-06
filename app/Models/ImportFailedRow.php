<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFailedRow extends Model
{
    protected $fillable = ['import_export_log_id', 'row_number', 'row_data', 'error_message'];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(ImportExportLog::class, 'import_export_log_id');
    }
}
