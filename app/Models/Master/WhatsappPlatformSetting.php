<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

/** Single-row platform Meta/WhatsApp settings (Super Admin → WhatsApp Configuration). */
class WhatsappPlatformSetting extends Model
{
    protected $connection = 'master';

    protected $fillable = [
        'embedded_signup_enabled',
        'app_id',
        'embedded_signup_config_id',
        'graph_version',
        'app_secret',
        'webhook_verify_token',
        'signup_ticket_ttl',
        'app_secret_updated_at',
        'webhook_verify_token_updated_at',
        'updated_by_id',
        'updated_by_email',
        'last_check',
    ];

    protected $hidden = ['app_secret', 'webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'embedded_signup_enabled' => 'boolean',
            'app_secret' => 'encrypted',
            'webhook_verify_token' => 'encrypted',
            'signup_ticket_ttl' => 'integer',
            'app_secret_updated_at' => 'datetime',
            'webhook_verify_token_updated_at' => 'datetime',
            'last_check' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::query()->create(['graph_version' => 'v23.0']);
    }
}
