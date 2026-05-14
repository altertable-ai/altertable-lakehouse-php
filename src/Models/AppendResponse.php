<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class AppendResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ok: $data['ok'] ?? false,
            errorCode: $data['error_code'] ?? null,
        );
    }
}
