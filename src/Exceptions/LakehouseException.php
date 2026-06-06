<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

class LakehouseException extends \RuntimeException
{
    public readonly bool $retriable;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $operation = null,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $requestId = null,
        bool $retriable = false,
    ) {
        parent::__construct($message, $code, $previous);
        $this->retriable = $retriable;
    }
}
