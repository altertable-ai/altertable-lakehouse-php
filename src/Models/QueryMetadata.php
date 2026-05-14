<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class QueryMetadata
{
    public function __construct(
        public readonly string $queryId,
        public readonly string $status,
        public readonly ?int $totalRows = null,
        public readonly ?float $elapsedMs = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            queryId: $data['query_id'] ?? '',
            status: $data['status'] ?? 'unknown',
            totalRows: $data['total_rows'] ?? null,
            elapsedMs: $data['elapsed_ms'] ?? null,
        );
    }
}
