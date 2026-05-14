<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class NetworkError extends LakehouseException
{
    public function __construct(
        string $message = 'Network error',
        ?\Throwable $previous = null,
        ?string $operation = null,
        ?string $method = null,
        ?string $path = null,
        ?string $requestId = null,
    ) {
        parent::__construct(
            $message,
            0,
            $previous,
            $operation,
            $method,
            $path,
            null,
            $requestId,
            retriable: true,
        );
    }
}
