<?php

declare(strict_types=1);

namespace Altertable\Lakehouse;

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\Exceptions\ApiError;
use Altertable\Lakehouse\Exceptions\AuthError;
use Altertable\Lakehouse\Exceptions\BadRequestError;
use Altertable\Lakehouse\Exceptions\NetworkError;
use Altertable\Lakehouse\Exceptions\ParseError;
use Altertable\Lakehouse\Exceptions\SerializationError;
use Altertable\Lakehouse\Exceptions\TimeoutError;
use Altertable\Lakehouse\Models\AppendResponse;
use Altertable\Lakehouse\Models\CancelQueryResponse;
use Altertable\Lakehouse\Models\ColumnSchema;
use Altertable\Lakehouse\Models\QueryAllResult;
use Altertable\Lakehouse\Models\QueryLogResponse;
use Altertable\Lakehouse\Models\QueryMetadata;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\QueryResult;
use Altertable\Lakehouse\Models\UpsertMode;
use Altertable\Lakehouse\Models\ValidateRequest;
use Altertable\Lakehouse\Models\ValidateResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class LakehouseClient
{
    private const USER_AGENT = 'altertable-lakehouse-php/0.1.0';

    private readonly ClientInterface $httpClient;
    private readonly LakehouseConfig $config;

    public function __construct(
        LakehouseConfig $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient ?? $this->buildDefaultClient();
    }

    public function getConfig(): LakehouseConfig
    {
        return $this->config;
    }

    // ── Append ────────────────────────────────────────────────────────

    public function append(
        string $catalog,
        string $schema,
        string $table,
        array $payload,
        ?bool $sync = null,
    ): AppendResponse {
        $body = $this->serialize($payload);
        $query = compact('catalog', 'schema', 'table');

        if ($sync !== null) {
            $query['sync'] = $sync;
        }

        $response = $this->send('POST', '/append', [
            'query' => $query,
            'body' => $body,
            'headers' => ['Content-Type' => 'application/json'],
        ], 'append');

        return AppendResponse::fromArray($this->deserialize($response));
    }

    // ── Query (streamed) ──────────────────────────────────────────────

    public function query(QueryRequest $request): QueryResult
    {
        $body = $this->serialize($request->toArray());

        $response = $this->send('POST', '/query', [
            'body' => $body,
            'headers' => ['Content-Type' => 'application/json'],
            'stream' => true,
        ], 'query');

        $stream = $response->getBody();
        $metadata = null;
        $columns = [];
        $firstRow = null;
        $lineIndex = 0;

        while (!$stream->eof()) {
            $line = $this->readLine($stream);
            if ($line === null) {
                continue;
            }
            $lineIndex++;

            $parsed = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ParseError(
                    message: "Failed to parse NDJSON line {$lineIndex}: " . json_last_error_msg(),
                    lineIndex: $lineIndex,
                );
            }

            $type = $parsed['type'] ?? null;
            $isList = array_is_list($parsed);

            if ($type === 'metadata') {
                $metadata = QueryMetadata::fromArray($parsed['metadata'] ?? $parsed);
                continue;
            }

            if ($type === null && array_key_exists('query_id', $parsed)) {
                $metadata = QueryMetadata::fromArray($parsed);
                continue;
            }

            if ($type === 'columns' || $type === 'schema') {
                $columns = array_map(
                    static fn (array $col) => ColumnSchema::fromArray($col),
                    $parsed['columns'] ?? $parsed['schema'] ?? [],
                );
                continue;
            }

            if ($type === null && $isList && $this->looksLikeColumnNames($parsed)) {
                $columns = array_map(
                    static fn (string $name) => new ColumnSchema($name, 'unknown'),
                    $parsed,
                );
                continue;
            }

            $firstRow = $parsed;
            break;
        }

        if ($metadata === null) {
            $metadata = new QueryMetadata(queryId: '', status: 'completed');
        }

        $rows = $this->rowIterator($stream, $lineIndex, $firstRow);

        return new QueryResult(
            metadata: $metadata,
            columns: $columns,
            rows: $rows,
        );
    }

    // ── Query All (accumulated) ───────────────────────────────────────

    public function queryAll(QueryRequest $request): QueryAllResult
    {
        $result = $this->query($request);

        $rows = [];
        foreach ($result->rows as $row) {
            $rows[] = $row;
        }

        return new QueryAllResult(
            metadata: $result->metadata,
            columns: $result->columns,
            rows: $rows,
        );
    }

    // ── Get Query ─────────────────────────────────────────────────────

    public function getQuery(string $queryId): QueryLogResponse
    {
        $response = $this->send('GET', "/query/{$queryId}", [], 'getQuery');
        return QueryLogResponse::fromArray($this->deserialize($response));
    }

    // ── Cancel Query ──────────────────────────────────────────────────

    public function cancelQuery(string $queryId, string $sessionId): CancelQueryResponse
    {
        $response = $this->send('DELETE', "/query/{$queryId}", [
            'query' => ['session_id' => $sessionId],
        ], 'cancelQuery');
        return CancelQueryResponse::fromArray($this->deserialize($response));
    }

    // ── Upsert ────────────────────────────────────────────────────────

    public function upsert(
        string $catalog,
        string $schema,
        string $table,
        string|StreamInterface $body,
        UpsertMode $mode,
        ?string $primaryKey = null,
        string $contentType = 'application/octet-stream',
    ): AppendResponse {
        $query = [
            'catalog' => $catalog,
            'schema' => $schema,
            'table' => $table,
        ];

        $query['mode'] = $mode->value;

        if ($primaryKey !== null) {
            $query['primary_key'] = $primaryKey;
        }

        $response = $this->send('POST', '/upsert', [
            'query' => $query,
            'body' => $body,
            'headers' => ['Content-Type' => $contentType],
        ], 'upsert');

        return AppendResponse::fromArray($this->deserialize($response));
    }

    // ── Validate ──────────────────────────────────────────────────────

    public function validate(ValidateRequest $request): ValidateResponse
    {
        $body = $this->serialize($request->toArray());

        $response = $this->send('POST', '/validate', [
            'body' => $body,
            'headers' => ['Content-Type' => 'application/json'],
        ], 'validate');

        return ValidateResponse::fromArray($this->deserialize($response));
    }

    // ── Internal ──────────────────────────────────────────────────────

    private function buildDefaultClient(): ClientInterface
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            $this->retryDecider(...),
            $this->retryDelay(...),
        ));

        $ua = self::USER_AGENT;
        if ($this->config->userAgentSuffix !== null) {
            $ua .= ' ' . $this->config->userAgentSuffix;
        }

        return new GuzzleClient([
            'handler' => $stack,
            'base_uri' => $this->config->baseUrl,
            'connect_timeout' => $this->config->connectTimeout,
            'read_timeout' => $this->config->readTimeout,
            'timeout' => $this->config->readTimeout,
            'headers' => [
                'Authorization' => $this->config->basicAuthToken,
                'User-Agent' => $ua,
            ],
            'http_errors' => false,
            'allow_redirects' => true,
            'keep_alive' => true,
        ]);
    }

    private function retryDecider(
        int $retries,
        Request $request,
        ?ResponseInterface $response = null,
        ?\RuntimeException $exception = null,
    ): bool {
        if ($retries >= $this->config->maxRetries) {
            return false;
        }

        if ($response !== null && $response->getStatusCode() >= 500) {
            return true;
        }

        if ($exception instanceof ConnectException) {
            return true;
        }

        if ($exception instanceof TooManyRedirectsException) {
            return false;
        }

        return false;
    }

    private function retryDelay(int $retries, ?ResponseInterface $response = null): int
    {
        $delay = $this->config->retryDelayMs * (2 ** $retries);
        return min($delay, 10_000);
    }

    private function send(string $method, string $path, array $options, string $operation): ResponseInterface
    {
        try {
            $response = $this->httpClient->request($method, $path, $options);
        } catch (ConnectException $e) {
            throw new NetworkError(
                message: "{$operation}: Connection failed: " . $e->getMessage(),
                previous: $e,
                operation: $operation,
                method: $method,
                path: $path,
            );
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                throw new TimeoutError(
                    message: "{$operation}: Request timed out",
                    previous: $e,
                    operation: $operation,
                    method: $method,
                    path: $path,
                );
            }
            throw new NetworkError(
                message: "{$operation}: " . $e->getMessage(),
                previous: $e,
                operation: $operation,
                method: $method,
                path: $path,
            );
        } catch (GuzzleException $e) {
            throw new NetworkError(
                message: "{$operation}: " . $e->getMessage(),
                previous: $e,
                operation: $operation,
                method: $method,
                path: $path,
            );
        }

        $statusCode = $response->getStatusCode();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: $response->getHeaderLine('Request-Id') ?: null;

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->handleErrorResponse($response, $operation, $method, $path, $requestId);
        }

        return $response;
    }

    private function handleErrorResponse(
        ResponseInterface $response,
        string $operation,
        string $method,
        string $path,
        ?string $requestId,
    ): never {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $message = $body !== '' ? $body : "HTTP {$statusCode}";

        $exception = match (true) {
            $statusCode === 401 => new AuthError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
            $statusCode === 400 || $statusCode === 422 => new BadRequestError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
            $statusCode === 403 => new AuthError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
            $statusCode === 404 => new BadRequestError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
            $statusCode === 408 || $statusCode === 429 => new TimeoutError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                requestId: $requestId,
            ),
            $statusCode >= 500 => new ApiError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
            default => new ApiError(
                message: $message,
                operation: $operation,
                method: $method,
                path: $path,
                statusCode: $statusCode,
                requestId: $requestId,
            ),
        };

        throw $exception;
    }

    private function serialize(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SerializationError('Failed to encode request body', $e);
        }
    }

    private function deserialize(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ParseError(
                message: 'Failed to parse response JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return $data ?? [];
    }

    private function readLine(StreamInterface $stream): ?string
    {
        $line = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $line .= $byte;
        }
        if ($line === '' && $stream->eof()) {
            return null;
        }
        return $line;
    }

    private function looksLikeColumnNames(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        foreach ($data as $value) {
            if (!is_string($value)) {
                return false;
            }
        }

        return true;
    }

    private function rowIterator(StreamInterface $stream, int &$lineIndex, mixed $firstRow = null): \Generator
    {
        if ($firstRow !== null) {
            yield $firstRow;
        }

        while (!$stream->eof()) {
            $line = $this->readLine($stream);
            if ($line === null) {
                break;
            }
            $lineIndex++;

            if (trim($line) === '') {
                continue;
            }

            $parsed = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ParseError(
                    message: "Failed to parse NDJSON row at line {$lineIndex}: " . json_last_error_msg(),
                    lineIndex: $lineIndex,
                );
            }

            yield $parsed;
        }
    }
}
