<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = ['action', 'auditable_type', 'auditable_id', 'changes', 'performed_by_id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'performed_by_id');
    }
}
