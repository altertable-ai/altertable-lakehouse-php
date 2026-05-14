<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class AppendPayload
{
    public function __construct(
        public readonly array $data,
        public readonly ?array $columns = null,
    ) {
    }

    public function toArray(): array
    {
        $result = ['data' => $this->data];
        if ($this->columns !== null) {
            $result['columns'] = $this->columns;
        }
        return $result;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'],
            columns: $data['columns'] ?? null,
        );
    }
}
