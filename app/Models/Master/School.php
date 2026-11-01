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
        'creation_date',
        'renewal_date',
        'storage_path',
        'is_first_school',
        'notes',
        'demo_settings',
        'allow_backdate_fee',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_first_school' => 'boolean',
            'price' => 'decimal:2',
            'renewal_charge' => 'decimal:2',
            'creation_date' => 'date:Y-m-d',
            'renewal_date' => 'date:Y-m-d',
            'demo_settings' => 'array',
            'allow_backdate_fee' => 'boolean',
        ];
    }

    /** Super Admin switch read by Fee Receipt (back-date entry); see BackdateFee. */
    public function allowsBackdateFee(): bool
    {
        return (bool) $this->allow_backdate_fee;
    }

    /** creation_date / renewal_date exist only after the 2026_11_01 master migration has run. */
    public static function hasSubscriptionDates(): bool
    {
        static $ready;

        return $ready ??= \Illuminate\Support\Facades\Schema::connection('master')->hasColumn('schools', 'renewal_date');
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
