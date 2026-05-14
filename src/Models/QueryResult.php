<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class QueryResult
{
    public function __construct(
        public readonly QueryMetadata $metadata,
        public readonly array $columns,
        public readonly iterable $rows,
    ) {
    }
}
