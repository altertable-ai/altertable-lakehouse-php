<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class AppendPayload
{
    public function __construct(
        public readonly array $data,
    ) {
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public static function fromArray(array $data): self
    {
        return new self(data: $data);
    }
}
