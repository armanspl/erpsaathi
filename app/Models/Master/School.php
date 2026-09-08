<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $connection = 'master';

    protected $fillable = [
        'name',
        'slug',
        'db_name',
        'db_host',
        'status',
        'admin_email',
        'price',
        'renewal_charge',
        'billing_currency',
        'storage_path',
        'is_first_school',
        'notes',
        'demo_settings',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_first_school' => 'boolean',
            'price' => 'decimal:2',
            'renewal_charge' => 'decimal:2',
            'demo_settings' => 'array',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SchoolDomain::class);
    }

    public function primaryDomain(): ?SchoolDomain
    {
        return $this->domains()->where('is_primary', true)->first()
            ?? $this->domains()->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function storageRoot(): string
    {
        if ($this->storage_path) {
            return $this->storage_path;
        }

        return rtrim(config('tenancy.storage_root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$this->slug;
    }
}
