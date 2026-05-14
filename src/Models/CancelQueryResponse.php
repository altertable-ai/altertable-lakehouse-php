<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class CancelQueryResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $message = null,
        public readonly ?bool $cancelled = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $cancelled = isset($data['cancelled']) ? (bool) $data['cancelled'] : null;

        return new self(
            ok: (bool) ($data['ok'] ?? $cancelled ?? false),
            message: $data['message'] ?? null,
            cancelled: $cancelled,
        );
    }
}
