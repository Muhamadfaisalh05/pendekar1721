<?php

namespace App\Modules\Absensi\Exceptions;

use RuntimeException;

/**
 * Exception dengan status HTTP yang dipetakan ke envelope native
 * `{success: false, message: "..."}`.
 */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'Endpoint tidak ditemukan'): self
    {
        return new self($message, 404);
    }
}
