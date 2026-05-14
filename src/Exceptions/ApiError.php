<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class ApiError extends LakehouseException
{
    public function __construct(
        string $message = 'API error',
        ?\Throwable $previous = null,
        ?string $operation = null,
        ?string $method = null,
        ?string $path = null,
        ?int $statusCode = null,
        ?string $requestId = null,
    ) {
        parent::__construct(
            $message,
            $statusCode ?? 0,
            $previous,
            $operation,
            $method,
            $path,
            $statusCode,
            $requestId,
            retriable: $statusCode !== null && $statusCode >= 500,
        );
    }
}
