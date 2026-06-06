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
        public readonly ?string $sessionId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            queryId: (string) ($data['query_id'] ?? $data['uuid'] ?? $data['id'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            totalRows: $data['total_rows'] ?? $data['row_count'] ?? null,
            elapsedMs: $data['elapsed_ms'] ?? $data['duration_ms'] ?? null,
            sessionId: isset($data['session_id']) ? (string) $data['session_id'] : null,
        );
    }
}
