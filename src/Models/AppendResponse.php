<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

final class AppendResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $taskId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ok: (bool) ($data['ok'] ?? $data['success'] ?? false),
            errorCode: $data['error_code'] ?? $data['errorCode'] ?? null,
            errorMessage: $data['error_message'] ?? $data['errorMessage'] ?? null,
            taskId: $data['task_id'] ?? $data['taskId'] ?? null,
        );
    }
}
