<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SchoolSetting extends Model
{
    protected $fillable = [
        'school_name',
        'school_code',
        'registration_no',
        'udise_code',
        'address',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'district',
        'block',
        'pincode',
        'phone',
        'email',
        'website',
        'instagram',
        'facebook',
        'youtube',
        'linkedin',
        'twitter',
        'logo_path',
        'banner_path',
        'favicon_path',
        'browser_title',
        'meta_description',
        'watermark_text',
        'show_watermark',
        'compact_sidebar',
        'signatures',
        'stamps',
        'established_year',
        'current_branch',
    ];

    protected $appends = [
        'logo_url',
        'banner_url',
        'favicon_url',
        'formatted_address',
    ];

    protected function casts(): array
    {
        return [
            'show_watermark' => 'boolean',
            'compact_sidebar' => 'boolean',
            'signatures' => 'array',
            'stamps' => 'array',
        ];
    }

    /**
     * This table is a singleton — always work with the first (and only) row,
     * creating a sensible default if it doesn't exist yet.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'school_name' => 'Global Access School',
            'current_branch' => 'Main Campus',
            'browser_title' => 'Global Access School',
            'watermark_text' => 'GAS',
            'show_watermark' => true,
            'signatures' => [
                ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Principal', 'image_path' => null],
                ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Parents', 'image_path' => null],
                ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Examination Controller', 'image_path' => null],
            ],
            'stamps' => [],
        ]);
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1 ?: $this->address,
            $this->address_line_2,
            collect([$this->city, $this->state])->filter()->implode(', '),
            $this->pincode,
        ]);

        return implode(', ', $parts);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->assetUrl($this->logo_path);
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->assetUrl($this->banner_path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->assetUrl($this->favicon_path) ?: $this->logo_url;
    }

    public function signatureUrl(?string $path): ?string
    {
        return $this->assetUrl($path);
    }

    public function findSignature(string $label): ?array
    {
        $needle = strtolower(trim($label));

        foreach ($this->signatures ?? [] as $sig) {
            if (strtolower(trim((string) ($sig['label'] ?? ''))) === $needle) {
                return $sig;
            }
        }

        return null;
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, 'data:') || str_starts_with($path, '/')) {
            return $path;
        }

        $filename = basename($path);

        return url('/erp/api/settings/school/assets/'.$filename);
    }

    public function storagePathExists(?string $path): bool
    {
        return $path && Storage::disk('local')->exists($path);
    }
}
