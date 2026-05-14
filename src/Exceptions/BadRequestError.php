<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class BadRequestError extends LakehouseException
{
    public function __construct(
        string $message = 'Bad request',
        ?\Throwable $previous = null,
        ?string $operation = null,
        ?string $method = null,
        ?string $path = null,
        ?int $statusCode = null,
        ?string $requestId = null,
    ) {
        parent::__construct(
            $message,
            400,
            $previous,
            $operation,
            $method,
            $path,
            $statusCode,
            $requestId,
            retriable: false,
        );
    }
}
