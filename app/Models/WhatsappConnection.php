<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappConnection extends Model
{
    protected $fillable = [
        'waba_id',
        'waba_name',
        'business_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'platform_type',
        'code_verification_status',
        'access_token',
        'token_expires_at',
        'status',
        'connected_by_id',
        'connected_at',
        'disconnected_at',
        'last_error',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('status', 'connected')->latest('connected_at')->first();
    }

    /** Safe for the browser — never includes the token. */
    public function toPublicArray(): array
    {
        return [
            'status' => $this->status,
            'waba_id' => $this->waba_id,
            'waba_name' => $this->waba_name,
            'phone_number_id' => $this->phone_number_id,
            'display_phone_number' => $this->display_phone_number,
            'verified_name' => $this->verified_name,
            'quality_rating' => $this->quality_rating,
            'platform_type' => $this->platform_type,
            'registered' => $this->platform_type === 'CLOUD_API',
            'code_verification_status' => $this->code_verification_status,
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_error' => $this->last_error,
        ];
    }
}
