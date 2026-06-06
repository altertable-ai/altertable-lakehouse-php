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
        $error = $data['error'] ?? null;
        if (is_array($error)) {
            $error = json_encode($error);
            $error = $error === false ? null : $error;
        }

        return new self(
            queryId: (string) ($data['query_id'] ?? $data['uuid'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            statement: $data['statement'] ?? $data['query'] ?? null,
            durationMs: $data['duration_ms'] ?? null,
            totalRows: $data['total_rows'] ?? $data['row_count'] ?? null,
            totalBytes: $data['total_bytes'] ?? null,
            error: $error,
            progress: isset($data['progress']) ? (float) $data['progress'] : null,
        );
    }
}
