<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Tests\Integration;

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\LakehouseClient;
use Altertable\Lakehouse\Models\AppendPayload;
use Altertable\Lakehouse\Models\AppendRequest;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\UploadFormat;
use Altertable\Lakehouse\Models\UploadMode;
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
        $payload = new AppendPayload(
            data: [['id' => 1, 'name' => 'Alice']],
            columns: ['id', 'name'],
        );

        $response = self::$client->append('default', 'public', 'users', AppendRequest::single($payload));
        self::assertTrue($response->ok, $response->errorCode ?? 'append returned ok=false');
    }

    public function testQueryStreamed(): void
    {
        $result = self::$client->query(new QueryRequest(
            statement: 'SELECT * FROM default.public.users LIMIT 10',
        ));

        self::assertNotEmpty($result->metadata->queryId);
        self::assertIsIterable($result->rows);

        $rowCount = 0;
        foreach ($result->rows as $row) {
            $rowCount++;
        }

        self::assertGreaterThanOrEqual(0, $rowCount);
    }

    public function testQueryAll(): void
    {
        $result = self::$client->queryAll(new QueryRequest(
            statement: 'SELECT * FROM default.public.users LIMIT 10',
        ));

        self::assertNotEmpty($result->metadata->queryId);
        self::assertIsArray($result->rows);
    }

    public function testGetQuery(): void
    {
        $queryId = self::$client->query(new QueryRequest(
            statement: 'SELECT 1',
            sessionId: 'test-session',
            queryId: '11111111-1111-1111-1111-111111111111',
        ))->metadata->queryId;

        self::assertNotEmpty($queryId);

        $log = self::$client->getQuery($queryId);
        self::assertSame($queryId, $log->queryId);
    }

    public function testCancelQuery(): void
    {
        $statement = 'SELECT * FROM default.public.users';
        $queryResult = self::$client->queryAll(new QueryRequest(statement: $statement));
        $queryId = $queryResult->metadata->queryId;

        $response = self::$client->cancelQuery($queryId, 'test-session');
        self::assertTrue($response->ok, 'Cancel query should return ok=true');
    }

    public function testUploadCsv(): void
    {
        $csv = "id,name,email\n1,Alice,alice@example.com\n2,Bob,bob@example.com\n";

        $response = self::$client->upload(
            'default',
            'public',
            'upload_test',
            UploadFormat::Csv,
            UploadMode::Create,
            $csv,
        );

        self::assertTrue($response->ok, $response->errorCode ?? 'upload returned ok=false');
    }

    public function testValidate(): void
    {
        $response = self::$client->validate(new ValidateRequest('SELECT 1'));
        self::assertTrue($response->valid);
    }

    public function testAppendBatch(): void
    {
        $response = self::$client->append(
            'default',
            'public',
            'batch_test',
            AppendRequest::batch(
                new AppendPayload(data: [['id' => 1, 'val' => 'a']]),
                new AppendPayload(data: [['id' => 2, 'val' => 'b']]),
            ),
        );

        self::assertTrue($response->ok, $response->errorCode ?? 'append returned ok=false');
    }

    public function testQueryInvalidSql(): void
    {
        $this->expectException(\Altertable\Lakehouse\Exceptions\BadRequestError::class);
        self::$client->queryAll(new QueryRequest(statement: 'SELECT INVALID'));
    }
}
