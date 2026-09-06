<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRowLog extends Model
{
    protected $fillable = ['import_export_log_id', 'row_number', 'status', 'identifier', 'summary', 'error_message'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(ImportExportLog::class, 'import_export_log_id');
    }
}
