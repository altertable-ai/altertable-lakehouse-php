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

    public static function setUpBeforeClass(): void
    {
        $port = getenv('ALTERTABLE_MOCK_PORT') ?: '15000';
        $baseUrl = 'http://localhost:' . $port;

        self::$client = new LakehouseClient(
            LakehouseConfig::builder()
                ->withCredentials('testuser', 'testpass')
                ->withBaseUrl($baseUrl)
                ->build(),
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$client = null;
    }

    public function testAppend(): void
    {
        $payload = new AppendPayload(
            data: [['id' => 1, 'name' => 'Alice']],
            columns: ['id', 'name'],
        );

        $response = self::$client->append('default', 'public', 'users', AppendRequest::single($payload));
        self::assertTrue($response->ok);
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
        $queryResult = self::$client->queryAll(new QueryRequest(
            statement: 'SELECT 1',
        ));

        $queryId = $queryResult->metadata->queryId;
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

        self::assertTrue($response->ok);
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

        self::assertTrue($response->ok);
    }

    public function testQueryInvalidSql(): void
    {
        $this->expectException(\Altertable\Lakehouse\Exceptions\BadRequestError::class);
        self::$client->queryAll(new QueryRequest(statement: 'SELECT INVALID'));
    }
}
