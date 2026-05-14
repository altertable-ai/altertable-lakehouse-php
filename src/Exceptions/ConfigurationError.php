<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Exceptions;

final class ConfigurationError extends LakehouseException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
