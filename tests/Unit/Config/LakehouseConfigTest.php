<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Tests\Unit\Config;

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\Exceptions\ConfigurationError;
use PHPUnit\Framework\TestCase;

final class LakehouseConfigTest extends TestCase
{
    public function testBuildWithCredentials(): void
    {
        $config = LakehouseConfig::builder()
            ->withCredentials('alice', 'secret')
            ->build();

        self::assertSame('Basic ' . base64_encode('alice:secret'), $config->basicAuthToken);
    }

    public function testBuildWithPreEncodedToken(): void
    {
        $config = LakehouseConfig::builder()
            ->withBasicAuthToken('dG9rZW46eHh4')
            ->build();

        self::assertSame('Basic dG9rZW46eHh4', $config->basicAuthToken);
    }

    public function testBuildWithEnvCredentials(): void
    {
        putenv('ALTERTABLE_USERNAME=envuser');
        putenv('ALTERTABLE_PASSWORD=envpass');

        $config = LakehouseConfig::builder()->build();

        self::assertSame('Basic ' . base64_encode('envuser:envpass'), $config->basicAuthToken);

        putenv('ALTERTABLE_USERNAME');
        putenv('ALTERTABLE_PASSWORD');
    }

    public function testBuildWithEnvToken(): void
    {
        putenv('ALTERTABLE_BASIC_AUTH_TOKEN=ENV_TOKEN_BASE64');

        $config = LakehouseConfig::builder()->build();

        self::assertSame('Basic ENV_TOKEN_BASE64', $config->basicAuthToken);

        putenv('ALTERTABLE_BASIC_AUTH_TOKEN');
    }

    public function testBuildWithoutCredentialsThrows(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('No authentication credentials found');

        LakehouseConfig::builder()->build();
    }

    public function testFromArrayWithCredentials(): void
    {
        $config = LakehouseConfig::fromArray([
            'username' => 'alice',
            'password' => 'secret',
        ]);

        self::assertSame('Basic ' . base64_encode('alice:secret'), $config->basicAuthToken);
    }

    public function testDefaults(): void
    {
        putenv('ALTERTABLE_USERNAME=u');
        putenv('ALTERTABLE_PASSWORD=p');

        $config = LakehouseConfig::builder()
            ->withBaseUrl('http://localhost:15000')
            ->build();

        self::assertSame('http://localhost:15000', $config->baseUrl);
        self::assertSame(5, $config->connectTimeout);
        self::assertSame(60, $config->readTimeout);
        self::assertSame(3, $config->maxRetries);
        self::assertSame(500, $config->retryDelayMs);

        putenv('ALTERTABLE_USERNAME');
        putenv('ALTERTABLE_PASSWORD');
    }

    public function testCustomTimeouts(): void
    {
        putenv('ALTERTABLE_USERNAME=u');
        putenv('ALTERTABLE_PASSWORD=p');

        $config = LakehouseConfig::builder()
            ->withConnectTimeout(10)
            ->withReadTimeout(120)
            ->withMaxRetries(5)
            ->withRetryDelayMs(1000)
            ->withUserAgentSuffix('my-app/1.0')
            ->build();

        self::assertSame(10, $config->connectTimeout);
        self::assertSame(120, $config->readTimeout);
        self::assertSame(5, $config->maxRetries);
        self::assertSame(1000, $config->retryDelayMs);
        self::assertSame('my-app/1.0', $config->userAgentSuffix);

        putenv('ALTERTABLE_USERNAME');
        putenv('ALTERTABLE_PASSWORD');
    }

    public function testCredentialsTakePriorityOverEnv(): void
    {
        putenv('ALTERTABLE_USERNAME=envuser');

        $config = LakehouseConfig::builder()
            ->withCredentials('direct', 'creds')
            ->build();

        self::assertSame('Basic ' . base64_encode('direct:creds'), $config->basicAuthToken);

        putenv('ALTERTABLE_USERNAME');
    }

    protected function tearDown(): void
    {
        putenv('ALTERTABLE_USERNAME');
        putenv('ALTERTABLE_PASSWORD');
        putenv('ALTERTABLE_BASIC_AUTH_TOKEN');
    }
}
