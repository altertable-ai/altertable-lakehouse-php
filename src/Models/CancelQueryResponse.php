<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class CancelQueryResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $message = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ok: $data['ok'] ?? false,
            message: $data['message'] ?? null,
        );
    }
}
