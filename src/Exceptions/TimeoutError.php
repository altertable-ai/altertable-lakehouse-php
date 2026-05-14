<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class TimeoutError extends LakehouseException
{
    public function __construct(
        string $message = 'Request timed out',
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
