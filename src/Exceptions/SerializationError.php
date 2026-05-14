<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class SerializationError extends LakehouseException
{
    public function __construct(
        string $message = 'Serialization error',
        ?\Throwable $previous = null,
        ?string $operation = null,
    ) {
        parent::__construct(
            $message,
            0,
            $previous,
            $operation,
            retriable: false,
        );
    }
}
