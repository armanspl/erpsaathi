<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateType extends Model
{
    protected $fillable = ['label', 'prefix', 'roles', 'custom_fields', 'is_system', 'template_id'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'custom_fields' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
