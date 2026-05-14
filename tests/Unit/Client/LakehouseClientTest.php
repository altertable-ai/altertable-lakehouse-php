<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Tests\Unit\Client;

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\Exceptions\AuthError;
use Altertable\Lakehouse\Exceptions\BadRequestError;
use Altertable\Lakehouse\Exceptions\NetworkError;
use Altertable\Lakehouse\Exceptions\ParseError;
use Altertable\Lakehouse\Exceptions\TimeoutError;
use Altertable\Lakehouse\LakehouseClient;
use Altertable\Lakehouse\Models\AppendPayload;
use Altertable\Lakehouse\Models\AppendRequest;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\UploadFormat;
use Altertable\Lakehouse\Models\UploadMode;
use Altertable\Lakehouse\Models\ValidateRequest;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class LakehouseClientTest extends TestCase
{
    private LakehouseConfig $config;

    protected function setUp(): void
    {
        $this->config = LakehouseConfig::builder()
            ->withCredentials('test', 'test')
            ->withBaseUrl('http://localhost:15000')
            ->build();
    }

    public function testAppendSuccess(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('POST', '/append', self::anything())
            ->willReturn(new Response(200, [], '{"ok":true}'));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->append('cat', 'sch', 'tbl', AppendRequest::single(new AppendPayload(data: [['x' => 1]])));

        self::assertTrue($result->ok);
    }

    public function testQueryAllReturnsRows(): void
    {
        $ndjson = "{\"type\":\"metadata\",\"query_id\":\"q1\",\"status\":\"completed\"}\n"
            . "{\"type\":\"columns\",\"columns\":[{\"name\":\"id\",\"type\":\"integer\"}]}\n"
            . "{\"id\":1,\"name\":\"Alice\"}\n"
            . "{\"id\":2,\"name\":\"Bob\"}\n";

        $stream = $this->createStream($ndjson);

        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('POST', '/query', self::anything())
            ->willReturn(new Response(200, [], $stream));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->queryAll(new QueryRequest(statement: 'SELECT * FROM t'));

        self::assertSame('q1', $result->metadata->queryId);
        self::assertCount(1, $result->columns);
        self::assertCount(2, $result->rows);
        self::assertSame('Alice', $result->rows[0]['name']);
    }

    public function testGetQuery(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('GET', '/query/q-1', self::anything())
            ->willReturn(new Response(200, [], '{"query_id":"q-1","status":"completed"}'));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->getQuery('q-1');

        self::assertSame('q-1', $result->queryId);
        self::assertSame('completed', $result->status);
    }

    public function testCancelQuery(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('DELETE', '/query/q-1', self::callback(function (array $options): bool {
                return ($options['query']['session_id'] ?? null) === 'sess-1';
            }))
            ->willReturn(new Response(200, [], '{"ok":true}'));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->cancelQuery('q-1', 'sess-1');

        self::assertTrue($result->ok);
    }

    public function testUploadCsv(): void
    {
        $body = "id,name\n1,Alice\n";

        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('POST', '/upload', self::callback(function (array $options) use ($body): bool {
                return $options['body'] === $body
                    && $options['headers']['Content-Type'] === 'text/csv'
                    && $options['query']['format'] === 'csv'
                    && $options['query']['mode'] === 'create';
            }))
            ->willReturn(new Response(200, [], '{"ok":true}'));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->upload('cat', 'sch', 'tbl', UploadFormat::Csv, UploadMode::Create, $body);

        self::assertTrue($result->ok);
    }

    public function testUploadWithPrimaryKey(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('POST', '/upload', self::callback(function (array $options): bool {
                return ($options['query']['primary_key'] ?? null) === 'id'
                    && $options['query']['mode'] === 'upsert';
            }))
            ->willReturn(new Response(200, [], '{"ok":true}'));

        $client = new LakehouseClient($this->config, $mock);
        $client->upload('cat', 'sch', 'tbl', UploadFormat::Json, UploadMode::Upsert, '{}', primaryKey: 'id');
    }

    public function testValidate(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->with('POST', '/validate', self::anything())
            ->willReturn(new Response(200, [], '{"valid":true}'));

        $client = new LakehouseClient($this->config, $mock);
        $result = $client->validate(new ValidateRequest('SELECT 1'));

        self::assertTrue($result->valid);
    }

    public function testAuthErrorOn401(): void
    {
        $this->expectException(AuthError::class);

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')
            ->willReturn(new Response(401, [], 'Unauthorized'));

        $client = new LakehouseClient($this->config, $mock);
        $client->validate(new ValidateRequest('SELECT 1'));
    }

    public function testBadRequestErrorOn400(): void
    {
        $this->expectException(BadRequestError::class);

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')
            ->willReturn(new Response(400, [], 'Bad Request'));

        $client = new LakehouseClient($this->config, $mock);
        $client->validate(new ValidateRequest('SELECT 1'));
    }

    public function testNetworkErrorOnException(): void
    {
        $this->expectException(NetworkError::class);

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')
            ->willThrowException(new \GuzzleHttp\Exception\ConnectException('Failed', new \GuzzleHttp\Psr7\Request('GET', '')));

        $client = new LakehouseClient($this->config, $mock);
        $client->validate(new ValidateRequest('SELECT 1'));
    }

    public function testParseErrorOnInvalidNdjson(): void
    {
        $this->expectException(ParseError::class);

        $stream = $this->createStream("invalid json\n");

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')
            ->willReturn(new Response(200, [], $stream));

        $client = new LakehouseClient($this->config, $mock);
        foreach ($client->query(new QueryRequest('SELECT 1'))->rows as $_) {
        }
    }

    public function testClientConfigIsAccessible(): void
    {
        $client = new LakehouseClient($this->config);
        self::assertSame($this->config, $client->getConfig());
    }

    private function createStream(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(...array_fill(0, strlen($content), false), true);
        $stream->method('read')->willReturnCallback(function (int $length) use ($content, &$offset) {
            $offset ??= 0;
            $chunk = substr($content, $offset, $length);
            $offset += $length;
            return $chunk;
        });
        return $stream;
    }
}
