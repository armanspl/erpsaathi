<?php

namespace App\Services\Tenancy;

use RuntimeException;

/** A refused or failed Super Admin Database Manager request; $status is the HTTP status to answer with. */
class DatabaseManagerException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
