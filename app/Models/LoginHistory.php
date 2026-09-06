<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = ['erp_user_id', 'email', 'status', 'ip_address', 'user_agent'];

    public function erpUser(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class);
    }
}
