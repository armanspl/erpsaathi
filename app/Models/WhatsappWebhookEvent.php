<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappWebhookEvent extends Model
{
    protected $fillable = [
        'event_key',
        'kind',
        'phone_number_id',
        'wamid',
        'status',
        'occurred_at',
        'payload',
        'processed_at',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
