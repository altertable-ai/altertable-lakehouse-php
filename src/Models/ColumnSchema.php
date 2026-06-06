<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class ColumnSchema
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?bool $nullable = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            type: $data['type'] ?? 'unknown',
            nullable: $data['nullable'] ?? null,
        );
    }
}
