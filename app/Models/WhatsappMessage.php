<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'wamid',
        'direction',
        'phone_number_id',
        'contact_wa_id',
        'type',
        'body',
        'context_wamid',
        'status',
        'status_rank',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'received_at',
        'error_code',
        'error_title',
        'conversation_id',
        'pricing_category',
    ];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'status_rank' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
