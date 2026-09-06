<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpNotice extends Model
{
    protected $table = 'erp_notices';

    protected $fillable = ['title', 'content', 'type', 'audience', 'publish_date', 'expiry_date', 'status', 'created_by_id'];

    protected function casts(): array
    {
        return [
            'publish_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'created_by_id');
    }
}
