<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Config;

final class LakehouseConfig
{
    public const DEFAULT_BASE_URL = 'https://api.altertable.ai';
    public const DEFAULT_CONNECT_TIMEOUT = 5;
    public const DEFAULT_READ_TIMEOUT = 60;
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_RETRY_DELAY_MS = 500;

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $basicAuthToken,
        public readonly int $connectTimeout,
        public readonly int $readTimeout,
        public readonly int $maxRetries,
        public readonly int $retryDelayMs,
        public readonly ?string $userAgentSuffix,
    ) {
    }

    public static function builder(): LakehouseConfigBuilder
    {
        return new LakehouseConfigBuilder();
    }

    public static function fromArray(array $options): self
    {
        $builder = self::builder();

        if (isset($options['base_url'])) {
            $builder->withBaseUrl($options['base_url']);
        }
        if (isset($options['username']) && isset($options['password'])) {
            $builder->withCredentials($options['username'], $options['password']);
        }
        if (isset($options['basic_auth_token'])) {
            $builder->withBasicAuthToken($options['basic_auth_token']);
        }
        if (isset($options['connect_timeout'])) {
            $builder->withConnectTimeout($options['connect_timeout']);
        }
        if (isset($options['read_timeout'])) {
            $builder->withReadTimeout($options['read_timeout']);
        }
        if (isset($options['max_retries'])) {
            $builder->withMaxRetries($options['max_retries']);
        }
        if (isset($options['retry_delay_ms'])) {
            $builder->withRetryDelayMs($options['retry_delay_ms']);
        }
        if (isset($options['user_agent_suffix'])) {
            $builder->withUserAgentSuffix($options['user_agent_suffix']);
        }

        return $builder->build();
    }
}
