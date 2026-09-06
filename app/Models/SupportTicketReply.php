<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReply extends Model
{
    protected $fillable = ['support_ticket_id', 'erp_user_id', 'message'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function erpUser(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class);
    }
}
