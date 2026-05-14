<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Tests\Unit\Models;

use Altertable\Lakehouse\Models\AppendPayload;
use Altertable\Lakehouse\Models\AppendRequest;
use Altertable\Lakehouse\Models\AppendResponse;
use Altertable\Lakehouse\Models\CancelQueryResponse;
use Altertable\Lakehouse\Models\ColumnSchema;
use Altertable\Lakehouse\Models\ComputeSize;
use Altertable\Lakehouse\Models\QueryLogResponse;
use Altertable\Lakehouse\Models\QueryMetadata;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\UploadFormat;
use Altertable\Lakehouse\Models\UploadMode;
use Altertable\Lakehouse\Models\ValidateRequest;
use Altertable\Lakehouse\Models\ValidateResponse;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testComputeSizeEnum(): void
    {
        self::assertSame('S', ComputeSize::S->value);
        self::assertSame('M', ComputeSize::M->value);
        self::assertSame('L', ComputeSize::L->value);
    }

    public function testUploadFormatEnum(): void
    {
        self::assertSame('csv', UploadFormat::Csv->value);
        self::assertSame('json', UploadFormat::Json->value);
        self::assertSame('parquet', UploadFormat::Parquet->value);
    }

    public function testUploadModeEnum(): void
    {
        self::assertSame('create', UploadMode::Create->value);
        self::assertSame('append', UploadMode::Append->value);
        self::assertSame('upsert', UploadMode::Upsert->value);
        self::assertSame('overwrite', UploadMode::Overwrite->value);
    }

    public function testAppendPayloadToArray(): void
    {
        $payload = new AppendPayload(
            data: [['id' => 1, 'name' => 'Alice']],
            columns: ['id', 'name'],
        );

        $arr = $payload->toArray();
        self::assertSame([['id' => 1, 'name' => 'Alice']], $arr['data']);
        self::assertSame(['id', 'name'], $arr['columns']);
    }

    public function testAppendRequestSingle(): void
    {
        $payload = new AppendPayload(data: [['x' => 1]]);
        $request = AppendRequest::single($payload);

        $arr = $request->toArray();
        self::assertArrayHasKey('data', $arr);
        self::assertSame([['x' => 1]], $arr['data']);
    }

    public function testAppendRequestBatch(): void
    {
        $request = AppendRequest::batch(
            new AppendPayload(data: [['a' => 1]]),
            new AppendPayload(data: [['b' => 2]]),
        );

        $arr = $request->toArray();
        self::assertCount(2, $arr);
        self::assertSame([['a' => 1]], $arr[0]['data']);
        self::assertSame([['b' => 2]], $arr[1]['data']);
    }

    public function testAppendResponseFromArray(): void
    {
        $response = AppendResponse::fromArray(['ok' => true]);
        self::assertTrue($response->ok);
        self::assertNull($response->errorCode);

        $response = AppendResponse::fromArray(['ok' => false, 'error_code' => 'invalid-data']);
        self::assertFalse($response->ok);
        self::assertSame('invalid-data', $response->errorCode);
    }

    public function testQueryRequestToArray(): void
    {
        $request = new QueryRequest(
            statement: 'SELECT 1',
            catalog: 'my_catalog',
            computeSize: ComputeSize::M,
            maxResults: 100,
        );

        $arr = $request->toArray();
        self::assertSame('SELECT 1', $arr['statement']);
        self::assertSame('my_catalog', $arr['catalog']);
        self::assertSame('M', $arr['compute_size']);
        self::assertSame(100, $arr['max_results']);
    }

    public function testQueryMetadataFromArray(): void
    {
        $meta = QueryMetadata::fromArray([
            'query_id' => 'abc-123',
            'status' => 'completed',
            'total_rows' => 42,
            'elapsed_ms' => 150.5,
        ]);

        self::assertSame('abc-123', $meta->queryId);
        self::assertSame('completed', $meta->status);
        self::assertSame(42, $meta->totalRows);
        self::assertSame(150.5, $meta->elapsedMs);
    }

    public function testColumnSchemaFromArray(): void
    {
        $col = ColumnSchema::fromArray([
            'name' => 'id',
            'type' => 'integer',
            'nullable' => false,
        ]);

        self::assertSame('id', $col->name);
        self::assertSame('integer', $col->type);
        self::assertFalse($col->nullable);
    }

    public function testQueryLogResponseFromArray(): void
    {
        $log = QueryLogResponse::fromArray([
            'query_id' => 'q-1',
            'status' => 'running',
            'statement' => 'SELECT 1',
            'duration_ms' => 5000,
            'total_rows' => 100,
            'total_bytes' => 2048,
            'progress' => 0.5,
        ]);

        self::assertSame('q-1', $log->queryId);
        self::assertSame('running', $log->status);
        self::assertSame('SELECT 1', $log->statement);
        self::assertSame(5000, $log->durationMs);
        self::assertSame(0.5, $log->progress);
    }

    public function testCancelQueryResponseFromArray(): void
    {
        $resp = CancelQueryResponse::fromArray(['ok' => true, 'message' => 'Cancelled']);
        self::assertTrue($resp->ok);
        self::assertSame('Cancelled', $resp->message);
    }

    public function testValidateRequestToArray(): void
    {
        $req = new ValidateRequest('SELECT 1', catalog: 'cat', schema: 'sch');
        $arr = $req->toArray();
        self::assertSame('SELECT 1', $arr['statement']);
        self::assertSame('cat', $arr['catalog']);
        self::assertSame('sch', $arr['schema']);
    }

    public function testValidateResponseFromArray(): void
    {
        $resp = ValidateResponse::fromArray([
            'valid' => true,
            'error' => null,
            'suggestions' => [],
        ]);
        self::assertTrue($resp->valid);
        self::assertNull($resp->error);
        self::assertSame([], $resp->suggestions);
    }
}
