<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Config;

use Altertable\Lakehouse\Exceptions\ConfigurationError;

final class LakehouseConfigBuilder
{
    private string $baseUrl = LakehouseConfig::DEFAULT_BASE_URL;
    private ?string $username = null;
    private ?string $password = null;
    private ?string $basicAuthToken = null;
    private int $connectTimeout = LakehouseConfig::DEFAULT_CONNECT_TIMEOUT;
    private int $readTimeout = LakehouseConfig::DEFAULT_READ_TIMEOUT;
    private int $maxRetries = LakehouseConfig::DEFAULT_MAX_RETRIES;
    private int $retryDelayMs = LakehouseConfig::DEFAULT_RETRY_DELAY_MS;
    private ?string $userAgentSuffix = null;

    public function withBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    public function withCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    public function withBasicAuthToken(string $token): self
    {
        $this->basicAuthToken = $token;
        return $this;
    }

    public function withConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    public function withReadTimeout(int $seconds): self
    {
        $this->readTimeout = $seconds;
        return $this;
    }

    public function withMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withRetryDelayMs(int $ms): self
    {
        $this->retryDelayMs = $ms;
        return $this;
    }

    public function withUserAgentSuffix(string $suffix): self
    {
        $this->userAgentSuffix = $suffix;
        return $this;
    }

    public function build(): LakehouseConfig
    {
        $token = $this->resolveAuthToken();

        if ($token === null) {
            throw new ConfigurationError(
                'No authentication credentials found. Provide username+password, '
                . 'a pre-encoded basic_auth_token, or set ALTERTABLE_USERNAME/ALTERTABLE_PASSWORD '
                . 'or ALTERTABLE_BASIC_AUTH_TOKEN environment variables.',
            );
        }

        return new LakehouseConfig(
            baseUrl: $this->baseUrl,
            basicAuthToken: $this->normalizeBasicAuthToken($token),
            connectTimeout: $this->connectTimeout,
            readTimeout: $this->readTimeout,
            maxRetries: $this->maxRetries,
            retryDelayMs: $this->retryDelayMs,
            userAgentSuffix: $this->userAgentSuffix,
        );
    }

    private function resolveAuthToken(): ?string
    {
        if ($this->basicAuthToken !== null) {
            return $this->basicAuthToken;
        }

        if ($this->username !== null && $this->password !== null) {
            return base64_encode($this->username . ':' . $this->password);
        }

        $envToken = getenv('ALTERTABLE_BASIC_AUTH_TOKEN');
        if ($envToken !== false && $envToken !== '') {
            return $envToken;
        }

        $envUser = getenv('ALTERTABLE_USERNAME');
        $envPass = getenv('ALTERTABLE_PASSWORD');
        if ($envUser !== false && $envUser !== '' && $envPass !== false && $envPass !== '') {
            return base64_encode($envUser . ':' . $envPass);
        }

        return null;
    }

    private function normalizeBasicAuthToken(string $token): string
    {
        return str_starts_with($token, 'Basic ') ? $token : 'Basic ' . $token;
    }
}
