<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class AppendRequest
{
    private function __construct(
        public readonly ?AppendPayload $single = null,
        public readonly ?array $batch = null,
    ) {
    }

    public static function single(AppendPayload $payload): self
    {
        return new self(single: $payload);
    }

    public static function batch(AppendPayload ...$payloads): self
    {
        return new self(batch: $payloads);
    }

    public function toArray(): array
    {
        if ($this->single !== null) {
            return $this->single->toArray();
        }

        return array_map(
            static fn (AppendPayload $p) => $p->toArray(),
            $this->batch ?? [],
        );
    }
}
