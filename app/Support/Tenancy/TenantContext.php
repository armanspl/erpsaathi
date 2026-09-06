<?php

namespace App\Support\Tenancy;

use App\Models\Master\School;

class TenantContext
{
    protected ?School $school = null;

    protected bool $isCentral = false;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function check(): bool
    {
        return $this->school !== null;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function slug(): ?string
    {
        return $this->school?->slug;
    }

    public function setCentral(bool $central = true): void
    {
        $this->isCentral = $central;
        if ($central) {
            $this->school = null;
        }
    }

    public function isCentral(): bool
    {
        return $this->isCentral;
    }

    public function clear(): void
    {
        $this->school = null;
        $this->isCentral = false;
    }
}
