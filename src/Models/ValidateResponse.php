<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class ValidateResponse
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $error = null,
        public readonly ?array $suggestions = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            valid: $data['valid'] ?? false,
            error: $data['error'] ?? null,
            suggestions: $data['suggestions'] ?? null,
        );
    }
}
