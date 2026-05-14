<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class QueryRequest
{
    public function __construct(
        public readonly string $statement,
        public readonly ?string $catalog = null,
        public readonly ?string $schema = null,
        public readonly ?string $table = null,
        public readonly ?ComputeSize $computeSize = null,
        public readonly ?int $maxResults = null,
        public readonly ?int $timeoutSecs = null,
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
        if ($this->table !== null) {
            $result['table'] = $this->table;
        }
        if ($this->computeSize !== null) {
            $result['compute_size'] = $this->computeSize->value;
        }
        if ($this->maxResults !== null) {
            $result['max_results'] = $this->maxResults;
        }
        if ($this->timeoutSecs !== null) {
            $result['timeout_secs'] = $this->timeoutSecs;
        }
        return $result;
    }
}
