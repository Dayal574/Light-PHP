<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : 'HTTP Error', $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
