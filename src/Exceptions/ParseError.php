<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class ParseError extends LakehouseException
{
    public function __construct(
        string $message = 'Parse error',
        ?\Throwable $previous = null,
        ?string $operation = null,
        public readonly ?int $lineIndex = null,
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
