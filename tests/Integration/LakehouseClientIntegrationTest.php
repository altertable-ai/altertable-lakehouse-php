<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Tests\Integration;

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\LakehouseClient;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\UpsertMode;
use Altertable\Lakehouse\Models\ValidateRequest;
use PHPUnit\Framework\TestCase;

final class LakehouseClientIntegrationTest extends TestCase
{
    private static ?LakehouseClient $client = null;
    private static $mockProcess = null;

    public static function setUpBeforeClass(): void
    {
        $port = getenv('ALTERTABLE_MOCK_PORT') ?: '15000';

        if (getenv('CI') === false || getenv('CI') === '') {
            self::startLocalMock($port);
        }

        $baseUrl = 'http://localhost:' . $port;

        $config = LakehouseConfig::builder()
            ->withCredentials('testuser', 'testpass')
            ->withBaseUrl($baseUrl)
            ->build();

        self::$client = new LakehouseClient($config);
    }

    public static function tearDownAfterClass(): void
    {
        self::$client = null;

        if (is_resource(self::$mockProcess)) {
            proc_terminate(self::$mockProcess);
            proc_close(self::$mockProcess);
            self::$mockProcess = null;
        }
    }

    private static function startLocalMock(string $port): void
    {
        $dockerBinary = trim((string) shell_exec('command -v docker 2>/dev/null'));
        if ($dockerBinary === '') {
            self::markTestSkipped('Local integration tests require Docker when CI is not set.');
        }

        $command = sprintf(
            '%s run --rm -p %s:15000 '
            . '-e ALTERTABLE_MOCK_USERS=testuser:testpass '
            . 'ghcr.io/altertable-ai/altertable-mock:latest',
            escapeshellcmd($dockerBinary),
            escapeshellarg($port),
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/altertable-mock.out', 'a'],
            2 => ['file', sys_get_temp_dir() . '/altertable-mock.err', 'a'],
        ];

        self::$mockProcess = proc_open(
            $command,
            $descriptorSpec,
            $pipes,
        );
        if (!is_resource(self::$mockProcess)) {
            self::markTestSkipped('Failed to start local altertable-mock container.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', (int) $port, $errno, $errstr, 0.5);
            if (is_resource($fp)) {
                fclose($fp);
                return;
            }
            usleep(250000);
        }

        self::markTestSkipped('altertable-mock did not become ready in time.');
    }

    public function testAppend(): void
    {
        $payload = [
            'id' => 1,
            'name' => 'Alice',
        ];

        $response = self::$client->append('test', 'public', 'users', $payload);
        self::assertFalse($response->ok);
        self::assertSame('invalid-data', $response->errorCode);
    }

    public function testQueryStreamed(): void
    {
        $result = self::$client->query(new QueryRequest(
            statement: 'SELECT 1',
        ));

        self::assertNotEmpty($result->metadata->queryId);
        self::assertIsIterable($result->rows);

        $rows = iterator_to_array($result->rows, false);
        self::assertCount(1, $rows);
    }

    public function testQueryAll(): void
    {
        $result = self::$client->queryAll(new QueryRequest(
            statement: 'SELECT 1',
        ));

        self::assertNotEmpty($result->metadata->queryId);
        self::assertIsArray($result->rows);
        self::assertCount(1, $result->rows);
    }

    public function testGetQuery(): void
    {
        $queryId = self::$client->query(new QueryRequest(
            statement: 'SELECT 1',
        ))->metadata->queryId;

        self::assertNotEmpty($queryId);

        $log = self::$client->getQuery($queryId);
        self::assertSame($queryId, $log->queryId);
    }

    public function testCancelQuery(): void
    {
        $queryResult = self::$client->queryAll(new QueryRequest(statement: 'SELECT 1'));
        $queryId = $queryResult->metadata->queryId;
        $sessionId = $queryResult->metadata->sessionId;

        self::assertNotNull($sessionId);

        $response = self::$client->cancelQuery($queryId, $sessionId);
        self::assertTrue($response->ok || $response->cancelled === true, 'Cancel query should acknowledge the request');
    }

    public function testUpsertCsv(): void
    {
        $csv = "id,name,email\n1,Alice,alice@example.com\n2,Bob,bob@example.com\n";

        $this->expectException(\Altertable\Lakehouse\Exceptions\BadRequestError::class);
        self::$client->upsert(
            'test',
            'public',
            'upload_test',
            $csv,
            UpsertMode::Create,
        );
    }

    public function testValidate(): void
    {
        $response = self::$client->validate(new ValidateRequest('SELECT 1'));
        self::assertTrue($response->valid);
    }

    public function testAppendBatch(): void
    {
        $response = self::$client->append(
            'test',
            'public',
            'batch_test',
            [
                ['id' => 1, 'val' => 'a'],
                ['id' => 2, 'val' => 'b'],
            ],
        );

        self::assertFalse($response->ok);
        self::assertSame('invalid-data', $response->errorCode);
    }

    public function testQueryInvalidSql(): void
    {
        $this->expectException(\Altertable\Lakehouse\Exceptions\BadRequestError::class);
        self::$client->queryAll(new QueryRequest(statement: 'SELECT INVALID'));
    }
}
