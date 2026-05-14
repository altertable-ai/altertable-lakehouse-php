# Altertable Lakehouse PHP Client

[![CI](https://github.com/altertable-ai/altertable-lakehouse-php/actions/workflows/ci.yml/badge.svg)](https://github.com/altertable-ai/altertable-lakehouse-php/actions/workflows/ci.yml)

PHP client library for the [Altertable Lakehouse API](https://api.altertable.ai). Supports all Lakehouse operations with typed models, streaming queries, and configurable retry/auth.

## Installation

```bash
composer require altertable/lakehouse-php
```

## Quick Start

```php
<?php

use Altertable\Lakehouse\Config\LakehouseConfig;
use Altertable\Lakehouse\LakehouseClient;
use Altertable\Lakehouse\Models\AppendPayload;
use Altertable\Lakehouse\Models\AppendRequest;
use Altertable\Lakehouse\Models\QueryRequest;
use Altertable\Lakehouse\Models\UploadFormat;
use Altertable\Lakehouse\Models\UploadMode;
use Altertable\Lakehouse\Models\ValidateRequest;

$config = LakehouseConfig::builder()
    ->withCredentials('your-username', 'your-password')
    ->build();

$client = new LakehouseClient($config);
```

### Authentication

Credentials are resolved in this priority order:

1. Direct `username` + `password` passed to `LakehouseConfig::builder()`
2. Pre-encoded `Basic` token via `withBasicAuthToken()`
3. Environment variables: `ALTERTABLE_USERNAME` + `ALTERTABLE_PASSWORD`
4. Environment variable: `ALTERTABLE_BASIC_AUTH_TOKEN`

A `ConfigurationError` is thrown at construction if no credentials are found.

### Append

```php
// Single record
$response = $client->append('my_catalog', 'my_schema', 'my_table',
    AppendRequest::single(new AppendPayload(
        data: [['id' => 1, 'name' => 'Alice']],
        columns: ['id', 'name'],
    )),
);

// Batch records
$response = $client->append('my_catalog', 'my_schema', 'my_table',
    AppendRequest::batch(
        new AppendPayload(data: [['id' => 1, 'val' => 'a']]),
        new AppendPayload(data: [['id' => 2, 'val' => 'b']]),
    ),
);
```

### Query (Streamed)

```php
$result = $client->query(new QueryRequest(
    statement: 'SELECT * FROM my_catalog.my_schema.my_table LIMIT 100',
));

echo $result->metadata->queryId . "\n";

foreach ($result->rows as $row) {
    echo json_encode($row) . "\n";
}
```

### Query All (Accumulated)

```php
$result = $client->queryAll(new QueryRequest(
    statement: 'SELECT * FROM my_catalog.my_schema.my_table LIMIT 100',
));

echo "Columns: " . count($result->columns) . "\n";
echo "Rows: " . count($result->rows) . "\n";
```

### Get Query

```php
$log = $client->getQuery('your-query-id');
echo $log->status . ' - ' . ($log->progress * 100) . '%';
```

### Cancel Query

```php
$response = $client->cancelQuery('your-query-id', 'your-session-id');
```

### Upload

```php
$csv = "id,name,email\n1,Alice,alice@example.com\n";

$response = $client->upload(
    'my_catalog',
    'my_schema',
    'my_table',
    UploadFormat::Csv,
    UploadMode::Create,
    $csv,
);

// Upsert with primary key
$response = $client->upload(
    'my_catalog',
    'my_schema',
    'my_table',
    UploadFormat::Json,
    UploadMode::Upsert,
    '[{"id":1,"name":"Alice"}]',
    primaryKey: 'id',
);
```

### Validate

```php
$result = $client->validate(new ValidateRequest('SELECT 1'));
var_dump($result->valid);
```

## Configuration

```php
use Altertable\Lakehouse\Config\LakehouseConfig;

$config = LakehouseConfig::builder()
    ->withBaseUrl('https://api.altertable.ai')    // default
    ->withConnectTimeout(10)                       // seconds (default: 5)
    ->withReadTimeout(120)                         // seconds (default: 60)
    ->withMaxRetries(5)                            // default: 3
    ->withRetryDelayMs(1000)                       // default: 500ms
    ->withUserAgentSuffix('my-app/1.0')
    ->build();
```

Or with an array:

```php
$config = LakehouseConfig::fromArray([
    'username' => 'user',
    'password' => 'pass',
    'base_url' => 'http://localhost:15000',
]);
```

## Error Handling

All errors extend `LakehouseException` with typed subclasses:

| Exception | HTTP Status | Retriable |
|---|---|---|
| `ConfigurationError` | — | No |
| `AuthError` | 401, 403 | No |
| `BadRequestError` | 400, 404, 422 | No |
| `ApiError` | 500+ | Yes |
| `NetworkError` | — | Yes |
| `TimeoutError` | 408, 429 | Yes |
| `ParseError` | — | No |
| `SerializationError` | — | No |

```php
use Altertable\Lakehouse\Exceptions\{AuthError, BadRequestError, NetworkError, TimeoutError};

try {
    $client->queryAll(new QueryRequest('SELECT 1'));
} catch (AuthError $e) {
    // check credentials
} catch (BadRequestError $e) {
    // invalid SQL
} catch (TimeoutError $e) {
    // query timed out
}
```

## Retries & Timeouts

- **Connect timeout**: 5 seconds (configurable)
- **Read timeout**: 60 seconds (configurable)
- **Retries**: up to 3 with exponential backoff (500ms base)
- Retriable: 5xx responses, connection errors, timeouts

## Development

```bash
composer install
./vendor/bin/phpcs      # lint
./vendor/bin/phpunit    # test
```

### Running Integration Tests

Integration tests target `altertable-mock`:

```bash
# Start the mock server on port 15000
export ALTERTABLE_MOCK_USERS="testuser:testpass"
docker run --rm -p 15000:15000 \
  -e ALTERTABLE_MOCK_USERS=testuser:testpass \
  ghcr.io/altertable-ai/altertable-mock:latest

# Run tests
./vendor/bin/phpunit
```

## License

MIT
