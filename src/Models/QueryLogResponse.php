<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class QueryLogResponse
{
    public function __construct(
        public readonly string $queryId,
        public readonly string $status,
        public readonly ?string $statement = null,
        public readonly ?int $durationMs = null,
        public readonly ?int $totalRows = null,
        public readonly ?int $totalBytes = null,
        public readonly ?string $error = null,
        public readonly ?float $progress = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            queryId: $data['query_id'] ?? '',
            status: $data['status'] ?? 'unknown',
            statement: $data['statement'] ?? null,
            durationMs: $data['duration_ms'] ?? null,
            totalRows: $data['total_rows'] ?? null,
            totalBytes: $data['total_bytes'] ?? null,
            error: $data['error'] ?? null,
            progress: $data['progress'] ?? null,
        );
    }
}
