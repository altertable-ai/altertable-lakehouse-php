<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class ValidateRequest
{
    public function __construct(
        public readonly string $statement,
        public readonly ?string $catalog = null,
        public readonly ?string $schema = null,
    ) {
    }

    public function toArray(): array
    {
        $result = ['statement' => $this->statement];
        if ($this->catalog !== null) {
            $result['catalog'] = $this->catalog;
        }
        if ($this->schema !== null) {
            $result['schema'] = $this->schema;
        }
        return $result;
    }
}
